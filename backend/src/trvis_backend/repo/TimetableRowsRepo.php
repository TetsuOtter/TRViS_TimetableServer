<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use BackedEnum;
use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TRViSJsonTimetableRow;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * timetable_rows — Train-rooted (trains_id FK).
 *
 * selectPrivilegeType resolves work_groups_id by walking
 * timetable_rows → trains → works (INNER JOIN), then delegates to
 * WorkGroupsPrivilegesRepo for project-root privilege resolution. This
 * reproduces the legacy MyRepoBase::selectPrivilegeType chain
 * (parentTableNameList=['trains','works','work_groups']) without the
 * base-class machinery. selectWorkGroupsId is exposed for the nonExistId
 * scoping that TimetableRowsService::beforeUpdate needs.
 *
 * Faithful-port quirks preserved verbatim (do NOT "fix" here — see
 * CONTRIBUTING-P3.md §11):
 *   - SELECT/fetch carry NO updated_at column (legacy SQL_SELECT_COLUMNS
 *     omits it; the model has no updated_at slot). The DB column still
 *     exists and auto-advances via ON UPDATE CURRENT_TIMESTAMP.
 *   - update() has NO column allowlist and NO explicit updated_at set —
 *     it is a literal port of MyRepoBase::update. The kvpArray is already
 *     filtered to the 22 model keys by the service's getArrayForUpdateSource.
 *   - The `colors_id_marker` field maps to the DB column `colors_id` on
 *     INSERT (correct), but legacy update() emits the SET line
 *     `colors_id_marker = :colors_id_marker` for that key — MySQL rejects
 *     it ("Unknown column 'colors_id_marker'") → caught → HTTP 500. This
 *     is a legacy bug reproduced 1:1; a fix belongs in a post-migration PR.
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in
 * the 4-exclude carve-out). Canonical precedent: TrainsRepo / WorksRepo.
 */
