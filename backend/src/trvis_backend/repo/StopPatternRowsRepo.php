<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stop_pattern_rows — StopPattern-child (parentId = stop_patterns_id).
 * Privilege root: project-rooted. `projects_id` IS a direct column in
 * stop_pattern_rows (populated at INSERT via subquery from stop_patterns).
 * selectPrivilegeType resolves via stop_pattern_rows.projects_id and then
 * delegates to ProjectsPrivilegesRepo — NOT via StopPatternsRepo.
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in
 * the 4-exclude carve-out). Canonical precedent:
 * ProjectsRepo::fetchResultToProject.
 */
final class StopPatternRowsRepo implements IMyRepoSelectPrivilegeType
{
	private const TABLE_NAME = 'stop_pattern_rows';

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private static function fetchResultToStopPatternRow(mixed $data): StopPatternRow
	{
		$row = new StopPatternRow();
		$row->setData([
			'stop_pattern_rows_id' => Uuid::fromBytes($data['stop_pattern_rows_id']),
			'stop_patterns_id' => Uuid::fromBytes($data['stop_patterns_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'project_stations_id' => Uuid::fromBytes($data['project_stations_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'sort_key' => intval($data['sort_key']),
			'track_name' => $data['track_name'],
			'track_hidden' => boolval($data['track_hidden']),
			'is_operation_only_stop' => boolval($data['is_operation_only_stop']),
			'is_pass' => boolval($data['is_pass']),
			'drive_time_mm' => is_null($data['drive_time_mm']) ? null : intval($data['drive_time_mm']),
			'drive_time_ss' => is_null($data['drive_time_ss']) ? null : intval($data['drive_time_ss']),
			'dwell_time_mm' => is_null($data['dwell_time_mm']) ? null : intval($data['dwell_time_mm']),
			'dwell_time_ss' => is_null($data['dwell_time_ss']) ? null : intval($data['dwell_time_ss']),
			'show_arrive' => boolval($data['show_arrive']),
			'show_departure' => boolval($data['show_departure']),
			'arrive_str' => $data['arrive_str'],
			'departure_str' => $data['departure_str'],
			'run_in_limit' => is_null($data['run_in_limit']) ? null : intval($data['run_in_limit']),
			'run_out_limit' => is_null($data['run_out_limit']) ? null : intval($data['run_out_limit']),
			'remarks' => $data['remarks'],
			'always_show_hh' => boolval($data['always_show_hh']),
		]);
		return $row;
	}

	/**
	 * Project-rooted privilege resolver. Faithfully ported from legacy
	 * MyProjectRootedRepoBase::selectPrivilegeType. Reads projects_id directly
	 * from stop_pattern_rows (the DB column is real — verified against
	 * mysql/init_sql/0_create_db.sql lines 1353-1357), then delegates to
	 * ProjectsPrivilegesRepo. Does NOT touch StopPatternsRepo.
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
			'selectPrivilegeType (project-rooted) {TABLE_NAME} id: {id}, userId: {userId}',
			['TABLE_NAME' => self::TABLE_NAME, 'id' => $id, 'userId' => $userId],
		);
		try {
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					projects_id
				FROM
					stop_pattern_rows
				WHERE
					stop_pattern_rows_id = :id
				AND
					deleted_at IS NULL
				SQL
				.
				($selectForUpdate ? ' FOR UPDATE' : '')
			);
			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$row = $query->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				$this->logger->warning(
					'selectPrivilegeType (project-rooted) {TABLE_NAME} - row not found (id:{id})',
					['TABLE_NAME' => self::TABLE_NAME, 'id' => $id],
				);
				return Utils::errStopPatternRowNotFound();
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
		$projectsId = Uuid::fromBytes($row['projects_id']);
		return (new ProjectsPrivilegesRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}

	/**
	 * @return RetValueOrError<StopPatternRow>
	 */
	public function selectStopPatternRowOne(
		string $userId,
		UuidInterface $stopPatternRowId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne stopPatternRowId: {id}",
			['id' => $stopPatternRowId],
		);

		$WHERE_USER_ID = $userId === Constants::UID_ANONYMOUS
			? ' uid = :userId '
			: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$query = $this->db->prepare(<<<SQL
			SELECT
				stop_pattern_rows.stop_pattern_rows_id,
				stop_pattern_rows.stop_patterns_id,
				stop_pattern_rows.projects_id,
				stop_pattern_rows.project_stations_id,
				stop_pattern_rows.created_at,
				stop_pattern_rows.sort_key,
				stop_pattern_rows.track_name,
				stop_pattern_rows.track_hidden,
				stop_pattern_rows.is_operation_only_stop,
				stop_pattern_rows.is_pass,
				stop_pattern_rows.drive_time_mm,
				stop_pattern_rows.drive_time_ss,
				stop_pattern_rows.dwell_time_mm,
				stop_pattern_rows.dwell_time_ss,
				stop_pattern_rows.show_arrive,
				stop_pattern_rows.show_departure,
				stop_pattern_rows.arrive_str,
				stop_pattern_rows.departure_str,
				stop_pattern_rows.run_in_limit,
				stop_pattern_rows.run_out_limit,
				stop_pattern_rows.remarks,
				stop_pattern_rows.always_show_hh
			FROM
				stop_pattern_rows
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
				projects_privileges.projects_id = stop_pattern_rows.projects_id
			WHERE
				stop_pattern_rows.stop_pattern_rows_id = :stop_pattern_rows_id
			AND
				stop_pattern_rows.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':stop_pattern_rows_id', $stopPatternRowId->getBytes(), PDO::PARAM_STR);

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
				"StopPatternRow not found ({id})",
				['id' => $stopPatternRowId],
			);
			return Utils::errStopPatternRowNotFound();
		}

