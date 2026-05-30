<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\repo\ColorsRepo;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\ProjectsRepo;
use dev_t0r\trvis_backend\repo\StationsOnLineRepo;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
use dev_t0r\trvis_backend\repo\StopPatternRowsRepo;
use dev_t0r\trvis_backend\repo\StopPatternsRepo;
use dev_t0r\trvis_backend\repo\TimetableRowsRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * ProjectTransferService — dedicated whole-Project export/import.
 *
 * Export aggregates every entity under a Project into one flat ProjectGraph
 * envelope in a single (consistent-snapshot) read transaction. Import recreates
 * the entire graph under a brand-new Project in ONE write transaction, with a
 * server-side old→new id remap so the new project is fully self-consistent and
 * independent of the source.
 *
 * Reads go straight to the repos with a large perPage (not the 100-row API
 * paging limit) so a real project never silently truncates on export. Both
 * directions are capped (EXPORT_MAX_ROWS / IMPORT_MAX_ROWS) and a too-large
 * payload is a clean 400, never a hang.
 *
 * Dangling-FK policy (a child may reference a soft-deleted parent that the
 * deleted_at-filtered reads omit): a row whose REQUIRED parent id can't be
 * remapped is SKIPPED and counted in `skipped` (never a silent loss, never a
 * NOT NULL abort); a NULLABLE dangling FK is dropped to null.
 */
final class ProjectTransferService
{
	/** read ceiling per select; a project hitting this exports as 400 (too large) */
	private const EXPORT_MAX_ROWS = 100000;
	/** total-rows ceiling for a single import payload */
	private const IMPORT_MAX_ROWS = 100000;

