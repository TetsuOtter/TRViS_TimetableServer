<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * station_tracks — Station-rooted (via stations_id).
 *
 * MUST NOT implement IMyRepoSelectPrivilegeType and MUST NOT have a
 * selectPrivilegeType method — faithful-port lock per CONTRIBUTING-P3.md §0.
 * Privilege is resolved by the service through StationsRepo::selectPrivilegeType
 * keyed on the owning stations_id.
 *
 * nonExistIdCheck is ported faithfully from legacy MyRepoBase (W3
 * TimetableRowsRepo calls this; see legacy TimetableRowsService).
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in the
 * 4-exclude carve-out). Canonical precedent: ProjectsRepo::fetchResultToProject.
 */
final class StationTracksRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private static function fetchResultToStationTrack(mixed $data): StationTrack
	{
		$result = new StationTrack();
		$result->setData([
			'station_tracks_id' => Uuid::fromBytes($data['station_tracks_id']),
			'stations_id' => Uuid::fromBytes($data['stations_id']),
			'description' => $data['description'],
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'name' => $data['name'],
			'run_in_limit' => $data['run_in_limit'],
			'run_out_limit' => $data['run_out_limit'],
		]);
		return $result;
	}

	/**
	 * Resolve stations_id for the given station_tracks_id.
	 * Returns errStationTrackNotFound when the track does not exist / is deleted.
	 *
	 * Used by the service to resolve the privilege anchor before delegating to
	 * StationsRepo::selectPrivilegeType.
	 *
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectStationsIdByStationTracksId(
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			"selectStationsIdByStationTracksId(stationTracksId:{id})",
			['id' => $stationTracksId],
		);

		try {
			$query = $this->db->prepare(
				'SELECT stations_id FROM station_tracks'
				. ' WHERE station_tracks_id = :stationTracksId AND deleted_at IS NULL;'
			);
			$query->bindValue(':stationTracksId', $stationTracksId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errStationTrackNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			return RetValueOrError::withValue(Uuid::fromBytes($row['stations_id']));
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to resolve stations_id for station_track ({errorCode})",
				["errorCode" => $errCode],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<StationTrack>
	 */
	public function selectStationTrackOne(
		string $userId,
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne stationTracksId: {id}",
			['id' => $stationTracksId],
		);

		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$query = $this->db->prepare(<<<SQL
			SELECT
				station_tracks.station_tracks_id,
				station_tracks.stations_id,
				station_tracks.description,
				station_tracks.created_at,
				station_tracks.name,
				station_tracks.run_in_limit,
				station_tracks.run_out_limit
			FROM
				station_tracks
			JOIN
				stations
			ON
				stations.stations_id = station_tracks.stations_id
			AND
				stations.deleted_at IS NULL
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
				) AS pp
			ON
				pp.projects_id = stations.projects_id
			WHERE
				station_tracks.station_tracks_id = :stationTracksId
			AND
				station_tracks.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':stationTracksId', $stationTracksId->getBytes(), PDO::PARAM_STR);

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
				"StationTrack not found ({id})",
				['id' => $stationTracksId],
			);
			return Utils::errStationTrackNotFound();
		}

		return RetValueOrError::withValue(self::fetchResultToStationTrack($data));
	}

	/**
	 * Bulk faithful mirror of selectStationTrackOne: identical projection /
	 * FROM / privilege-JOIN / WHERE filters, only the single-id predicate
	 * becomes `station_tracks.station_tracks_id IN (...)`. Element order is
	 * NOT guaranteed; the caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<StationTrack>>
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
				station_tracks.station_tracks_id,
				station_tracks.stations_id,
				station_tracks.description,
				station_tracks.created_at,
				station_tracks.name,
				station_tracks.run_in_limit,
				station_tracks.run_out_limit
			FROM
				station_tracks
			JOIN
				stations
			ON
				stations.stations_id = station_tracks.stations_id
			AND
				stations.deleted_at IS NULL
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
				) AS pp
			ON
				pp.projects_id = stations.projects_id
			WHERE
				station_tracks.station_tracks_id IN ($placeholders)
			AND
				station_tracks.deleted_at IS NULL
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

		$stationTracks = array_map(
			fn ($data) => self::fetchResultToStationTrack($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stationTracks);
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
			? ' station_tracks.station_tracks_id <= :top_id AND '
			: ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			station_tracks.station_tracks_id,
			station_tracks.stations_id,
			station_tracks.description,
			station_tracks.created_at,
			station_tracks.name,
			station_tracks.run_in_limit,
			station_tracks.run_out_limit
			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				station_tracks.station_tracks_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				station_tracks
			JOIN
				stations
			ON
				stations.stations_id = station_tracks.stations_id
			AND
				stations.deleted_at IS NULL
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
				) AS pp
			ON
				pp.projects_id = stations.projects_id
			WHERE
				station_tracks.stations_id = :stationsId
			AND
				$WHERE_TOP_ID
				station_tracks.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function selectStationTrackPage(
		UuidInterface $stationsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(stationsId:{stationsId}, userId:{userId}, page:{page})",
			[
				'stationsId' => $stationsId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':stationsId', $stationsId->getBytes(), PDO::PARAM_STR);
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

		$tracks = array_map(
			fn ($data) => self::fetchResultToStationTrack($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($tracks);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectStationTrackPageTotalCount(
		UuidInterface $stationsId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':stationsId', $stationsId->getBytes(), PDO::PARAM_STR);
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
	public function insertStationTrack(
		UuidInterface $stationTracksId,
		UuidInterface $stationsId,
		string $owner,
		string $description,
		string $name,
		?int $runInLimit,
		?int $runOutLimit,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO station_tracks (
				station_tracks_id,
				stations_id,
				description,
				owner,
				name,
				run_in_limit,
				run_out_limit
			) VALUES (
				:station_tracks_id,
				:stations_id,
				:description,
				:owner,
				:name,
				:run_in_limit,
				:run_out_limit
			);
			SQL
		);
		$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);
		$query->bindValue(':run_in_limit', $runInLimit, PDO::PARAM_INT);
		$query->bindValue(':run_out_limit', $runOutLimit, PDO::PARAM_INT);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "StationTrack");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateStationTrack(
		UuidInterface $stationTracksId,
		?string $description,
		?string $name,
		?int $runInLimit,
		?int $runOutLimit,
	): RetValueOrError {
		$hasDescription = !is_null($description);
		$hasName = !is_null($name);
		$hasRunInLimit = !is_null($runInLimit);
		$hasRunOutLimit = !is_null($runOutLimit);

		$this->logger->info(
			"updateStationTrack({id})",
			['id' => $stationTracksId],
		);

		if (!$hasDescription && !$hasName && !$hasRunInLimit && !$hasRunOutLimit) {
			return RetValueOrError::withValue(null);
		}

		$setClauses = [];
		if ($hasDescription) {
			$setClauses[] = 'description = :description';
		}
		if ($hasName) {
			$setClauses[] = 'name = :name';
		}
		if ($hasRunInLimit) {
			$setClauses[] = 'run_in_limit = :run_in_limit';
		}
		if ($hasRunOutLimit) {
			$setClauses[] = 'run_out_limit = :run_out_limit';
		}

		$setClauseStr = implode(', ', $setClauses);
		$query = $this->db->prepare(<<<SQL
			UPDATE station_tracks SET
				$setClauseStr
			WHERE
				station_tracks_id = :station_tracks_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);
		if ($hasDescription) {
			$query->bindValue(':description', $description, PDO::PARAM_STR);
		}
		if ($hasName) {
			$query->bindValue(':name', $name, PDO::PARAM_STR);
		}
		if ($hasRunInLimit) {
			$query->bindValue(':run_in_limit', $runInLimit, PDO::PARAM_INT);
		}
		if ($hasRunOutLimit) {
			$query->bindValue(':run_out_limit', $runOutLimit, PDO::PARAM_INT);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"StationTrack not found ({id})",
						['id' => $stationTracksId],
					);
					return Utils::errStationTrackNotFound();
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
	public function deleteStationTrack(
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->info(
			"deleteStationTrack({id})",
			['id' => $stationTracksId],
		);

		$query = $this->db->prepare(<<<SQL
			UPDATE
				station_tracks
			SET
				deleted_at = CURRENT_TIMESTAMP()
			WHERE
				station_tracks_id = :station_tracks_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info(
					"StationTrack not found ({id})",
					['id' => $stationTracksId],
				);
				return Utils::errStationTrackNotFound();
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
	 * Return the IDs in $idList that do NOT exist in station_tracks (or are
	 * deleted), optionally scoped to the project of a particular work_groups_id
	 * ancestor.
	 *
	 * Called by W3 TimetableRowsRepo/Service with the timetable row's
	 * workGroupsId. station_tracks is now Project-rooted through its parent
	 * `stations` (stations.projects_id), so when $workGroupsId is non-null the
	 * scope check JOINs `stations` and verifies the station's project equals
	 * the given work group's project.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function nonExistIdCheck(
		array $idList,
		?UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			'nonExistIdCheck station_tracks idList: {idList}, workGroupsId: {workGroupsId}',
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
			$JOIN_QUERY = '';
			$PARENTS_WHERE_DELETED_AT_IS_NULL = '';
			$WORK_GROUPS_ID_CHECK = '';
		} else {
			$JOIN_QUERY = <<<SQL
			INNER JOIN
				stations
			USING
				(stations_id)
			SQL;
			$PARENTS_WHERE_DELETED_AT_IS_NULL = <<<SQL
			AND
				stations.deleted_at IS NULL
			SQL;
			$WORK_GROUPS_ID_CHECK =
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
						station_tracks

					{$JOIN_QUERY}

					WHERE
						station_tracks_id = id_list.id
					AND
						station_tracks.deleted_at IS NULL

					{$PARENTS_WHERE_DELETED_AT_IS_NULL}

					{$WORK_GROUPS_ID_CHECK}
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
				'nonExistIdList(station_tracks): {nonExistIdList}',
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
