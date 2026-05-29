<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Project共通の駅 (stations)。Project直下。
 * (旧 work-group 配下 `stations` は廃止し、旧 `project_stations` をこのテーブルに統合。)
 * description列はDBにあるがAPIに無いのでINSERTから除外 (DB default '')。
 * Privilege root: projects_privileges JOIN on stations.projects_id.
 *
 * `location_km` / `record_type` は dump (TRViS-JSON) が消費するため保持。
 * 未設定時の DB default は 0 / 0 (= location_km 0 / record_type normal)。
 */
final class StationsRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private static function fetchResultToStation(mixed $data): Station
	{
		$lonlat = null;
		if (!is_null($data['location_lon']) && !is_null($data['location_lat'])) {
			$lonlat = new StationLocationLonlat();
			$lonlat->setData([
				'longitude' => floatval($data['location_lon']),
				'latitude' => floatval($data['location_lat']),
			]);
		}
		$station = new Station();
		$station->setData([
			'stations_id' => Uuid::fromBytes($data['stations_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'name' => $data['name'],
			'full_name' => $data['full_name'],
			'location_km' => floatval($data['location_km']),
			'location_lonlat' => $lonlat,
			'on_station_detect_radius_m' => is_null($data['on_station_detect_radius_m'])
				? null
				: floatval($data['on_station_detect_radius_m']),
			'record_type' => StationRecordType::fromOrNull((int)$data['record_type']),
			'always_show_hh' => boolval($data['always_show_hh']),
		]);
		return $station;
	}

	/**
	 * Privilege anchor for Station and its child station_tracks: resolve the
	 * station's projects_id, then delegate to ProjectsPrivilegesRepo.
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
			"selectPrivilegeType(stationsId:{id}, userId:{userId})",
			['id' => $id, 'userId' => $userId],
		);

		try {
			$query = $this->db->prepare(
				'SELECT projects_id FROM stations WHERE stations_id = :stationsId AND deleted_at IS NULL'
				. ($selectForUpdate ? ' FOR UPDATE' : '')
				. ';'
			);
			$query->bindValue(':stationsId', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errStationNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$projectsId = Uuid::fromBytes($row['projects_id']);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to resolve projects_id for station ({errorCode})",
				["errorCode" => $errCode],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}

		return (new ProjectsPrivilegesRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}

	/**
	 * @return RetValueOrError<Station>
	 */
	public function selectStationOne(
		string $userId,
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne stationsId: {id}",
			['id' => $stationsId],
		);

		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$query = $this->db->prepare(<<<SQL
			SELECT
				stations.stations_id,
				stations.projects_id,
				stations.created_at,
				stations.name,
				stations.full_name,
				stations.location_km,
				ST_X(stations.location_lonlat) AS location_lon,
				ST_Y(stations.location_lonlat) AS location_lat,
				stations.on_station_detect_radius_m,
				stations.record_type,
				stations.always_show_hh
			FROM
				stations
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
				projects_privileges.projects_id = stations.projects_id
			WHERE
				stations.stations_id = :stations_id
			AND
				stations.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);

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
				"Station not found ({id})",
				['id' => $stationsId],
			);
			return Utils::errStationNotFound();
		}

		return RetValueOrError::withValue(self::fetchResultToStation($data));
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
			? ' stations.stations_id <= :top_id AND '
			: ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			stations.stations_id,
			stations.projects_id,
			stations.created_at,
			stations.name,
			stations.full_name,
			stations.location_km,
			ST_X(stations.location_lonlat) AS location_lon,
			ST_Y(stations.location_lonlat) AS location_lat,
			stations.on_station_detect_radius_m,
			stations.record_type,
			stations.always_show_hh
			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				stations.stations_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				stations
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
				projects_privileges.projects_id = stations.projects_id
			WHERE
				stations.projects_id = :projects_id
			AND
				$WHERE_TOP_ID
				stations.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Station>>
	 */
	public function selectStationPage(
		UuidInterface $projectsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(projectsId:{projectsId}, userId:{userId}, page:{page})",
			[
				'projectsId' => $projectsId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
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
			fn ($data) => self::fetchResultToStation($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stations);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectStationPageTotalCount(
		UuidInterface $projectsId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
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
	public function insertStation(
		UuidInterface $stationsId,
		UuidInterface $projectsId,
		string $owner,
		string $name,
		?string $fullName,
		float $locationKm,
		?StationLocationLonlat $locationLonlat,
		?float $onStationDetectRadiusM,
		StationRecordType $recordType,
		bool $alwaysShowHh,
	): RetValueOrError {
		$lonlatStr = null;
		if (!is_null($locationLonlat)) {
			$lonlatStr = "POINT({$locationLonlat->longitude} {$locationLonlat->latitude})";
		}

		$query = $this->db->prepare(<<<SQL
			INSERT INTO stations (
				stations_id,
				projects_id,
				owner,
				name,
				full_name,
				location_km,
				location_lonlat,
				on_station_detect_radius_m,
				record_type,
				always_show_hh
			) VALUES (
				:stations_id,
				:projects_id,
				:owner,
				:name,
				:full_name,
				:location_km,
				ST_PointFromText(:location_lonlat),
				:on_station_detect_radius_m,
				:record_type,
				:always_show_hh
			);
			SQL
		);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);
		$query->bindValue(':full_name', $fullName, PDO::PARAM_STR);
		$query->bindValue(':location_km', $locationKm, PDO::PARAM_STR);
		$query->bindValue(':location_lonlat', $lonlatStr, PDO::PARAM_STR);
		$query->bindValue(':on_station_detect_radius_m', $onStationDetectRadiusM, PDO::PARAM_STR);
		$query->bindValue(':record_type', $recordType->value, PDO::PARAM_INT);
		$query->bindValue(':always_show_hh', $alwaysShowHh, PDO::PARAM_BOOL);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Station");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateStation(
		UuidInterface $stationsId,
		?string $name,
		?string $fullName,
		?float $locationKm,
		bool $hasLocationKm,
		?StationLocationLonlat $locationLonlat,
		bool $hasLocationLonlat,
		?float $onStationDetectRadiusM,
		bool $hasOnStationDetectRadiusM,
		?StationRecordType $recordType,
		?bool $alwaysShowHh,
	): RetValueOrError {
		$hasName = !is_null($name);
		$hasFullName = !is_null($fullName);
		$hasRecordType = !is_null($recordType);
		$hasAlwaysShowHh = !is_null($alwaysShowHh);

		$this->logger->info(
			"updateStation({id})",
			['id' => $stationsId],
		);

		if (
			!$hasName && !$hasFullName && !$hasLocationKm && !$hasLocationLonlat
			&& !$hasOnStationDetectRadiusM && !$hasRecordType && !$hasAlwaysShowHh
		) {
			return RetValueOrError::withValue(null);
		}

		$setClauses = [];
		if ($hasName) {
			$setClauses[] = 'name = :name';
		}
		if ($hasFullName) {
			$setClauses[] = 'full_name = :full_name';
		}
		if ($hasLocationKm) {
			$setClauses[] = 'location_km = :location_km';
		}
		if ($hasLocationLonlat) {
			$setClauses[] = 'location_lonlat = ST_PointFromText(:location_lonlat)';
		}
		if ($hasOnStationDetectRadiusM) {
			$setClauses[] = 'on_station_detect_radius_m = :on_station_detect_radius_m';
		}
		if ($hasRecordType) {
			$setClauses[] = 'record_type = :record_type';
		}
		if ($hasAlwaysShowHh) {
			$setClauses[] = 'always_show_hh = :always_show_hh';
		}

		$setClauseStr = implode(', ', $setClauses);
		$query = $this->db->prepare(<<<SQL
			UPDATE stations SET
				$setClauseStr
			WHERE
				stations_id = :stations_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
		if ($hasName) {
			$query->bindValue(':name', $name, PDO::PARAM_STR);
		}
		if ($hasFullName) {
			$query->bindValue(':full_name', $fullName, PDO::PARAM_STR);
		}
		if ($hasLocationKm) {
			$query->bindValue(':location_km', $locationKm, PDO::PARAM_STR);
		}
		if ($hasLocationLonlat) {
			$lonlatStr = is_null($locationLonlat)
				? null
				: "POINT({$locationLonlat->longitude} {$locationLonlat->latitude})";
			$query->bindValue(':location_lonlat', $lonlatStr, PDO::PARAM_STR);
		}
		if ($hasOnStationDetectRadiusM) {
			$query->bindValue(':on_station_detect_radius_m', $onStationDetectRadiusM, PDO::PARAM_STR);
		}
		if ($hasRecordType) {
			$query->bindValue(':record_type', $recordType->value, PDO::PARAM_INT);
		}
		if ($hasAlwaysShowHh) {
			$query->bindValue(':always_show_hh', $alwaysShowHh, PDO::PARAM_BOOL);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"Station not found ({id})",
						['id' => $stationsId],
					);
					return Utils::errStationNotFound();
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
	public function deleteStation(
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->info(
			"deleteStation({id})",
			['id' => $stationsId],
		);

		$query = $this->db->prepare(<<<SQL
			UPDATE
				stations
			SET
				deleted_at = CURRENT_TIMESTAMP()
			WHERE
				stations_id = :stations_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info(
					"Station not found ({id})",
					['id' => $stationsId],
				);
				return Utils::errStationNotFound();
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
	 * Return the IDs in $idList that do NOT exist in stations (or are deleted),
	 * optionally scoped to the project of a particular work_groups_id ancestor.
	 *
	 * Called by W3 TimetableRowsService with the timetable row's workGroupsId.
	 * `stations` is Project-rooted, so the scope check verifies the station
	 * belongs to the SAME PROJECT as the given work group
	 * (stations.projects_id = the work group's projects_id).
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function nonExistIdCheck(
		array $idList,
		?UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			'nonExistIdCheck stations idList: {idList}, workGroupsId: {workGroupsId}',
			[
				'idList' => $idList,
				'workGroupsId' => $workGroupsId,
			],
		);

		$idCount = count($idList);
		if ($idCount === 0) {
			return RetValueOrError::withValue([]);
		} elseif ($idCount === 1) {
			$idPlaceholders = 'SELECT UNHEX(:id_0) AS id';
		} else {
			$idPlaceholders = implode(' UNION ', array_map(
				fn ($i) => "SELECT UNHEX(:id_$i)" . ($i === 0 ? ' AS id' : ''),
				range(0, $idCount - 1),
			));
		}

		if (is_null($workGroupsId)) {
			$PROJECT_SCOPE_CHECK = '';
		} else {
			$PROJECT_SCOPE_CHECK =
				' AND stations.projects_id = (SELECT projects_id FROM work_groups WHERE work_groups_id = :work_groups_id)';
		}

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					id
				FROM
					(
						{$idPlaceholders}
					) AS id_list
				WHERE NOT EXISTS(
					SELECT
						*
					FROM
						stations

					WHERE
						stations_id = id_list.id
					AND
						stations.deleted_at IS NULL

					{$PROJECT_SCOPE_CHECK}
				)
				SQL
			);

			for ($i = 0; $i < $idCount; $i++) {
				$query->bindValue(":id_$i", $idList[$i]->getHex()->toString(), PDO::PARAM_STR);
			}
			if (!is_null($workGroupsId)) {
				$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$result = $query->fetchAll(PDO::FETCH_ASSOC);

			$nonExistIdList = array_map(
				fn ($data) => Uuid::fromBytes($data['id']),
				$result,
			);

			$this->logger->debug(
				'nonExistIdList(stations): {nonExistIdList}',
				['nonExistIdList' => $nonExistIdList],
			);

			return RetValueOrError::withValue($nonExistIdList);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}
}
