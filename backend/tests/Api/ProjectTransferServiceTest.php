<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration round-trip for the dedicated export/import endpoints
 * (ProjectTransferService). This is the real correctness gate for the
 * server-side id remap: seed a rich graph → export → import → re-export →
 * assert per-entity count equality. A remap bug (a dropped child create from a
 * dangling FK, a mis-wired parent) shrinks a count and fails here in seconds,
 * under the same strict+native-prepares harness as the rest of the suite.
 *
 * The export value is JSON-encoded then decoded to assoc arrays before import,
 * exactly mirroring the HTTP wire (BaseModel magic props are invisible to
 * property_exists, so passing raw models would not exercise the real path).
 *
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\service;

use PHPUnit\Framework\Attributes\CoversClass;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\repo\WorkGroupsRepo;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(\dev_t0r\trvis_backend\service\ProjectTransferService::class)]
class ProjectTransferServiceTest extends IntegrationTestCase
{
	private ?string $importedProjectId = null;

	private function transfer(): ProjectTransferService
	{
		return new ProjectTransferService($this->db, $this->logger);
	}

	/** Direct PDO insert of a project_lines row (LineService::create has a scalar sig). */
	private function newLine(string $name = 'L'): UuidInterface
	{
		$id = Uuid::uuid7();
		$this->db->prepare(
			"INSERT INTO project_lines (project_lines_id, projects_id, description, owner, name)"
			. " VALUES (:id, :pid, '', :owner, :name)"
		)->execute([
			':id' => $id->getBytes(),
			':pid' => $this->projectId->getBytes(),
			':owner' => $this->userId,
			':name' => $name,
		]);
		$this->register('project_lines', 'project_lines_id', (string)$id);
		return $id;
	}

	/** Seed a non-trivial graph under the fixture project; returns expected counts. */
	private function seedGraph(): array
	{
		$pid = $this->projectId;
		$uid = $this->userId;

		// Colors
		$colorSvc = new ColorsService($this->db, $this->logger);
		// color_real columns are NOT NULL in the schema, so every color must carry it.
		$cr = $colorSvc->create($pid, $uid, [
			$this->makeModel(Color::class, ['name' => 'C1', 'description' => '', 'color_8bit' => (object)['red' => 10, 'green' => 20, 'blue' => 30], 'color_real' => (object)['red' => 0.1, 'green' => 0.2, 'blue' => 0.3]]),
			$this->makeModel(Color::class, ['name' => 'C2', 'description' => '', 'color_8bit' => (object)['red' => 40, 'green' => 50, 'blue' => 60], 'color_real' => (object)['red' => 0.4, 'green' => 0.5, 'blue' => 0.6]]),
		]);
		$this->assertOk($cr, 'seed colors');
		$color1 = $cr->value[0]->colors_id;
		foreach ($cr->value as $c) {
			$this->register('colors', 'colors_id', (string)$c->colors_id);
		}

		// Stations
		$stationSvc = new StationsService($this->db, $this->logger);
		$stationIds = [];
		foreach (['S1', 'S2'] as $name) {
			$r = $stationSvc->createStation(
				projectsId: $pid,
				userId: $uid,
				name: $name,
				fullName: null,
				locationKm: 0.0,
				locationLonlat: null,
				onStationDetectRadiusM: null,
				recordType: StationRecordType::normal,
				alwaysShowHh: false,
			);
			$this->assertOk($r, "seed station $name");
			$stationIds[] = $r->value->stations_id;
			$this->register('stations', 'stations_id', (string)$r->value->stations_id);
		}

		// Station track on station 1
		$trackSvc = new StationTracksService($this->db, $this->logger);
		$tr = $trackSvc->create($stationIds[0], $uid, [
			$this->makeModel(\dev_t0r\trvis_backend\model\StationTrack::class, ['name' => 'T1', 'description' => '']),
		]);
		$this->assertOk($tr, 'seed station track');
		$this->register('station_tracks', 'station_tracks_id', (string)$tr->value[0]->station_tracks_id);

		// Line + stations-on-line
		$line1 = $this->newLine();
		$solSvc = new StationsOnLineService($this->db, $this->logger);
		$sol = $solSvc->create($line1, $uid, [
			$this->makeModel(StationOnLine::class, ['project_stations_id' => $stationIds[0], 'location_m' => 0.0, 'track_hidden_by_default' => false]),
			$this->makeModel(StationOnLine::class, ['project_stations_id' => $stationIds[1], 'location_m' => 1000.0, 'track_hidden_by_default' => false]),
		]);
		$this->assertOk($sol, 'seed stations_on_line');
		foreach ($sol->value as $s) {
			$this->register('stations_on_line', 'stations_on_line_id', (string)$s->stations_on_line_id);
		}

		// Stop pattern + rows
		$spSvc = new StopPatternsService($this->db, $this->logger);
		$spr = $spSvc->create($pid, $uid, [
			$this->makeModel(StopPattern::class, ['name' => 'SP1', 'lines_id' => $line1, 'direction' => 1]),
		]);
		$this->assertOk($spr, 'seed stop_pattern');
		$sp1 = $spr->value[0]->stop_patterns_id;
		$this->register('stop_patterns', 'stop_patterns_id', (string)$sp1);

		$rowSvc = new StopPatternRowsService($this->db, $this->logger);
		$rr = $rowSvc->create($sp1, $uid, [
			$this->makeModel(StopPatternRow::class, ['project_stations_id' => $stationIds[0], 'sort_key' => 0]),
			$this->makeModel(StopPatternRow::class, ['project_stations_id' => $stationIds[1], 'sort_key' => 1]),
		]);
		$this->assertOk($rr, 'seed stop_pattern_rows');
		foreach ($rr->value as $r) {
			$this->register('stop_pattern_rows', 'stop_pattern_rows_id', (string)$r->stop_pattern_rows_id);
		}

		// Work group → work → train → timetable rows
		$wgSvc = new WorkGroupsService($this->db, $this->logger);
		$wg = $wgSvc->createWorkGroupInProject($pid, $uid, '', 'WG1');
		$this->assertOk($wg, 'seed work_group');
		$wg1 = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wg1);

