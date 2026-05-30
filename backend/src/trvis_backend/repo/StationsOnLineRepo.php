<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stations_on_line — Line-rooted (parentId = lineId = project_lines_id).
 *
 * DB note: `project_lines_id` (DB) ↔ `lines_id` (API).
 * SELECT aliases project_lines_id AS lines_id.
 * projects_id is derived from the parent Line via subquery on INSERT.
 * description column is absent from the API schema (DB default '').
 *
 * Privilege root: Line (indirect) — selectPrivilegeType resolves the owning
 * project_lines_id from stations_on_line, then delegates to the landed LineRepo.
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in the
 * 4-exclude carve-out). Canonical precedent: ProjectsRepo::fetchResultToProject.
 */
final class StationsOnLineRepo implements IMyRepoSelectPrivilegeType
{
	public function __construct(
		private PDO $db,
		private LoggerInterface $logger,
	) {
	}

	private static function fetchResultToStationOnLine(mixed $data): StationOnLine
	{
		$lonlat = null;
		if (!is_null($data['location_lon']) && !is_null($data['location_lat'])) {
			$lonlat = new StationOnLineLocationLonlat();
			$lonlat->setData([
				'longitude' => floatval($data['location_lon']),
				'latitude' => floatval($data['location_lat']),
			]);
		}
		$station = new StationOnLine();
		$station->setData([
			'stations_on_line_id' => Uuid::fromBytes($data['stations_on_line_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'lines_id' => Uuid::fromBytes($data['lines_id']),
			'project_stations_id' => Uuid::fromBytes($data['project_stations_id']),
			// Resolved display name + tombstone flag for the referenced station;
			// the LEFT JOIN does NOT filter deleted_at so a soft-deleted station
			// still surfaces its name for the line editor's "(削除済み)" tombstone.
			'project_stations_name' => $data['project_stations_name'],
			'project_stations_is_deleted' => (bool)$data['project_stations_is_deleted'],
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'location_m' => floatval($data['location_m']),
			'location_lonlat' => $lonlat,
			'track_hidden_by_default' => boolval($data['track_hidden_by_default']),
		]);
		return $station;
	}

	/**
	 * Resolve the maximum privilege_type for $userId on the line that owns the
	 * given StationOnLine. Implements IMyRepoSelectPrivilegeType.
	 *
	 * Resolution: look up project_lines_id from stations_on_line, then delegate
	 * to the landed LineRepo::selectPrivilegeType (Line-rooted, which joins
	 * project_lines → projects_privileges internally).
	 *
	 * Note: the landed LineRepo does not accept a $selectForUpdate parameter;
	 * the $selectForUpdate arg on this interface method is accepted and silently
	 * ignored when delegating.
	 *
	 * @return RetValueOrError<InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"selectPrivilegeType(stationOnLineId:{id}, userId:{userId}, includeAnonymous:{includeAnonymous})",
			['id' => $id, 'userId' => $userId, 'includeAnonymous' => $includeAnonymous],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					project_lines_id
				FROM
					stations_on_line
				WHERE
					stations_on_line_id = :id
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$row = $query->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				$this->logger->warning(
					"selectPrivilegeType - StationOnLine not found (id:{id})",
					['id' => $id],
				);
				return Utils::errStationOnLineNotFound();
			}
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $ex->getMessage()],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}

		$linesId = Uuid::fromBytes($row['project_lines_id']);
		// Line-rooted: delegate to the landed LineRepo (which joins project_lines
		// → projects_privileges internally). selectForUpdate is not supported by
		// LineRepo and is intentionally dropped here.
		return (new \dev_t0r\trvis_backend\repo\LineRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $linesId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
		);
	}

	/**
	 * @return RetValueOrError<StationOnLine>
	 */
	public function selectStationOnLineOne(
		string $userId,
		UuidInterface $stationOnLineId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne stationOnLineId: {id}",
			['id' => $stationOnLineId],
		);

		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$query = $this->db->prepare(<<<SQL
			SELECT
				stations_on_line.stations_on_line_id,
				stations_on_line.projects_id,
				stations_on_line.project_lines_id AS lines_id,
				stations_on_line.project_stations_id,
				ref_station.name AS project_stations_name,
				(ref_station.deleted_at IS NOT NULL) AS project_stations_is_deleted,
				stations_on_line.created_at,
				stations_on_line.location_m,
				ST_X(stations_on_line.location_lonlat) AS location_lon,
				ST_Y(stations_on_line.location_lonlat) AS location_lat,
				stations_on_line.track_hidden_by_default
			FROM
				stations_on_line
			-- Resolve the referenced station's display name, NOT filtered on
			-- deleted_at so a soft-deleted station still tombstones in the editor.
			LEFT JOIN
				stations AS ref_station
			ON
				ref_station.stations_id = stations_on_line.project_stations_id
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				AND
					$WHERE_PRIVILEGE_TYPE
				GROUP BY
					projects_id
				) AS projects_privileges
			ON
				projects_privileges.projects_id = stations_on_line.projects_id
			WHERE
				stations_on_line.stations_on_line_id = :stations_on_line_id
			AND
				stations_on_line.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':stations_on_line_id', $stationOnLineId->getBytes(), PDO::PARAM_STR);

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
				"StationOnLine not found ({id})",
				['id' => $stationOnLineId],
			);
			return Utils::errStationOnLineNotFound();
		}

		return RetValueOrError::withValue(self::fetchResultToStationOnLine($data));
	}

	/**
	 * Bulk faithful mirror of selectStationOnLineOne: identical projection
	 * (including the `project_lines_id AS lines_id` alias) / FROM /
	 * privilege-JOIN / WHERE filters, only the single-id predicate becomes
	 * `stations_on_line.stations_on_line_id IN (...)`. Element order is NOT
	 * guaranteed; the caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<StationOnLine>>
	 */
	public function selectListByIds(
		string $userId,
		array $idList,
	): RetValueOrError {
		$this->logger->debug(
			"selectListByIds count: {count}",
			['count' => count($idList)],
		);

		if (empty($idList)) {
			return RetValueOrError::withValue([]);
		}

		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$placeholders = implode(',', array_map(fn ($i) => ":id_$i", array_keys($idList)));
		$query = $this->db->prepare(<<<SQL
			SELECT
				stations_on_line.stations_on_line_id,
				stations_on_line.projects_id,
				stations_on_line.project_lines_id AS lines_id,
				stations_on_line.project_stations_id,
				ref_station.name AS project_stations_name,
				(ref_station.deleted_at IS NOT NULL) AS project_stations_is_deleted,
				stations_on_line.created_at,
				stations_on_line.location_m,
				ST_X(stations_on_line.location_lonlat) AS location_lon,
				ST_Y(stations_on_line.location_lonlat) AS location_lat,
				stations_on_line.track_hidden_by_default
			FROM
				stations_on_line
			-- Resolve the referenced station's display name, NOT filtered on
			-- deleted_at so a soft-deleted station still tombstones in the editor.
			LEFT JOIN
				stations AS ref_station
			ON
				ref_station.stations_id = stations_on_line.project_stations_id
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				AND
					$WHERE_PRIVILEGE_TYPE
				GROUP BY
					projects_id
				) AS projects_privileges
			ON
				projects_privileges.projects_id = stations_on_line.projects_id
			WHERE
				stations_on_line.stations_on_line_id IN ($placeholders)
			AND
				stations_on_line.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
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

		$stationsOnLine = array_map(
			fn ($data) => self::fetchResultToStationOnLine($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stationsOnLine);
	}

	private static function getSelectPageQuery(
		string $userId,
		bool $hasTopId,
		bool $countOnly,
	): string {
		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$WHERE_TOP_ID = $hasTopId
			? ' stations_on_line.stations_on_line_id <= :top_id AND '
			: ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			stations_on_line.stations_on_line_id,
			stations_on_line.projects_id,
			stations_on_line.project_lines_id AS lines_id,
			stations_on_line.project_stations_id,
			ref_station.name AS project_stations_name,
			(ref_station.deleted_at IS NOT NULL) AS project_stations_is_deleted,
			stations_on_line.created_at,
			stations_on_line.location_m,
			ST_X(stations_on_line.location_lonlat) AS location_lon,
			ST_Y(stations_on_line.location_lonlat) AS location_lat,
			stations_on_line.track_hidden_by_default
			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				stations_on_line.stations_on_line_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				stations_on_line
			-- See selectStationOnLineOne: resolve referenced station name, NOT
			-- filtered on deleted_at so soft-deleted stations still tombstone.
			LEFT JOIN
				stations AS ref_station
			ON
				ref_station.stations_id = stations_on_line.project_stations_id
			JOIN
				project_lines AS pl
			ON
				pl.project_lines_id = stations_on_line.project_lines_id
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					$WHERE_PRIVILEGE_TYPE
				AND
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				GROUP BY
					projects_id
				) AS projects_privileges
			ON
				projects_privileges.projects_id = pl.projects_id
			WHERE
				stations_on_line.project_lines_id = :lines_id
			AND
				$WHERE_TOP_ID
				stations_on_line.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<StationOnLine>>
	 */
	public function selectStationOnLinePage(
		UuidInterface $linesId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(linesId:{linesId}, userId:{userId}, page:{page})",
			[
				'linesId' => $linesId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':lines_id', $linesId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
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

		$stations = array_map(
			fn ($data) => self::fetchResultToStationOnLine($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stations);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectStationOnLinePageTotalCount(
		UuidInterface $linesId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':lines_id', $linesId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);

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
	 * @return RetValueOrError<null>
	 */
	public function insertStationOnLine(
		UuidInterface $stationOnLineId,
		UuidInterface $linesId,
		string $owner,
		UuidInterface $projectStationsId,
		float $locationM,
		?StationOnLineLocationLonlat $locationLonlat,
		bool $trackHiddenByDefault,
	): RetValueOrError {
		$lonlatStr = null;
		if (!is_null($locationLonlat)) {
			$lonlatStr = "POINT({$locationLonlat->longitude} {$locationLonlat->latitude})";
		}

		$query = $this->db->prepare(<<<SQL
			INSERT INTO stations_on_line (
				stations_on_line_id,
				projects_id,
				project_lines_id,
				project_stations_id,
				owner,
				location_m,
				location_lonlat,
				track_hidden_by_default
			) VALUES (
				:stations_on_line_id,
				(SELECT pl.projects_id FROM project_lines pl WHERE pl.project_lines_id = :lines_id_for_projects),
				:lines_id,
				:project_stations_id,
				:owner,
				:location_m,
				ST_PointFromText(:location_lonlat),
				:track_hidden_by_default
			);
			SQL
		);
		$query->bindValue(':stations_on_line_id', $stationOnLineId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':lines_id', $linesId->getBytes(), PDO::PARAM_STR);
		// Native prepares (EMULATE_PREPARES=false) forbid reusing a named
		// placeholder; the projects_id subquery binds the same value separately.
		$query->bindValue(':lines_id_for_projects', $linesId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':project_stations_id', $projectStationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':location_m', $locationM, PDO::PARAM_STR);
		$query->bindValue(':location_lonlat', $lonlatStr, PDO::PARAM_STR);
		$query->bindValue(':track_hidden_by_default', $trackHiddenByDefault, PDO::PARAM_BOOL);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "StationOnLine");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateStationOnLine(
		UuidInterface $stationOnLineId,
		?float $locationM,
		?StationOnLineLocationLonlat $locationLonlat,
		bool $hasLocationLonlat,
		?bool $trackHiddenByDefault,
	): RetValueOrError {
		$hasLocationM = !is_null($locationM);
		$hasTrackHiddenByDefault = !is_null($trackHiddenByDefault);

		$this->logger->info(
			"updateStationOnLine({id})",
			['id' => $stationOnLineId],
		);

		if (!$hasLocationM && !$hasLocationLonlat && !$hasTrackHiddenByDefault) {
			return RetValueOrError::withValue(null);
		}

		$setClauses = [];
		if ($hasLocationM) {
			$setClauses[] = 'location_m = :location_m';
		}
		if ($hasLocationLonlat) {
			$setClauses[] = 'location_lonlat = ST_PointFromText(:location_lonlat)';
		}
		if ($hasTrackHiddenByDefault) {
			$setClauses[] = 'track_hidden_by_default = :track_hidden_by_default';
		}

		$setClauseStr = implode(', ', $setClauses);
		$query = $this->db->prepare(<<<SQL
			UPDATE stations_on_line SET
				$setClauseStr
			WHERE
				stations_on_line_id = :stations_on_line_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stations_on_line_id', $stationOnLineId->getBytes(), PDO::PARAM_STR);
		if ($hasLocationM) {
			$query->bindValue(':location_m', $locationM, PDO::PARAM_STR);
		}
		if ($hasLocationLonlat) {
			$lonlatStr = is_null($locationLonlat)
				? null
				: "POINT({$locationLonlat->longitude} {$locationLonlat->latitude})";
			$query->bindValue(':location_lonlat', $lonlatStr, PDO::PARAM_STR);
		}
		if ($hasTrackHiddenByDefault) {
			$query->bindValue(':track_hidden_by_default', $trackHiddenByDefault, PDO::PARAM_BOOL);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"StationOnLine not found ({id})",
						['id' => $stationOnLineId],
					);
					return Utils::errStationOnLineNotFound();
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
	public function deleteStationOnLine(
		UuidInterface $stationOnLineId,
	): RetValueOrError {
		$this->logger->info(
			"deleteStationOnLine({id})",
			['id' => $stationOnLineId],
		);

		$query = $this->db->prepare(<<<SQL
			UPDATE
				stations_on_line
			SET
				deleted_at = CURRENT_TIMESTAMP()
			WHERE
				stations_on_line_id = :stations_on_line_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stations_on_line_id', $stationOnLineId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info(
					"StationOnLine not found ({id})",
					['id' => $stationOnLineId],
				);
				return Utils::errStationOnLineNotFound();
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
}