	private readonly ProjectsRepo $projectsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;
	private readonly ColorsRepo $colorsRepo;
	private readonly StationsRepo $stationsRepo;
	private readonly StationTracksRepo $stationTracksRepo;
	private readonly LineRepo $lineRepo;
	private readonly StationsOnLineRepo $stationsOnLineRepo;
	private readonly StopPatternsRepo $stopPatternsRepo;
	private readonly StopPatternRowsRepo $stopPatternRowsRepo;
	private readonly WorkGroupsRepo $workGroupsRepo;
	private readonly WorksRepo $worksRepo;
	private readonly TrainsRepo $trainsRepo;
	private readonly TimetableRowsRepo $timetableRowsRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->projectsRepo = new ProjectsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
		$this->colorsRepo = new ColorsRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
		$this->stationTracksRepo = new StationTracksRepo($db, $logger);
		$this->lineRepo = new LineRepo($db, $logger);
		$this->stationsOnLineRepo = new StationsOnLineRepo($db, $logger);
		$this->stopPatternsRepo = new StopPatternsRepo($db, $logger);
		$this->stopPatternRowsRepo = new StopPatternRowsRepo($db, $logger);
		$this->workGroupsRepo = new WorkGroupsRepo($db, $logger);
		$this->worksRepo = new WorksRepo($db, $logger);
		$this->trainsRepo = new TrainsRepo($db, $logger);
		$this->timetableRowsRepo = new TimetableRowsRepo($db, $logger);
	}

	// ───────────────────────────── EXPORT ─────────────────────────────

	/**
	 * Aggregate the whole graph under $projectsId into a flat ProjectGraph.
	 *
	 * @return RetValueOrError<array<string,mixed>>
	 */
	public function export(
		UuidInterface $projectsId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"export(projectsId:{projectsId}, userId:{userId})",
			['projectsId' => $projectsId, 'userId' => $userId],
		);

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		if (!$privResult->value->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errProjectNotFound();
		}

		$projectResult = $this->projectsRepo->selectProjectOne($userId, $projectsId);
		if ($projectResult->isError) {
			return $projectResult;
		}

		// Consistent-snapshot read (REPEATABLE READ) so cross-entity paging
		// can't tear under a concurrent writer.
		$this->db->beginTransaction();
		try {
			$colors = $this->unwrap($this->colorsRepo->selectPage($projectsId, 1, self::EXPORT_MAX_ROWS, null));
			$stations = $this->unwrap($this->stationsRepo->selectStationPage($projectsId, $userId, 1, self::EXPORT_MAX_ROWS, null));
			$lines = $this->unwrap($this->lineRepo->selectLinePage($userId, $projectsId, 1, self::EXPORT_MAX_ROWS, null));
			$stopPatterns = $this->unwrap($this->stopPatternsRepo->selectStopPatternPage($projectsId, $userId, 1, self::EXPORT_MAX_ROWS, null));
			$workGroups = $this->unwrap($this->workGroupsRepo->selectWorkGroupPageByProjectId($projectsId, $userId, 1, self::EXPORT_MAX_ROWS, null));

			$stationTracks = [];
			foreach ($stations as $st) {
				foreach ($this->unwrap($this->stationTracksRepo->selectStationTrackPage($st->stations_id, $userId, 1, self::EXPORT_MAX_ROWS, null)) as $t) {
					$stationTracks[] = $t;
				}
			}
			$stationsOnLine = [];
			foreach ($lines as $ln) {
				foreach ($this->unwrap($this->stationsOnLineRepo->selectStationOnLinePage($ln->lines_id, $userId, 1, self::EXPORT_MAX_ROWS, null)) as $sol) {
					$stationsOnLine[] = $sol;
				}
			}
			$stopPatternRows = [];
			foreach ($stopPatterns as $sp) {
				foreach ($this->unwrap($this->stopPatternRowsRepo->selectStopPatternRowPage($sp->stop_patterns_id, $userId, 1, self::EXPORT_MAX_ROWS, null)) as $r) {
					$stopPatternRows[] = $r;
				}
			}
			$works = [];
			foreach ($workGroups as $wg) {
				foreach ($this->unwrap($this->worksRepo->selectWorkPage($wg->work_groups_id, $userId, 1, self::EXPORT_MAX_ROWS, null)) as $w) {
					$works[] = $w;
				}
			}
			$trains = [];
			foreach ($works as $w) {
				foreach ($this->unwrap($this->trainsRepo->selectTrainPage($w->works_id, 1, self::EXPORT_MAX_ROWS, null)) as $t) {
					$trains[] = $t;
				}
			}
			$timetableRows = [];
			foreach ($trains as $t) {
				foreach ($this->unwrap($this->timetableRowsRepo->selectTimetableRowPage($t->trains_id, 1, self::EXPORT_MAX_ROWS, null)) as $r) {
					$timetableRows[] = $r;
				}
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			$this->logger->error("export failed: {exception}", ['exception' => $e]);
			if ($e instanceof TransferTooLargeException) {
				return RetValueOrError::withError(Constants::HTTP_BAD_REQUEST, $e->getMessage());
			}
			return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, 'export failed');
		}

		$graph = [
			'project' => $projectResult->value,
			'colors' => $colors,
			'stations' => $stations,
			'station_tracks' => $stationTracks,
			'lines' => $lines,
			'stations_on_line' => $stationsOnLine,
			'stop_patterns' => $stopPatterns,
			'stop_pattern_rows' => $stopPatternRows,
			'work_groups' => $workGroups,
			'works' => $works,
			'trains' => $trains,
			'timetable_rows' => $timetableRows,
		];
		return RetValueOrError::withValue($graph);
	}

	/**
	 * Unwrap a select RetValueOrError to its array, throwing on error / cap so
	 * the export transaction aborts cleanly.
	 *
	 * @return array<object>
	 */
	private function unwrap(RetValueOrError $r): array
	{
		if ($r->isError) {
			throw new \RuntimeException("select failed: [{$r->statusCode}] {$r->errorMsg}");
		}
		$list = $r->value;
		if (count($list) >= self::EXPORT_MAX_ROWS) {
			throw new TransferTooLargeException(
				"Project is too large to export (an entity exceeds " . self::EXPORT_MAX_ROWS . " rows)",
			);
		}
		return $list;
	}

	// ───────────────────────────── IMPORT ─────────────────────────────

	/**
	 * Recreate $graph as a brand-new Project owned by $userId in one
	 * transaction. Returns the new Project (201) on success.
	 *
	 * @param array<string,mixed>|object $graph the parsed ProjectGraph body
	 * @return RetValueOrError<object>
	 */
	public function import(
		string $userId,
		mixed $graph,
	): RetValueOrError {
		$this->logger->debug("import(userId:{userId})", ['userId' => $userId]);

		$project = Utils::getValueOrNull($graph, 'project');
		if (is_null($project)) {
			return RetValueOrError::withError(Constants::HTTP_BAD_REQUEST, "'project' is required");
		}

		$colors = $this->listOf($graph, 'colors');
		$stations = $this->listOf($graph, 'stations');
		$stationTracks = $this->listOf($graph, 'station_tracks');
		$lines = $this->listOf($graph, 'lines');
		$stationsOnLine = $this->listOf($graph, 'stations_on_line');
		$stopPatterns = $this->listOf($graph, 'stop_patterns');
		$stopPatternRows = $this->listOf($graph, 'stop_pattern_rows');
		$workGroups = $this->listOf($graph, 'work_groups');
		$works = $this->listOf($graph, 'works');
		$trains = $this->listOf($graph, 'trains');
		$timetableRows = $this->listOf($graph, 'timetable_rows');

		$total = count($colors) + count($stations) + count($stationTracks) + count($lines)
			+ count($stationsOnLine) + count($stopPatterns) + count($stopPatternRows)
			+ count($workGroups) + count($works) + count($trains) + count($timetableRows);
		if ($total > self::IMPORT_MAX_ROWS) {
			return RetValueOrError::withError(
				Constants::HTTP_BAD_REQUEST,
				"Import payload is too large. Max total rows is " . self::IMPORT_MAX_ROWS,
			);
		}

		// old-id (string) → new UuidInterface
		$colorMap = [];
		$stationMap = [];
		$stationTrackMap = [];
		$lineMap = [];
		$stopPatternMap = [];
		$workGroupMap = [];
		$workMap = [];
		$trainMap = [];
		$skipped = 0;

		$this->db->beginTransaction();
		try {
			// 1. Project + owner-admin privilege (mirrors ProjectsService::createProject)
			$newProjectId = Uuid::uuid7();
			$this->ok($this->projectsRepo->insertProject(
				projectId: $newProjectId,
				owner: $userId,
				name: (string)(Utils::getValueOrNull($project, 'name') ?? ''),
				description: (string)(Utils::getValueOrNull($project, 'description') ?? ''),
			));
			$this->ok($this->projectsPrivilegesRepo->insert(
				projectsId: $newProjectId,
				userId: $userId,
				privilegeType: InviteKeyPrivilegeType::admin,
			));

			// 2. Colors (bulk insert via model list, like ColorsService::create)
			if (!empty($colors)) {
				$colorIds = [];
				$colorModels = [];
				foreach ($colors as $c) {
					$colorIds[] = Uuid::uuid7();
					$m = new Color();
					$m->setData([
						'name' => Utils::getValueOrNull($c, 'name'),
						'description' => Utils::getValueOrNull($c, 'description'),
						// ColorsRepo::insertList reads `color_8bit->red` (object
						// access); Slim's body parser yields assoc arrays, so
						// coerce to stdClass to stay parser-agnostic.
						'color_8bit' => $this->toObjOrNull(Utils::getValueOrNull($c, 'color_8bit')),
						'color_real' => $this->toObjOrNull(Utils::getValueOrNull($c, 'color_real')),
					]);
					$colorModels[] = $m;
				}
				$this->ok($this->colorsRepo->insertList(
					projectsId: $newProjectId,
					owner: $userId,
					insertIdList: $colorIds,
					dataList: $colorModels,
					db: $this->db,
				));
				foreach ($colors as $i => $c) {
					$colorMap[(string)Utils::getValueOrNull($c, 'colors_id')] = $colorIds[$i];
				}
			}

			// 3. Stations
			foreach ($stations as $s) {
				$newId = Uuid::uuid7();
				$this->ok($this->stationsRepo->insertStation(
					stationsId: $newId,
					projectsId: $newProjectId,
					owner: $userId,
					name: (string)Utils::getValueOrNull($s, 'name'),
					fullName: Utils::getValueOrNull($s, 'full_name'),
					locationKm: Utils::floatvalOrNull(Utils::getValueOrNull($s, 'location_km')) ?? 0.0,
					locationLonlat: $this->parseStationLonlat($s),
					onStationDetectRadiusM: Utils::floatvalOrNull(Utils::getValueOrNull($s, 'on_station_detect_radius_m')),
					recordType: StationRecordType::fromOrNull(Utils::getValueOrNull($s, 'record_type')) ?? StationRecordType::normal,
					alwaysShowHh: boolval(Utils::getValueOrNull($s, 'always_show_hh')),
				));
				$stationMap[(string)Utils::getValueOrNull($s, 'stations_id')] = $newId;
			}

			// 4. Station tracks (child of station — required FK)
			foreach ($stationTracks as $t) {
				$parent = $this->remap($stationMap, Utils::getValueOrNull($t, 'stations_id'));
				if (is_null($parent)) {
					$skipped++;
					continue;
				}
				$newId = Uuid::uuid7();
				$this->ok($this->stationTracksRepo->insertStationTrack(
					stationTracksId: $newId,
					stationsId: $parent,
					owner: $userId,
					description: (string)(Utils::getValueOrNull($t, 'description') ?? ''),
					name: (string)Utils::getValueOrNull($t, 'name'),
					runInLimit: $this->intOrNull(Utils::getValueOrNull($t, 'run_in_limit')),
					runOutLimit: $this->intOrNull(Utils::getValueOrNull($t, 'run_out_limit')),
				));
				$stationTrackMap[(string)Utils::getValueOrNull($t, 'station_tracks_id')] = $newId;
			}

			// 5. Lines
			foreach ($lines as $ln) {
				$newId = Uuid::uuid7();
				$this->ok($this->lineRepo->insertLine(
					lineId: $newId,
					projectsId: $newProjectId,
					owner: $userId,
					description: (string)(Utils::getValueOrNull($ln, 'description') ?? ''),
					name: (string)Utils::getValueOrNull($ln, 'name'),
				));
				$lineMap[(string)Utils::getValueOrNull($ln, 'lines_id')] = $newId;
			}

			// 6. Stations-on-line (leaf — both FKs required)
			foreach ($stationsOnLine as $sol) {
				$line = $this->remap($lineMap, Utils::getValueOrNull($sol, 'lines_id'));
				$station = $this->remap($stationMap, Utils::getValueOrNull($sol, 'project_stations_id'));
				if (is_null($line) || is_null($station)) {
					$skipped++;
					continue;
				}
				$this->ok($this->stationsOnLineRepo->insertStationOnLine(
					stationOnLineId: Uuid::uuid7(),
					linesId: $line,
					owner: $userId,
					projectStationsId: $station,
					locationM: Utils::floatvalOrNull(Utils::getValueOrNull($sol, 'location_m')) ?? 0.0,
					locationLonlat: $this->parseStationOnLineLonlat($sol),
					trackHiddenByDefault: boolval(Utils::getValueOrNull($sol, 'track_hidden_by_default')),
				));
			}

			// 7. Stop patterns (line required; from/to stations nullable)
			foreach ($stopPatterns as $sp) {
				$line = $this->remap($lineMap, Utils::getValueOrNull($sp, 'lines_id'));
				if (is_null($line)) {
					$skipped++;
					continue;
				}
				$newId = Uuid::uuid7();
				$this->ok($this->stopPatternsRepo->insertStopPattern(
					stopPatternId: $newId,
					projectsId: $newProjectId,
					owner: $userId,
					name: (string)Utils::getValueOrNull($sp, 'name'),
					linesId: $line,
					direction: $this->intOrNull(Utils::getValueOrNull($sp, 'direction')) ?? 1,
					fromProjectStationsId: $this->remap($stationMap, Utils::getValueOrNull($sp, 'from_project_stations_id')),
					toProjectStationsId: $this->remap($stationMap, Utils::getValueOrNull($sp, 'to_project_stations_id')),
				));
				$stopPatternMap[(string)Utils::getValueOrNull($sp, 'stop_patterns_id')] = $newId;
			}

			// 8. Stop-pattern rows (leaf — pattern + station required)
			foreach ($stopPatternRows as $r) {
				$pattern = $this->remap($stopPatternMap, Utils::getValueOrNull($r, 'stop_patterns_id'));
				$station = $this->remap($stationMap, Utils::getValueOrNull($r, 'project_stations_id'));
				if (is_null($pattern) || is_null($station)) {
					$skipped++;
					continue;
				}
				$this->ok($this->stopPatternRowsRepo->insertStopPatternRow(
					stopPatternRowId: Uuid::uuid7(),
					stopPatternsId: $pattern,
					owner: $userId,
					projectStationsId: $station,
					sortKey: $this->intOrNull(Utils::getValueOrNull($r, 'sort_key')) ?? 0,
					trackName: Utils::getValueOrNull($r, 'track_name'),
					trackHidden: boolval(Utils::getValueOrNull($r, 'track_hidden')),
					isOperationOnlyStop: boolval(Utils::getValueOrNull($r, 'is_operation_only_stop')),
					isPass: boolval(Utils::getValueOrNull($r, 'is_pass')),
					driveTimeMm: $this->intOrNull(Utils::getValueOrNull($r, 'drive_time_mm')),
					driveTimeSs: $this->intOrNull(Utils::getValueOrNull($r, 'drive_time_ss')),
					dwellTimeMm: $this->intOrNull(Utils::getValueOrNull($r, 'dwell_time_mm')),
					dwellTimeSs: $this->intOrNull(Utils::getValueOrNull($r, 'dwell_time_ss')),
					showArrive: boolval(Utils::getValueOrNull($r, 'show_arrive') ?? true),
					showDeparture: boolval(Utils::getValueOrNull($r, 'show_departure') ?? true),
					arriveStr: Utils::getValueOrNull($r, 'arrive_str'),
					departureStr: Utils::getValueOrNull($r, 'departure_str'),
					runInLimit: $this->intOrNull(Utils::getValueOrNull($r, 'run_in_limit')),
					runOutLimit: $this->intOrNull(Utils::getValueOrNull($r, 'run_out_limit')),
					remarks: Utils::getValueOrNull($r, 'remarks'),
					alwaysShowHh: boolval(Utils::getValueOrNull($r, 'always_show_hh')),
				));
			}

			// 9. Work groups (attached to the new project)
			foreach ($workGroups as $wg) {
				$newId = Uuid::uuid7();
				$this->ok($this->workGroupsRepo->insertWorkGroup(
					workGroupId: $newId,
					projectsId: $newProjectId,
					owner: $userId,
					name: (string)Utils::getValueOrNull($wg, 'name'),
					description: (string)(Utils::getValueOrNull($wg, 'description') ?? ''),
				));
				$workGroupMap[(string)Utils::getValueOrNull($wg, 'work_groups_id')] = $newId;
			}

			// 10. Works (work group required)
			foreach ($works as $w) {
				$wg = $this->remap($workGroupMap, Utils::getValueOrNull($w, 'work_groups_id'));
				if (is_null($wg)) {
					$skipped++;
					continue;
				}
				$newId = Uuid::uuid7();
				$this->ok($this->worksRepo->insertWork(
					worksId: $newId,
					workGroupsId: $wg,
					owner: $userId,
					name: (string)Utils::getValueOrNull($w, 'name'),
					description: (string)(Utils::getValueOrNull($w, 'description') ?? ''),
					affectDate: Utils::fromJsonDateOnlyStrToDateTime(Utils::getValueOrNull($w, 'affect_date')),
					affixContentType: TrvisContentType::fromOrNull(Utils::getValueOrNull($w, 'affix_content_type')),
					remarks: Utils::getValueOrNull($w, 'remarks'),
					hasETrainTimetable: boolval(Utils::getValueOrNull($w, 'has_e_train_timetable')),
					eTrainTimetableContentType: TrvisContentType::fromOrNull(Utils::getValueOrNull($w, 'e_train_timetable_content_type')),
				));
				$workMap[(string)Utils::getValueOrNull($w, 'works_id')] = $newId;
			}

			// 11. Trains (work required)
			foreach ($trains as $t) {
				$work = $this->remap($workMap, Utils::getValueOrNull($t, 'works_id'));
				if (is_null($work)) {
					$skipped++;
					continue;
				}
				$newId = Uuid::uuid7();
				$this->ok($this->trainsRepo->insertTrain(
					trainsId: $newId,
					worksId: $work,
					owner: $userId,
					description: (string)(Utils::getValueOrNull($t, 'description') ?? ''),
					trainNumber: (string)(Utils::getValueOrNull($t, 'train_number') ?? ''),
					maxSpeed: Utils::getValueOrNull($t, 'max_speed'),
					speedType: Utils::getValueOrNull($t, 'speed_type'),
					nominalTractiveCapacity: Utils::getValueOrNull($t, 'nominal_tractive_capacity'),
					carCount: $this->intOrNull(Utils::getValueOrNull($t, 'car_count')),
					destination: Utils::getValueOrNull($t, 'destination'),
					beginRemarks: Utils::getValueOrNull($t, 'begin_remarks'),
					afterRemarks: Utils::getValueOrNull($t, 'after_remarks'),
					remarks: Utils::getValueOrNull($t, 'remarks'),
					beforeDeparture: Utils::getValueOrNull($t, 'before_departure'),
					afterArrive: Utils::getValueOrNull($t, 'after_arrive'),
					trainInfo: Utils::getValueOrNull($t, 'train_info'),
					direction: $this->intOrNull(Utils::getValueOrNull($t, 'direction')) ?? 1,
					dayCount: $this->intOrNull(Utils::getValueOrNull($t, 'day_count')) ?? 0,
					isRideOnMoving: boolval(Utils::getValueOrNull($t, 'is_ride_on_moving')),
				));
				$trainMap[(string)Utils::getValueOrNull($t, 'trains_id')] = $newId;
			}

			// 12. Timetable rows (leaf — train + station required; track/color nullable)
			foreach ($timetableRows as $r) {
				$train = $this->remap($trainMap, Utils::getValueOrNull($r, 'trains_id'));
				$station = $this->remap($stationMap, Utils::getValueOrNull($r, 'stations_id'));
				if (is_null($train) || is_null($station)) {
					$skipped++;
					continue;
				}
				$this->ok($this->timetableRowsRepo->insertTimetableRow(
					timetableRowsId: Uuid::uuid7(),
					trainsId: $train,
					owner: $userId,
					stationsId: $station,
					stationTracksId: $this->remap($stationTrackMap, Utils::getValueOrNull($r, 'station_tracks_id')),
					colorsIdMarker: $this->remap($colorMap, Utils::getValueOrNull($r, 'colors_id_marker')),
					description: (string)(Utils::getValueOrNull($r, 'description') ?? ''),
					driveTimeMm: $this->intOrNull(Utils::getValueOrNull($r, 'drive_time_mm')),
					driveTimeSs: $this->intOrNull(Utils::getValueOrNull($r, 'drive_time_ss')),
					isOperationOnlyStop: boolval(Utils::getValueOrNull($r, 'is_operation_only_stop')),
					isPass: boolval(Utils::getValueOrNull($r, 'is_pass')),
					hasBracket: boolval(Utils::getValueOrNull($r, 'has_bracket')),
					isLastStop: boolval(Utils::getValueOrNull($r, 'is_last_stop')),
					arriveTimeHh: $this->intOrNull(Utils::getValueOrNull($r, 'arrive_time_hh')),
					arriveTimeMm: $this->intOrNull(Utils::getValueOrNull($r, 'arrive_time_mm')),
					arriveTimeSs: $this->intOrNull(Utils::getValueOrNull($r, 'arrive_time_ss')),
					departureTimeHh: $this->intOrNull(Utils::getValueOrNull($r, 'departure_time_hh')),
					departureTimeMm: $this->intOrNull(Utils::getValueOrNull($r, 'departure_time_mm')),
					departureTimeSs: $this->intOrNull(Utils::getValueOrNull($r, 'departure_time_ss')),
					runInLimit: $this->intOrNull(Utils::getValueOrNull($r, 'run_in_limit')),
					runOutLimit: $this->intOrNull(Utils::getValueOrNull($r, 'run_out_limit')),
					remarks: Utils::getValueOrNull($r, 'remarks'),
					arriveStr: Utils::getValueOrNull($r, 'arrive_str'),
					departureStr: Utils::getValueOrNull($r, 'departure_str'),
					markerText: Utils::getValueOrNull($r, 'marker_text'),
					workType: WorkAtStationType::fromOrNull(Utils::getValueOrNull($r, 'work_type')),
				));
			}

			$selectResult = $this->projectsRepo->selectProjectOne($userId, $newProjectId);
			if ($selectResult->isError) {
				$this->db->rollBack();
				return $selectResult;
			}

			$this->db->commit();
			if ($skipped > 0) {
				$this->logger->warning(
					"import: {skipped} row(s) skipped (dangling required FK to a soft-deleted parent)",
					['skipped' => $skipped],
				);
			}
			return RetValueOrError::withValue($selectResult->value, Constants::HTTP_CREATED);
		} catch (ImportInsertException $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			$this->logger->warning("import insert failed: {msg}", ['msg' => $e->getMessage()]);
			return RetValueOrError::withError($e->statusCode, $e->getMessage());
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			$this->logger->error("import failed: {exception}", ['exception' => $e]);
			return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, 'import failed');
		}
	}

	/** Throw on a failed insert so the whole import transaction rolls back. */
	private function ok(RetValueOrError $r): void
	{
		if ($r->isError) {
			throw new ImportInsertException($r->statusCode, $r->errorMsg);
		}
	}

	/**
	 * Read an array property from the parsed body, tolerating object/array and
	 * a missing/null key (→ []).
	 *
	 * @param array<string,mixed>|object $graph
	 * @return array<mixed>
	 */
	private function listOf(mixed $graph, string $key): array
	{
		$v = Utils::getValueOrNull($graph, $key);
		return is_array($v) ? array_values($v) : [];
	}

	/** old-id string → remapped new UuidInterface, or null if absent/unmapped. */
	private function remap(array $map, mixed $oldId): ?UuidInterface
	{
		if (is_null($oldId)) {
			return null;
		}
		$key = (string)$oldId;
		return $map[$key] ?? null;
	}

	private function intOrNull(mixed $v): ?int
	{
		return is_null($v) ? null : intval($v);
	}

	/** array → stdClass (for `->prop` access); object/null pass through. */
	private function toObjOrNull(mixed $v): ?object
	{
		if (is_null($v)) {
			return null;
		}
		return is_array($v) ? (object)$v : $v;
	}

	private function parseStationLonlat(mixed $item): ?StationLocationLonlat
	{
		$raw = Utils::getValueOrNull($item, 'location_lonlat');
		if (is_null($raw)) {
			return null;
		}
		$ll = new StationLocationLonlat();
		$ll->setData([
			'longitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'longitude')),
			'latitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'latitude')),
		]);
		return $ll;
	}

	private function parseStationOnLineLonlat(mixed $item): ?StationOnLineLocationLonlat
	{
		$raw = Utils::getValueOrNull($item, 'location_lonlat');
		if (is_null($raw)) {
			return null;
		}
		$ll = new StationOnLineLocationLonlat();
		$ll->setData([
			'longitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'longitude')),
			'latitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'latitude')),
		]);
		return $ll;
	}
}

/** Internal: an insert returned an error; carries the status for the response. */
final class ImportInsertException extends \RuntimeException
{
	public function __construct(public readonly int $statusCode, string $message)
	{
		parent::__construct($message);
	}
}

/** Internal: export hit the per-entity row cap. */
final class TransferTooLargeException extends \RuntimeException
{
}