final class TimetableRowsRepo implements IMyRepoSelectPrivilegeType
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
		private readonly int $dumpMaxRowsPerTable = Constants::DUMP_MAX_ROWS_PER_TABLE,
	) {
	}

	private static function fetchResultToTimetableRow(mixed $d): TimetableRow
	{
		$stationTracksId = $d['station_tracks_id'];
		$colorsIdMarker = $d['colors_id_marker'];
		$result = new TimetableRow();
		$result->setData([
			'timetable_rows_id' => Uuid::fromBytes($d['timetable_rows_id']),
			'trains_id' => Uuid::fromBytes($d['trains_id']),
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'station_tracks_id' => is_null($stationTracksId) ? null : Uuid::fromBytes($stationTracksId),
			'colors_id_marker' => is_null($colorsIdMarker) ? null : Uuid::fromBytes($colorsIdMarker),
			// Resolved display names + tombstone flags (read-only; the LEFT JOINs
			// deliberately do NOT filter deleted_at so a soft-deleted station/
			// track/color still surfaces its name for a "(削除済み)" tombstone).
			'stations_name' => $d['stations_name'],
			'stations_is_deleted' => (bool)$d['stations_is_deleted'],
			'station_tracks_name' => $d['station_tracks_name'],
			'station_tracks_is_deleted' => is_null($stationTracksId) ? false : (bool)$d['station_tracks_is_deleted'],
			'colors_name' => $d['colors_name'],
			'colors_is_deleted' => is_null($colorsIdMarker) ? false : (bool)$d['colors_is_deleted'],
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),

			'drive_time_mm' => $d['drive_time_mm'],
			'drive_time_ss' => $d['drive_time_ss'],

			'is_operation_only_stop' => $d['is_operation_only_stop'],
			'is_pass' => $d['is_pass'],
			'has_bracket' => $d['has_bracket'],
			'is_last_stop' => $d['is_last_stop'],

			'arrive_time_hh' => $d['arrive_time_hh'],
			'arrive_time_mm' => $d['arrive_time_mm'],
			'arrive_time_ss' => $d['arrive_time_ss'],

			'departure_time_hh' => $d['departure_time_hh'],
			'departure_time_mm' => $d['departure_time_mm'],
			'departure_time_ss' => $d['departure_time_ss'],

			'run_in_limit' => $d['run_in_limit'],
			'run_out_limit' => $d['run_out_limit'],

			'remarks' => $d['remarks'],

			'arrive_str' => $d['arrive_str'],
			'departure_str' => $d['departure_str'],

			'marker_text' => $d['marker_text'],

			'work_type' => WorkAtStationType::fromOrNull($d['work_type']),
		]);
		return $result;
	}

	/**
	 * @return RetValueOrError<\dev_t0r\trvis_backend\model\InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"selectPrivilegeType(userId:{userId}, timetableRowsId:{timetableRowsId})",
			[
				'userId' => $userId,
				'timetableRowsId' => $id,
			],
		);

		try {
			$query = $this->db->prepare(
				'SELECT works.work_groups_id FROM timetable_rows'
				. ' INNER JOIN trains USING (trains_id)'
				. ' INNER JOIN works USING (works_id)'
				. ' WHERE timetable_rows_id = :timetable_rows_id'
				. ' AND timetable_rows.deleted_at IS NULL'
				. ' AND trains.deleted_at IS NULL'
				. ' AND works.deleted_at IS NULL'
				. ($selectForUpdate ? ' FOR UPDATE' : '')
				. ';'
			);
			$query->bindValue(':timetable_rows_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errTimetableRowNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$workGroupsId = Uuid::fromBytes($row['work_groups_id']);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				'failed to resolve work_groups_id for timetable_row ({errorCode})',
				['errorCode' => $errCode],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}

		return (new WorkGroupsPrivilegesRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}

	/**
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectWorkGroupsId(
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			"selectWorkGroupsId(timetableRowsId:{timetableRowsId})",
			['timetableRowsId' => $id],
		);

		try {
			$query = $this->db->prepare(
				'SELECT works.work_groups_id FROM timetable_rows'
				. ' INNER JOIN trains USING (trains_id)'
				. ' INNER JOIN works USING (works_id)'
				. ' WHERE timetable_rows_id = :timetable_rows_id'
				. ' AND timetable_rows.deleted_at IS NULL'
				. ' AND trains.deleted_at IS NULL'
				. ' AND works.deleted_at IS NULL;'
			);
			$query->bindValue(':timetable_rows_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->warning('selectWorkGroupsId - timetable_row not found');
				return Utils::errTimetableRowNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			return RetValueOrError::withValue(Uuid::fromBytes($row['work_groups_id']));
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<TimetableRow>
	 */
	public function selectTimetableRowOne(
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			"selectTimetableRowOne(timetableRowsId:{timetableRowsId})",
			['timetableRowsId' => $timetableRowsId],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					timetable_rows.timetable_rows_id AS timetable_rows_id,
					timetable_rows.trains_id AS trains_id,
					timetable_rows.stations_id AS stations_id,
					timetable_rows.station_tracks_id AS station_tracks_id,
					timetable_rows.colors_id AS colors_id_marker,

					stations.name AS stations_name,
					(stations.deleted_at IS NOT NULL) AS stations_is_deleted,
					station_tracks.name AS station_tracks_name,
					(station_tracks.deleted_at IS NOT NULL) AS station_tracks_is_deleted,
					colors.name AS colors_name,
					(colors.deleted_at IS NOT NULL) AS colors_is_deleted,

					timetable_rows.description AS description,
					timetable_rows.created_at AS created_at,

					timetable_rows.drive_time_mm AS drive_time_mm,
					timetable_rows.drive_time_ss AS drive_time_ss,

					timetable_rows.is_operation_only_stop AS is_operation_only_stop,
					timetable_rows.is_pass AS is_pass,
					timetable_rows.has_bracket AS has_bracket,
					timetable_rows.is_last_stop AS is_last_stop,

					timetable_rows.arrive_time_hh AS arrive_time_hh,
					timetable_rows.arrive_time_mm AS arrive_time_mm,
					timetable_rows.arrive_time_ss AS arrive_time_ss,

					timetable_rows.departure_time_hh AS departure_time_hh,
					timetable_rows.departure_time_mm AS departure_time_mm,
					timetable_rows.departure_time_ss AS departure_time_ss,

					timetable_rows.run_in_limit AS run_in_limit,
					timetable_rows.run_out_limit AS run_out_limit,

					timetable_rows.remarks AS remarks,

					timetable_rows.arrive_str AS arrive_str,
					timetable_rows.departure_str AS departure_str,

					timetable_rows.marker_text AS marker_text,

					timetable_rows.work_type AS work_type
				FROM
					timetable_rows
				-- Resolve display names for the FK targets. NOT filtered on
				-- deleted_at so a soft-deleted station/track/color still resolves
				-- its name for the editor's "(削除済み)" tombstone.
				LEFT JOIN stations
					ON stations.stations_id = timetable_rows.stations_id
				LEFT JOIN station_tracks
					ON station_tracks.station_tracks_id = timetable_rows.station_tracks_id
				LEFT JOIN colors
					ON colors.colors_id = timetable_rows.colors_id
				WHERE
					timetable_rows.timetable_rows_id = :timetable_rows_id
				AND
					timetable_rows.deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);

			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}

			$data = $query->fetch(PDO::FETCH_ASSOC);
			if (!$data) {
				$this->logger->info(
					"TimetableRow not found ({timetableRowsId})",
					['timetableRowsId' => $timetableRowsId],
				);
				return Utils::errTimetableRowNotFound();
			}

			return RetValueOrError::withValue(self::fetchResultToTimetableRow($data));
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * Bulk faithful mirror of selectTimetableRowOne: identical projection
	 * (all the explicit AS aliases) / FROM / WHERE filters and try/catch
	 * wrapping (no $userId, no privilege-JOIN — exactly like
	 * selectTimetableRowOne), only the single-id predicate becomes
	 * `timetable_rows.timetable_rows_id IN (...)`. Element order is NOT
	 * guaranteed; the caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function selectListByIds(
		array $idList,
	): RetValueOrError {
		$this->logger->debug(
			"selectListByIds count: {count}",
			['count' => count($idList)],
		);

		if (empty($idList)) {
			return RetValueOrError::withValue([]);
		}

		try {
			$placeholders = implode(',', array_map(fn ($i) => ":id_$i", array_keys($idList)));
			$query = $this->db->prepare(<<<SQL
				SELECT
					timetable_rows.timetable_rows_id AS timetable_rows_id,
					timetable_rows.trains_id AS trains_id,
					timetable_rows.stations_id AS stations_id,
					timetable_rows.station_tracks_id AS station_tracks_id,
					timetable_rows.colors_id AS colors_id_marker,

					stations.name AS stations_name,
					(stations.deleted_at IS NOT NULL) AS stations_is_deleted,
					station_tracks.name AS station_tracks_name,
					(station_tracks.deleted_at IS NOT NULL) AS station_tracks_is_deleted,
					colors.name AS colors_name,
					(colors.deleted_at IS NOT NULL) AS colors_is_deleted,

					timetable_rows.description AS description,
					timetable_rows.created_at AS created_at,

					timetable_rows.drive_time_mm AS drive_time_mm,
					timetable_rows.drive_time_ss AS drive_time_ss,

					timetable_rows.is_operation_only_stop AS is_operation_only_stop,
					timetable_rows.is_pass AS is_pass,
					timetable_rows.has_bracket AS has_bracket,
					timetable_rows.is_last_stop AS is_last_stop,

					timetable_rows.arrive_time_hh AS arrive_time_hh,
					timetable_rows.arrive_time_mm AS arrive_time_mm,
					timetable_rows.arrive_time_ss AS arrive_time_ss,

					timetable_rows.departure_time_hh AS departure_time_hh,
					timetable_rows.departure_time_mm AS departure_time_mm,
					timetable_rows.departure_time_ss AS departure_time_ss,

					timetable_rows.run_in_limit AS run_in_limit,
					timetable_rows.run_out_limit AS run_out_limit,

					timetable_rows.remarks AS remarks,

					timetable_rows.arrive_str AS arrive_str,
					timetable_rows.departure_str AS departure_str,

					timetable_rows.marker_text AS marker_text,

					timetable_rows.work_type AS work_type
				FROM
					timetable_rows
				-- See selectTimetableRowOne: resolve FK display names, NOT filtered
				-- on deleted_at so soft-deleted refs still surface their name.
				LEFT JOIN stations
					ON stations.stations_id = timetable_rows.stations_id
				LEFT JOIN station_tracks
					ON station_tracks.station_tracks_id = timetable_rows.station_tracks_id
				LEFT JOIN colors
					ON colors.colors_id = timetable_rows.colors_id
				WHERE
					timetable_rows.timetable_rows_id IN ($placeholders)
				AND
					timetable_rows.deleted_at IS NULL
				;
				SQL
			);
			foreach ($idList as $i => $id) {
				$query->bindValue(":id_$i", $id->getBytes(), PDO::PARAM_STR);
			}

			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}

			$timetableRows = array_map(
				fn ($data) => self::fetchResultToTimetableRow($data),
				$query->fetchAll(PDO::FETCH_ASSOC),
			);
			return RetValueOrError::withValue($timetableRows);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	private static function getSelectPageSql(
		bool $hasTopId,
		bool $countOnly,
	): string {
		$WHERE_TOP_ID = $hasTopId ? ' AND timetable_rows.timetable_rows_id <= :top_id ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			timetable_rows.timetable_rows_id AS timetable_rows_id,
			timetable_rows.trains_id AS trains_id,
			timetable_rows.stations_id AS stations_id,
			timetable_rows.station_tracks_id AS station_tracks_id,
			timetable_rows.colors_id AS colors_id_marker,

			stations.name AS stations_name,
			(stations.deleted_at IS NOT NULL) AS stations_is_deleted,
			station_tracks.name AS station_tracks_name,
			(station_tracks.deleted_at IS NOT NULL) AS station_tracks_is_deleted,
			colors.name AS colors_name,
			(colors.deleted_at IS NOT NULL) AS colors_is_deleted,

			timetable_rows.description AS description,
			timetable_rows.created_at AS created_at,

			timetable_rows.drive_time_mm AS drive_time_mm,
			timetable_rows.drive_time_ss AS drive_time_ss,

			timetable_rows.is_operation_only_stop AS is_operation_only_stop,
			timetable_rows.is_pass AS is_pass,
			timetable_rows.has_bracket AS has_bracket,
			timetable_rows.is_last_stop AS is_last_stop,

			timetable_rows.arrive_time_hh AS arrive_time_hh,
			timetable_rows.arrive_time_mm AS arrive_time_mm,
			timetable_rows.arrive_time_ss AS arrive_time_ss,

			timetable_rows.departure_time_hh AS departure_time_hh,
			timetable_rows.departure_time_mm AS departure_time_mm,
			timetable_rows.departure_time_ss AS departure_time_ss,

			timetable_rows.run_in_limit AS run_in_limit,
			timetable_rows.run_out_limit AS run_out_limit,

			timetable_rows.remarks AS remarks,

			timetable_rows.arrive_str AS arrive_str,
			timetable_rows.departure_str AS departure_str,

			timetable_rows.marker_text AS marker_text,

			timetable_rows.work_type AS work_type

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				timetable_rows_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		// Resolve display names for the FK targets (same as selectTimetableRowOne).
		// NOT filtered on deleted_at so a soft-deleted station/track/color still
		// resolves its name for the editor's "(削除済み)" tombstone. Omitted on the
		// COUNT(*) path where the embedded columns are not selected.
		$JOINS = $countOnly ? '' : <<<SQL

			LEFT JOIN stations
				ON stations.stations_id = timetable_rows.stations_id
			LEFT JOIN station_tracks
				ON station_tracks.station_tracks_id = timetable_rows.station_tracks_id
			LEFT JOIN colors
				ON colors.colors_id = timetable_rows.colors_id
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				timetable_rows
			$JOINS
			WHERE
				timetable_rows.trains_id = :trains_id
			AND
				$WHERE_TOP_ID
				timetable_rows.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function selectTimetableRowPage(
		UuidInterface $trainsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectTimetableRowPage(trainsId:{trainsId}, page:{page}, perPage:{perPage})",
			[
				'trainsId' => $trainsId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
			],
		);

		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageSql($hasTopId, false));
		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
		$query->bindValue(':offset', ($pageFrom1 - 1) * $perPage, PDO::PARAM_INT);

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => implode('\n\t', $query->errorInfo()),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}

		$timetableRows = array_map(
			fn ($data) => self::fetchResultToTimetableRow($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($timetableRows);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectTimetableRowPageTotalCount(
		UuidInterface $trainsId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageSql($hasTopId, true));
		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => implode('\n\t', $query->errorInfo()),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}

		$row = $query->fetch(PDO::FETCH_ASSOC);
		$totalCount = $row ? (int)$row['count'] : 0;
		return RetValueOrError::withValue($totalCount);
	}

	/**
	 * Single-row INSERT. The `colors_id_marker` model field is written to the
	 * DB column `colors_id` (faithful 1:1 with legacy _genInsertValuesQuerySegment).
	 *
	 * @return RetValueOrError<null>
	 */
	public function insertTimetableRow(
		UuidInterface $timetableRowsId,
		UuidInterface $trainsId,
		string $owner,
		UuidInterface $stationsId,
		?UuidInterface $stationTracksId,
		?UuidInterface $colorsIdMarker,
		string $description,
		?int $driveTimeMm,
		?int $driveTimeSs,
		bool $isOperationOnlyStop,
		bool $isPass,
		bool $hasBracket,
		bool $isLastStop,
		?int $arriveTimeHh,
		?int $arriveTimeMm,
		?int $arriveTimeSs,
		?int $departureTimeHh,
		?int $departureTimeMm,
		?int $departureTimeSs,
		?int $runInLimit,
		?int $runOutLimit,
		?string $remarks,
		?string $arriveStr,
		?string $departureStr,
		?string $markerText,
		?WorkAtStationType $workType,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO timetable_rows (
				timetable_rows_id,
				trains_id,
				stations_id,
				station_tracks_id,
				colors_id,
				description,
				owner,

				drive_time_mm,
				drive_time_ss,

				is_operation_only_stop,
				is_pass,
				has_bracket,
				is_last_stop,

				arrive_time_hh,
				arrive_time_mm,
				arrive_time_ss,

				departure_time_hh,
				departure_time_mm,
				departure_time_ss,

				run_in_limit,
				run_out_limit,

				remarks,

				arrive_str,
				departure_str,

				marker_text,

				work_type
			) VALUES (
				:timetable_rows_id,
				:trains_id,
				:stations_id,
				:station_tracks_id,
				:colors_id,
				:description,
				:owner,

				:drive_time_mm,
				:drive_time_ss,

				:is_operation_only_stop,
				:is_pass,
				:has_bracket,
				:is_last_stop,

				:arrive_time_hh,
				:arrive_time_mm,
				:arrive_time_ss,

				:departure_time_hh,
				:departure_time_mm,
				:departure_time_ss,

				:run_in_limit,
				:run_out_limit,

				:remarks,

				:arrive_str,
				:departure_str,

				:marker_text,

				:work_type
			);
			SQL
		);

		$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':station_tracks_id', $stationTracksId?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':colors_id', $colorsIdMarker?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);

		$query->bindValue(':drive_time_mm', $driveTimeMm, PDO::PARAM_INT);
		$query->bindValue(':drive_time_ss', $driveTimeSs, PDO::PARAM_INT);

		$query->bindValue(':is_operation_only_stop', $isOperationOnlyStop, PDO::PARAM_BOOL);
		$query->bindValue(':is_pass', $isPass, PDO::PARAM_BOOL);
		$query->bindValue(':has_bracket', $hasBracket, PDO::PARAM_BOOL);
		$query->bindValue(':is_last_stop', $isLastStop, PDO::PARAM_BOOL);

		$query->bindValue(':arrive_time_hh', $arriveTimeHh, PDO::PARAM_INT);
		$query->bindValue(':arrive_time_mm', $arriveTimeMm, PDO::PARAM_INT);
		$query->bindValue(':arrive_time_ss', $arriveTimeSs, PDO::PARAM_INT);

		$query->bindValue(':departure_time_hh', $departureTimeHh, PDO::PARAM_INT);
		$query->bindValue(':departure_time_mm', $departureTimeMm, PDO::PARAM_INT);
		$query->bindValue(':departure_time_ss', $departureTimeSs, PDO::PARAM_INT);

		$query->bindValue(':run_in_limit', $runInLimit, PDO::PARAM_INT);
		$query->bindValue(':run_out_limit', $runOutLimit, PDO::PARAM_INT);

		$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);

		$query->bindValue(':arrive_str', $arriveStr, PDO::PARAM_STR);
		$query->bindValue(':departure_str', $departureStr, PDO::PARAM_STR);

		$query->bindValue(':marker_text', $markerText, PDO::PARAM_STR);

		$query->bindValue(':work_type', $workType?->value, PDO::PARAM_STR);

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "TimetableRow");
	}

	/**
	 * Literal port of MyRepoBase::update — NO column allowlist, NO explicit
	 * updated_at (DB ON UPDATE CURRENT_TIMESTAMP handles it). The kvpArray is
	 * already filtered to the model keys by the service's
	 * Utils::getArrayForUpdateSource, so iterating array_keys() directly is
	 * safe for every key EXCEPT colors_id_marker.
	 *
	 * TODO(P3-faithful-quirk): the SET line for `colors_id_marker` is emitted
	 * as `colors_id_marker = :colors_id_marker`, but the DB column is
	 * `colors_id`. MySQL rejects the unknown column → PDOException → HTTP 500.
	 * This is a legacy bug (MyRepoBase + TimetableRowsRepo::_keyToUpdateQuerySetLine
	 * whose two branches are identical) reproduced 1:1 on purpose. The fix
	 * (map colors_id_marker → colors_id on UPDATE too) belongs in a separate
	 * post-migration PR — see CONTRIBUTING-P3.md §11.
	 *
	 * @param array<string, mixed> $kvpArray
	 * @return RetValueOrError<null>
	 */
	public function update(
		UuidInterface $timetableRowsId,
		array $kvpArray,
	): RetValueOrError {
		if (count($kvpArray) === 0) {
			return RetValueOrError::withValue(null);
		}

		$this->logger->info(
			"update({timetableRowsId})",
			['timetableRowsId' => $timetableRowsId],
		);

		$setClause = implode(
			', ',
			array_map(
				fn ($key) => "{$key} = :{$key}",
				array_keys($kvpArray),
			),
		);

		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE timetable_rows SET
					$setClause
				WHERE
					timetable_rows_id = :timetable_rows_id
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);
			foreach ($kvpArray as $key => $value) {
				$newValue = $value;

				$paramType = PDO::PARAM_STR;
				if (is_null($newValue)) {
					$paramType = PDO::PARAM_NULL;
				} elseif ($newValue instanceof UuidInterface) {
					$newValue = $value->getBytes();
				} elseif ($newValue instanceof DateTimeInterface) {
					$newValue = Utils::utcDateStrOrNull($newValue);
				} elseif ($newValue instanceof BackedEnum) {
					$newValue = $newValue->value;
					$paramType = PDO::PARAM_INT;
				} elseif (is_int($newValue)) {
					$paramType = PDO::PARAM_INT;
				} elseif (is_bool($newValue)) {
					$paramType = PDO::PARAM_BOOL;
				}

				$query->bindValue(":{$key}", $newValue, $paramType);
			}

			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"TimetableRow not found ({timetableRowsId})",
						['timetableRowsId' => $timetableRowsId],
					);
					return Utils::errTimetableRowNotFound();
				}
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);

		return RetValueOrError::withError(
			Constants::HTTP_INTERNAL_SERVER_ERROR,
			"Failed to execute SQL - " . $errCode,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteTimetableRow(
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->info(
			"deleteTimetableRow({timetableRowsId})",
			['timetableRowsId' => $timetableRowsId],
		);
		$query = $this->db->prepare(<<<SQL
			UPDATE
				timetable_rows
			SET
				deleted_at = CURRENT_TIMESTAMP()
			WHERE
				timetable_rows_id = :timetable_rows_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"TimetableRow not found ({timetableRowsId})",
					['timetableRowsId' => $timetableRowsId],
				);
				return Utils::errTimetableRowNotFound();
			}
			return RetValueOrError::withValue(null);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * @param array<UuidInterface> $parentIdList
	 * @param array<string, array<TRViSJsonTimetableRow>> $dst
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function dump(
		array $parentIdList,
		array &$dst,
	): RetValueOrError {
		$this->logger->debug(
			'Dumping timetable rows... {parentIdList}',
			[
				'parentIdList' => $parentIdList,
			],
		);

		$parentIdCount = count($parentIdList);
		if ($parentIdCount === 0) {
			// 親IDが空のとき IN () となり MySQL 構文エラー(42000)になるため、
			// クエリを組み立てず空結果を返す（親が無ければ子も無い）。
			return RetValueOrError::withValue([]);
		}
		$parentIdListPlaceholder = implode(', ', array_fill(0, $parentIdCount, '?'));
		try {
			// station_tracksとのJOINは、本当はstations_idも条件として加えるべきである。
			// しかし、実装上の都合で同じProjectの他の駅に属するstation_tracksも登録できてしまう。
			// そのため、stations_idでの絞り込みは行わない。
			$dumpLimit = $this->dumpMaxRowsPerTable + 1;
			$query = $this->db->prepare(<<<SQL
				SELECT
					HEX(timetable_rows.trains_id) AS parent_id,
					timetable_rows.timetable_rows_id AS timetable_rows_id,
					stations.name AS StationName,
					stations.location_km * 1000 AS Location_m,
					ST_X(stations.location_lonlat) AS Longitude_deg,
					ST_Y(stations.location_lonlat) AS Latitude_deg,
					stations.on_station_detect_radius_m AS OnStationDetectRadius_m,
					COALESCE(stations.full_name, stations.name) AS FullName,
					stations.record_type AS RecordType,
					station_tracks.name AS TrackName,

					timetable_rows.drive_time_mm AS DriveTime_MM,
					timetable_rows.drive_time_ss AS DriveTime_SS,

					timetable_rows.is_operation_only_stop AS IsOperationOnlyStop,
					timetable_rows.is_pass AS IsPass,
					timetable_rows.has_bracket AS HasBracket,
					timetable_rows.is_last_stop AS IsLastStop,

					IF(
							timetable_rows.arrive_time_hh IS NULL
						AND
							timetable_rows.arrive_time_mm IS NULL
						AND
							timetable_rows.arrive_time_ss IS NULL
						,
						timetable_rows.arrive_str,
						CONCAT(
							IFNULL(timetable_rows.arrive_time_hh, ''),
							':',
							IFNULL(timetable_rows.arrive_time_mm, ''),
							':',
							IFNULL(timetable_rows.arrive_time_ss, '')
						)
					) AS Arrive,
					IF(
							timetable_rows.departure_time_hh IS NULL
						AND
							timetable_rows.departure_time_mm IS NULL
						AND
							timetable_rows.departure_time_ss IS NULL
						,
						timetable_rows.departure_str,
						CONCAT(
							IFNULL(timetable_rows.departure_time_hh, ''),
							':',
							IFNULL(timetable_rows.departure_time_mm, ''),
							':',
							IFNULL(timetable_rows.departure_time_ss, '')
						)
					) AS Departure,

					timetable_rows.run_in_limit AS RunInLimit,
					timetable_rows.run_out_limit AS RunOutLimit,

					timetable_rows.remarks AS Remarks,

					IF(
						timetable_rows.colors_id IS NULL,
						NULL,
						CONCAT(
							LPAD(HEX(colors.red_8bit), 2, '0'),
							LPAD(HEX(colors.green_8bit), 2, '0'),
							LPAD(HEX(colors.blue_8bit), 2, '0')
						)
					) AS MarkerColor,
					timetable_rows.marker_text AS MarkerText,

					timetable_rows.work_type AS WorkType
				FROM
					timetable_rows
				JOIN
					stations
				USING
					(stations_id)
				LEFT JOIN
					station_tracks
				USING
					(station_tracks_id)
				LEFT JOIN
					colors
				USING
					(colors_id)
				WHERE
					trains_id IN ($parentIdListPlaceholder)
				AND
					timetable_rows.deleted_at IS NULL
				AND
					stations.deleted_at IS NULL
				ORDER BY
					Location_m ASC
				LIMIT $dumpLimit
				SQL
			);
			for ($i = 0; $i < $parentIdCount; ++$i) {
				$query->bindValue($i + 1, $parentIdList[$i]->getBytes(), PDO::PARAM_STR);
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'selected timetable {rowCount} rows.',
				[
					'rowCount' => $rowCount,
				],
			);
			if ($rowCount === 0) {
				return RetValueOrError::withValue([]);
			}
			if ($rowCount > $this->dumpMaxRowsPerTable) {
				$this->logger->error(
					'TimetableRowsRepo::dump() cap exceeded: {rowCount} rows > {cap}.',
					[
						'rowCount' => $rowCount,
						'cap' => $this->dumpMaxRowsPerTable,
					],
				);
				return RetValueOrError::withError(
					Constants::HTTP_PAYLOAD_TOO_LARGE,
					'Dump aborted: timetable_rows row count exceeds the per-table limit ('
						. $this->dumpMaxRowsPerTable . '). Reduce the work group size.',
				);
			}

			$rowCountPerParent = [];
			$timetableRowsIdList = [];
			$timetableRowsIdListIndex = 0;
			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$parentId = $row['parent_id'];
				if (!array_key_exists($parentId, $rowCountPerParent)) {
					$rowCountPerParent[$parentId] = 0;
				}
				$dst[$parentId][$rowCountPerParent[$parentId]++] = self::fetchResultRowToTrvisJsonData($row);
				$timetableRowsIdList[$timetableRowsIdListIndex++] = Uuid::fromBytes($row['timetable_rows_id']);
			}

			return RetValueOrError::withValue($timetableRowsIdList);
		} catch (\PDOException $e) {
			$this->logger->error(
				'Failed to dump timetable rows. {exception}',
				[
					'exception' => $e,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'Failed to execute SQL - ' . $e->getCode(),
			);
		}
	}

	/**
	 * @param array<string, mixed> $kvpList
	 */
	private static function fetchResultRowToTrvisJsonData(
		array $kvpList,
	): TRViSJsonTimetableRow {
		$d = new TRViSJsonTimetableRow();
		$d->setData([
			'StationName' => $kvpList['StationName'],
			'Location_m' => $kvpList['Location_m'],
			'Longitude_deg' => Utils::floatvalOrNull($kvpList['Longitude_deg']),
			'Latitude_deg' => Utils::floatvalOrNull($kvpList['Latitude_deg']),
			'OnStationDetectRadius_m' => Utils::floatvalOrNull($kvpList['OnStationDetectRadius_m']),
			'FullName' => $kvpList['FullName'],
			'RecordType' => $kvpList['RecordType'],
			'TrackName' => $kvpList['TrackName'],

			'DriveTime_MM' => $kvpList['DriveTime_MM'],
			'DriveTime_SS' => $kvpList['DriveTime_SS'],

			'IsOperationOnlyStop' => $kvpList['IsOperationOnlyStop'],
			'IsPass' => $kvpList['IsPass'],
			'HasBracket' => $kvpList['HasBracket'],
			'IsLastStop' => $kvpList['IsLastStop'],

			'Arrive' => $kvpList['Arrive'],
			'Departure' => $kvpList['Departure'],

			'RunInLimit' => $kvpList['RunInLimit'],
			'RunOutLimit' => $kvpList['RunOutLimit'],

			'Remarks' => $kvpList['Remarks'],

			'MarkerColor' => $kvpList['MarkerColor'],
			'MarkerText' => $kvpList['MarkerText'],

			'WorkType' => $kvpList['WorkType'],
		]);
		return $d;
	}
}