		return RetValueOrError::withValue(self::fetchResultToStopPatternRow($data));
	}

	/**
	 * Bulk faithful mirror of selectStopPatternRowOne: identical projection /
	 * FROM / privilege-JOIN / WHERE filters, only the single-id predicate
	 * becomes `stop_pattern_rows.stop_pattern_rows_id IN (...)`. Element order
	 * is NOT guaranteed; the caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<StopPatternRow>>
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
				stop_pattern_rows.stop_pattern_rows_id,
				stop_pattern_rows.stop_patterns_id,
				stop_pattern_rows.projects_id,
				stop_pattern_rows.project_stations_id,
				stop_pattern_rows.created_at,
				stop_pattern_rows.sort_key,
				stop_pattern_rows.track_name,
				stop_pattern_rows.track_hidden,
				stop_pattern_rows.is_operation_only_stop,
				stop_pattern_rows.is_pass,
				stop_pattern_rows.drive_time_mm,
				stop_pattern_rows.drive_time_ss,
				stop_pattern_rows.dwell_time_mm,
				stop_pattern_rows.dwell_time_ss,
				stop_pattern_rows.show_arrive,
				stop_pattern_rows.show_departure,
				stop_pattern_rows.arrive_str,
				stop_pattern_rows.departure_str,
				stop_pattern_rows.run_in_limit,
				stop_pattern_rows.run_out_limit,
				stop_pattern_rows.remarks,
				stop_pattern_rows.always_show_hh
			FROM
				stop_pattern_rows
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
				projects_privileges.projects_id = stop_pattern_rows.projects_id
			WHERE
				stop_pattern_rows.stop_pattern_rows_id IN ($placeholders)
			AND
				stop_pattern_rows.deleted_at IS NULL
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

		$rows = array_map(
			fn ($data) => self::fetchResultToStopPatternRow($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($rows);
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
			? ' stop_pattern_rows.stop_pattern_rows_id <= :top_id AND '
			: ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			stop_pattern_rows.stop_pattern_rows_id,
			stop_pattern_rows.stop_patterns_id,
			stop_pattern_rows.projects_id,
			stop_pattern_rows.project_stations_id,
			stop_pattern_rows.created_at,
			stop_pattern_rows.sort_key,
			stop_pattern_rows.track_name,
			stop_pattern_rows.track_hidden,
			stop_pattern_rows.is_operation_only_stop,
			stop_pattern_rows.is_pass,
			stop_pattern_rows.drive_time_mm,
			stop_pattern_rows.drive_time_ss,
			stop_pattern_rows.dwell_time_mm,
			stop_pattern_rows.dwell_time_ss,
			stop_pattern_rows.show_arrive,
			stop_pattern_rows.show_departure,
			stop_pattern_rows.arrive_str,
			stop_pattern_rows.departure_str,
			stop_pattern_rows.run_in_limit,
			stop_pattern_rows.run_out_limit,
			stop_pattern_rows.remarks,
			stop_pattern_rows.always_show_hh
			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				stop_pattern_rows.stop_pattern_rows_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				stop_pattern_rows
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
				projects_privileges.projects_id = stop_pattern_rows.projects_id
			WHERE
				stop_pattern_rows.stop_patterns_id = :stop_patterns_id
			AND
				$WHERE_TOP_ID
				stop_pattern_rows.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<StopPatternRow>>
	 */
	public function selectStopPatternRowPage(
		UuidInterface $stopPatternsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(stopPatternsId:{stopPatternsId}, userId:{userId}, page:{page})",
			[
				'stopPatternsId' => $stopPatternsId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':stop_patterns_id', $stopPatternsId->getBytes(), PDO::PARAM_STR);
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

		$rows = array_map(
			fn ($data) => self::fetchResultToStopPatternRow($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($rows);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectStopPatternRowPageTotalCount(
		UuidInterface $stopPatternsId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':stop_patterns_id', $stopPatternsId->getBytes(), PDO::PARAM_STR);
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
	public function insertStopPatternRow(
		UuidInterface $stopPatternRowId,
		UuidInterface $stopPatternsId,
		string $owner,
		UuidInterface $projectStationsId,
		int $sortKey,
		?string $trackName,
		bool $trackHidden,
		bool $isOperationOnlyStop,
		bool $isPass,
		?int $driveTimeMm,
		?int $driveTimeSs,
		?int $dwellTimeMm,
		?int $dwellTimeSs,
		bool $showArrive,
		bool $showDeparture,
		?string $arriveStr,
		?string $departureStr,
		?int $runInLimit,
		?int $runOutLimit,
		?string $remarks,
		bool $alwaysShowHh,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO stop_pattern_rows (
				stop_pattern_rows_id,
				stop_patterns_id,
				projects_id,
				project_stations_id,
				owner,
				sort_key,
				track_name,
				track_hidden,
				is_operation_only_stop,
				is_pass,
				drive_time_mm,
				drive_time_ss,
				dwell_time_mm,
				dwell_time_ss,
				show_arrive,
				show_departure,
				arrive_str,
				departure_str,
				run_in_limit,
				run_out_limit,
				remarks,
				always_show_hh
			) VALUES (
				:stop_pattern_rows_id,
				:stop_patterns_id,
				(SELECT sp.projects_id FROM stop_patterns sp WHERE sp.stop_patterns_id = :stop_patterns_id),
				:project_stations_id,
				:owner,
				:sort_key,
				:track_name,
				:track_hidden,
				:is_operation_only_stop,
				:is_pass,
				:drive_time_mm,
				:drive_time_ss,
				:dwell_time_mm,
				:dwell_time_ss,
				:show_arrive,
				:show_departure,
				:arrive_str,
				:departure_str,
				:run_in_limit,
				:run_out_limit,
				:remarks,
				:always_show_hh
			);
			SQL
		);

		$query->bindValue(':stop_pattern_rows_id', $stopPatternRowId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':stop_patterns_id', $stopPatternsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':project_stations_id', $projectStationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':sort_key', $sortKey, PDO::PARAM_INT);
		$query->bindValue(':track_name', $trackName, PDO::PARAM_STR);
		$query->bindValue(':track_hidden', $trackHidden, PDO::PARAM_BOOL);
		$query->bindValue(':is_operation_only_stop', $isOperationOnlyStop, PDO::PARAM_BOOL);
		$query->bindValue(':is_pass', $isPass, PDO::PARAM_BOOL);
		$query->bindValue(':drive_time_mm', $driveTimeMm, $driveTimeMm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':drive_time_ss', $driveTimeSs, $driveTimeSs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':dwell_time_mm', $dwellTimeMm, $dwellTimeMm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':dwell_time_ss', $dwellTimeSs, $dwellTimeSs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':show_arrive', $showArrive, PDO::PARAM_BOOL);
		$query->bindValue(':show_departure', $showDeparture, PDO::PARAM_BOOL);
		$query->bindValue(':arrive_str', $arriveStr, PDO::PARAM_STR);
		$query->bindValue(':departure_str', $departureStr, PDO::PARAM_STR);
		$query->bindValue(':run_in_limit', $runInLimit, $runInLimit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':run_out_limit', $runOutLimit, $runOutLimit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "StopPatternRow");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateStopPatternRow(
		UuidInterface $stopPatternRowId,
		?UuidInterface $projectStationsId,
		?int $sortKey,
		?string $trackName,
		?bool $trackHidden,
		?bool $isOperationOnlyStop,
		?bool $isPass,
		?int $driveTimeMm,
		bool $hasDriveTimeMm,
		?int $driveTimeSs,
		bool $hasDriveTimeSs,
		?int $dwellTimeMm,
		bool $hasDwellTimeMm,
		?int $dwellTimeSs,
		bool $hasDwellTimeSs,
		?bool $showArrive,
		?bool $showDeparture,
		?string $arriveStr,
		bool $hasArriveStr,
		?string $departureStr,
		bool $hasDepartureStr,
		?int $runInLimit,
		bool $hasRunInLimit,
		?int $runOutLimit,
		bool $hasRunOutLimit,
		?string $remarks,
		bool $hasRemarks,
		?bool $alwaysShowHh,
	): RetValueOrError {
		$hasProjectStationsId = !is_null($projectStationsId);
		$hasSortKey = !is_null($sortKey);
		$hasTrackName = !is_null($trackName);
		$hasTrackHidden = !is_null($trackHidden);
		$hasIsOperationOnlyStop = !is_null($isOperationOnlyStop);
		$hasIsPass = !is_null($isPass);
		$hasShowArrive = !is_null($showArrive);
		$hasShowDeparture = !is_null($showDeparture);
		$hasAlwaysShowHh = !is_null($alwaysShowHh);

		$this->logger->info(
			"updateStopPatternRow({id})",
			['id' => $stopPatternRowId],
		);

		if (
			!$hasProjectStationsId && !$hasSortKey && !$hasTrackName
			&& !$hasTrackHidden && !$hasIsOperationOnlyStop && !$hasIsPass
			&& !$hasDriveTimeMm && !$hasDriveTimeSs && !$hasDwellTimeMm && !$hasDwellTimeSs
			&& !$hasShowArrive && !$hasShowDeparture
			&& !$hasArriveStr && !$hasDepartureStr
			&& !$hasRunInLimit && !$hasRunOutLimit
			&& !$hasRemarks && !$hasAlwaysShowHh
		) {
			return RetValueOrError::withValue(null);
		}

		$setClauses = [];
		if ($hasProjectStationsId) {
			$setClauses[] = 'project_stations_id = :project_stations_id';
		}
		if ($hasSortKey) {
			$setClauses[] = 'sort_key = :sort_key';
		}
		if ($hasTrackName) {
			$setClauses[] = 'track_name = :track_name';
		}
		if ($hasTrackHidden) {
			$setClauses[] = 'track_hidden = :track_hidden';
		}
		if ($hasIsOperationOnlyStop) {
			$setClauses[] = 'is_operation_only_stop = :is_operation_only_stop';
		}
		if ($hasIsPass) {
			$setClauses[] = 'is_pass = :is_pass';
		}
		if ($hasDriveTimeMm) {
			$setClauses[] = 'drive_time_mm = :drive_time_mm';
		}
		if ($hasDriveTimeSs) {
			$setClauses[] = 'drive_time_ss = :drive_time_ss';
		}
		if ($hasDwellTimeMm) {
			$setClauses[] = 'dwell_time_mm = :dwell_time_mm';
		}
		if ($hasDwellTimeSs) {
			$setClauses[] = 'dwell_time_ss = :dwell_time_ss';
		}
		if ($hasShowArrive) {
			$setClauses[] = 'show_arrive = :show_arrive';
		}
		if ($hasShowDeparture) {
			$setClauses[] = 'show_departure = :show_departure';
		}
		if ($hasArriveStr) {
			$setClauses[] = 'arrive_str = :arrive_str';
		}
		if ($hasDepartureStr) {
			$setClauses[] = 'departure_str = :departure_str';
		}
		if ($hasRunInLimit) {
			$setClauses[] = 'run_in_limit = :run_in_limit';
		}
		if ($hasRunOutLimit) {
			$setClauses[] = 'run_out_limit = :run_out_limit';
		}
		if ($hasRemarks) {
			$setClauses[] = 'remarks = :remarks';
		}
		if ($hasAlwaysShowHh) {
			$setClauses[] = 'always_show_hh = :always_show_hh';
		}

		$setClauseStr = implode(', ', $setClauses);
		$query = $this->db->prepare(<<<SQL
			UPDATE stop_pattern_rows SET
				$setClauseStr
			WHERE
				stop_pattern_rows_id = :stop_pattern_rows_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stop_pattern_rows_id', $stopPatternRowId->getBytes(), PDO::PARAM_STR);
		if ($hasProjectStationsId) {
			$query->bindValue(':project_stations_id', $projectStationsId->getBytes(), PDO::PARAM_STR);
		}
		if ($hasSortKey) {
			$query->bindValue(':sort_key', $sortKey, PDO::PARAM_INT);
		}
		if ($hasTrackName) {
			$query->bindValue(':track_name', $trackName, PDO::PARAM_STR);
		}
		if ($hasTrackHidden) {
			$query->bindValue(':track_hidden', $trackHidden, PDO::PARAM_BOOL);
		}
		if ($hasIsOperationOnlyStop) {
			$query->bindValue(':is_operation_only_stop', $isOperationOnlyStop, PDO::PARAM_BOOL);
		}
		if ($hasIsPass) {
			$query->bindValue(':is_pass', $isPass, PDO::PARAM_BOOL);
		}
		if ($hasDriveTimeMm) {
			$query->bindValue(':drive_time_mm', $driveTimeMm, $driveTimeMm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasDriveTimeSs) {
			$query->bindValue(':drive_time_ss', $driveTimeSs, $driveTimeSs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasDwellTimeMm) {
			$query->bindValue(':dwell_time_mm', $dwellTimeMm, $dwellTimeMm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasDwellTimeSs) {
			$query->bindValue(':dwell_time_ss', $dwellTimeSs, $dwellTimeSs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasShowArrive) {
			$query->bindValue(':show_arrive', $showArrive, PDO::PARAM_BOOL);
		}
		if ($hasShowDeparture) {
			$query->bindValue(':show_departure', $showDeparture, PDO::PARAM_BOOL);
		}
		if ($hasArriveStr) {
			$query->bindValue(':arrive_str', $arriveStr, PDO::PARAM_STR);
		}
		if ($hasDepartureStr) {
			$query->bindValue(':departure_str', $departureStr, PDO::PARAM_STR);
		}
		if ($hasRunInLimit) {
			$query->bindValue(':run_in_limit', $runInLimit, $runInLimit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasRunOutLimit) {
			$query->bindValue(':run_out_limit', $runOutLimit, $runOutLimit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		}
		if ($hasRemarks) {
			$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
		}
		if ($hasAlwaysShowHh) {
			$query->bindValue(':always_show_hh', $alwaysShowHh, PDO::PARAM_BOOL);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"StopPatternRow not found ({id})",
						['id' => $stopPatternRowId],
					);
					return Utils::errStopPatternRowNotFound();
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
	public function deleteStopPatternRow(
		UuidInterface $stopPatternRowId,
	): RetValueOrError {
		$this->logger->info(
			"deleteStopPatternRow({id})",
			['id' => $stopPatternRowId],
		);

		$query = $this->db->prepare(<<<SQL
			UPDATE
				stop_pattern_rows
			SET
				deleted_at = CURRENT_TIMESTAMP()
			WHERE
				stop_pattern_rows_id = :stop_pattern_rows_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stop_pattern_rows_id', $stopPatternRowId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info(
					"StopPatternRow not found ({id})",
					['id' => $stopPatternRowId],
				);
				return Utils::errStopPatternRowNotFound();
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