		$workSvc = new WorksService($this->db, $this->logger);
		$wk = $workSvc->create($wg1, $uid, [
			$this->makeModel(Work::class, ['name' => 'WK1', 'description' => '']),
		]);
		$this->assertOk($wk, 'seed work');
		$wk1 = $wk->value[0]->works_id;
		$this->register('works', 'works_id', (string)$wk1);

		$trainSvc = new TrainsService($this->db, $this->logger);
		$tn = $trainSvc->create($wk1, $uid, [
			$this->makeModel(Train::class, ['train_number' => '1M', 'description' => '', 'direction' => 1, 'day_count' => 0]),
		]);
		$this->assertOk($tn, 'seed train');
		$train1 = $tn->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$train1);

		$ttSvc = new TimetableRowsService($this->db, $this->logger);
		$tt = $ttSvc->create($train1, $uid, [
			$this->makeModel(TimetableRow::class, ['stations_id' => $stationIds[0], 'colors_id_marker' => $color1, 'description' => '', 'drive_time_mm' => 2, 'drive_time_ss' => 0]),
			$this->makeModel(TimetableRow::class, ['stations_id' => $stationIds[1], 'description' => '', 'drive_time_mm' => 3, 'drive_time_ss' => 0]),
		]);
		$this->assertOk($tt, 'seed timetable_rows');
		foreach ($tt->value as $r) {
			$this->register('timetable_rows', 'timetable_rows_id', (string)$r->timetable_rows_id);
		}

		return [
			'colors' => 2,
			'stations' => 2,
			'station_tracks' => 1,
			'lines' => 1,
			'stations_on_line' => 2,
			'stop_patterns' => 1,
			'stop_pattern_rows' => 2,
			'work_groups' => 1,
			'works' => 1,
			'trains' => 1,
			'timetable_rows' => 2,
		];
	}

	/** Per-entity counts from an export value (the flat ProjectGraph arrays). */
	private function countsOf(array $graph): array
	{
		$out = [];
		foreach (['colors', 'stations', 'station_tracks', 'lines', 'stations_on_line',
			'stop_patterns', 'stop_pattern_rows', 'work_groups', 'works', 'trains', 'timetable_rows'] as $k) {
			$out[$k] = count($graph[$k] ?? []);
		}
		return $out;
	}

	public function testExportImportRoundTripReproducesGraph(): void
	{
		$expected = $this->seedGraph();

		// Export the seeded project.
		$exportResult = $this->transfer()->export($this->projectId, $this->userId);
		$this->assertOk($exportResult, 'export');
		$sourceGraph = $exportResult->value;
		$this->assertSame($expected, $this->countsOf($sourceGraph), 'exported counts match seed');

		// Wire round-trip: encode→decode to assoc arrays (what the HTTP body is).
		$wire = json_decode(json_encode($sourceGraph), true);
		$this->assertIsArray($wire, 'graph serializes');

		// Import into a brand-new project.
		$importResult = $this->transfer()->import($this->userId, $wire);
		$this->assertOk($importResult, 'import');
		$newProjectId = (string)$importResult->value->projects_id;
		$this->assertNotSame((string)$this->projectId, $newProjectId, 'import creates a new project');
		$this->importedProjectId = $newProjectId;

		// The new project must be owner-visible (catches a missing admin-privilege row).
		$visible = (new \dev_t0r\trvis_backend\service\ProjectsService($this->db, $this->logger))
			->selectProjectOne(Uuid::fromString($newProjectId), $this->userId);
		$this->assertOk($visible, 'imported project owner-visible');

		// Its work groups must be owner-visible too (new-style WG delegates to project).
		$wgVisible = (new WorkGroupsRepo($this->db, $this->logger))
			->selectWorkGroupPageByProjectId(Uuid::fromString($newProjectId), $this->userId, 1, 50, null);
		$this->assertOk($wgVisible, 'imported work_groups visible');
		$this->assertCount(1, $wgVisible->value, 'imported work_group count');

		// Re-export the imported project; every count must match the source.
		$reExport = $this->transfer()->export(Uuid::fromString($newProjectId), $this->userId);
		$this->assertOk($reExport, 're-export');
		$this->assertSame(
			$this->countsOf($sourceGraph),
			$this->countsOf($reExport->value),
			'imported graph reproduces every entity count',
		);
	}

	protected function tearDown(): void
	{
		// Hard-delete the imported project's whole graph (its rows were created
		// server-side with fresh ids, so they aren't individually registered).
		if ($this->importedProjectId !== null) {
			$pid = Uuid::fromString($this->importedProjectId)->getBytes();
			$cascades = [
				"DELETE FROM timetable_rows WHERE trains_id IN (SELECT trains_id FROM trains WHERE works_id IN (SELECT works_id FROM works WHERE work_groups_id IN (SELECT work_groups_id FROM work_groups WHERE projects_id = :pid)))",
				"DELETE FROM trains WHERE works_id IN (SELECT works_id FROM works WHERE work_groups_id IN (SELECT work_groups_id FROM work_groups WHERE projects_id = :pid))",
				"DELETE FROM works WHERE work_groups_id IN (SELECT work_groups_id FROM work_groups WHERE projects_id = :pid)",
				"DELETE FROM work_groups WHERE projects_id = :pid",
				"DELETE FROM stop_pattern_rows WHERE projects_id = :pid",
				"DELETE FROM stop_patterns WHERE projects_id = :pid",
				"DELETE FROM stations_on_line WHERE projects_id = :pid",
				"DELETE FROM station_tracks WHERE stations_id IN (SELECT stations_id FROM stations WHERE projects_id = :pid)",
				"DELETE FROM project_lines WHERE projects_id = :pid",
				"DELETE FROM stations WHERE projects_id = :pid",
				"DELETE FROM colors WHERE projects_id = :pid",
				"DELETE FROM projects_privileges WHERE projects_id = :pid",
				"DELETE FROM projects WHERE projects_id = :pid",
			];
			foreach ($cascades as $sql) {
				try {
					$this->db->prepare($sql)->execute([':pid' => $pid]);
				} catch (\Throwable $e) {
					// best-effort
				}
			}
			$this->importedProjectId = null;
		}
		parent::tearDown();
	}
}
