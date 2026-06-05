<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\StopPatternRowsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * StopPatternRow service. Privilege root: project-rooted via stop_pattern_rows.
 * projects_id. For create/getPage (parent = stopPatternId), privilege resolution
 * uses an inline SELECT against stop_patterns to find projects_id, then delegates
 * to ProjectsPrivilegesRepo — no StopPatternsRepo import (§0 scope rule).
 *
 * update() 4th arg `$requestedKeys` is an assoc array (key => value from the
 * request body) faithful to legacy MyServiceBase convention. Keys from the array
 * determine which fields are updated; values come from the StopPatternRow model
 * object (3rd arg).
 */
final class StopPatternRowsService
{
	private readonly StopPatternRowsRepo $stopPatternRowsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stopPatternRowsRepo = new StopPatternRowsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	/**
	 * Resolve projects_id from a stop_patterns row (inline — no StopPatternsRepo).
	 * Returns RetValueOrError<UuidInterface>.
	 */
	private function resolveProjectsIdFromStopPattern(
		UuidInterface $stopPatternsId,
	): RetValueOrError {
		try {
			$q = $this->db->prepare(
				'SELECT projects_id FROM stop_patterns'
				. ' WHERE stop_patterns_id = :id AND deleted_at IS NULL'
			);
			$q->bindValue(':id', $stopPatternsId->getBytes(), PDO::PARAM_STR);
			$q->execute();
			$row = $q->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				$this->logger->warning(
					"resolveProjectsId: StopPattern not found (stopPatternsId:{id})",
					['id' => $stopPatternsId],
				);
				return Utils::errStopPatternNotFound();
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
		return RetValueOrError::withValue(Uuid::fromBytes($row['projects_id']));
	}

	/** Coerce a request value (string UUID, or UuidInterface from a direct
	 *  test caller) into a UuidInterface. */
	private static function asUuid(mixed $v): UuidInterface
	{
		return $v instanceof UuidInterface ? $v : Uuid::fromString((string)$v);
	}

	/**
	 * @return RetValueOrError<array<\dev_t0r\trvis_backend\model\StopPatternRow>>
	 */
	public function create(
		UuidInterface $stopPatternsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(stopPatternsId:{stopPatternsId}, userId:{userId})",
			[
				'stopPatternsId' => $stopPatternsId,
				'userId' => $userId,
			],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$projectsIdResult = $this->resolveProjectsIdFromStopPattern($stopPatternsId);
		if ($projectsIdResult->isError) {
			return $projectsIdResult;
		}
		$projectsId = $projectsIdResult->value;

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStopPatternNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $d) {
				$rowId = Uuid::uuid7();
				$insertResult = $this->stopPatternRowsRepo->insertStopPatternRow(
					stopPatternRowId: $rowId,
					stopPatternsId: $stopPatternsId,
					owner: $userId,
					// HTTP body delivers project_stations_id as a string; repo expects UuidInterface.
					projectStationsId: self::asUuid($d->project_stations_id),
					sortKey: $d->sort_key ?? 0,
					trackName: $d->track_name,
					trackHidden: $d->track_hidden ?? false,
					isOperationOnlyStop: $d->is_operation_only_stop ?? false,
					isPass: $d->is_pass ?? false,
					driveTimeMm: $d->drive_time_mm,
					driveTimeSs: $d->drive_time_ss,
					dwellTimeMm: $d->dwell_time_mm,
					dwellTimeSs: $d->dwell_time_ss,
					showArrive: $d->show_arrive ?? true,
					showDeparture: $d->show_departure ?? true,
					arriveStr: $d->arrive_str,
					departureStr: $d->departure_str,
					runInLimit: $d->run_in_limit,
					runOutLimit: $d->run_out_limit,
					remarks: $d->remarks,
					alwaysShowHh: $d->always_show_hh ?? false,
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $rowId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create stop pattern row: {exception}",
				['exception' => $e],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'unknown error',
				$e->getCode(),
			);
		}

		$selectListResult = $this->stopPatternRowsRepo->selectListByIds($userId, $insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->stop_pattern_rows_id] = $m;
		}
		if (count($byId) !== count($insertedIds)) {
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'inconsistent read-back after bulk insert',
			);
		}
		$results = [];
		foreach ($insertedIds as $id) {
			$results[] = $byId[(string)$id];
		}

		return RetValueOrError::withValue($results, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<\dev_t0r\trvis_backend\model\StopPatternRow>
	 */
	public function getOne(
		string $userId,
		UuidInterface $stopPatternRowId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, stopPatternRowId:{id})",
			['userId' => $userId, 'id' => $stopPatternRowId],
		);
		return $this->stopPatternRowsRepo->selectStopPatternRowOne($userId, $stopPatternRowId);
	}

	/**
	 * @return RetValueOrError<array<\dev_t0r\trvis_backend\model\StopPatternRow>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $stopPatternsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, stopPatternsId:{stopPatternsId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'stopPatternsId' => $stopPatternsId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->stopPatternRowsRepo->selectStopPatternRowPageTotalCount(
			stopPatternsId: $stopPatternsId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->stopPatternRowsRepo->selectStopPatternRowPage(
			stopPatternsId: $stopPatternsId,
			userId: $userId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);

		return RetValueOrError::withTotalCount(
			value: $selectResult,
			totalCount: $totalCountResult,
		);
	}

	/**
	 * @param array<string,mixed> $requestedKeys Assoc array of key => value from
	 *   the request body (faithful to legacy MyServiceBase convention). Keys
	 *   determine which fields are updated; values are read from $body.
	 * @return RetValueOrError<\dev_t0r\trvis_backend\model\StopPatternRow>
	 */
	public function update(
		string $userId,
		UuidInterface $stopPatternRowId,
		object $body,
		array $requestedKeys,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, stopPatternRowId:{id})",
			['userId' => $userId, 'id' => $stopPatternRowId],
		);

		$selectResult = $this->stopPatternRowsRepo->selectStopPatternRowOne($userId, $stopPatternRowId);
		if ($selectResult->isError) {
			return $selectResult;
		}

		$projectsId = $selectResult->value->projects_id;

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStopPatternRowNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// $requestedKeys may be assoc (key=>value) or flat — normalize to key list
		$keys = array_is_list($requestedKeys) ? $requestedKeys : array_keys($requestedKeys);

		$projectStationsId = in_array('project_stations_id', $keys)
			? $body->project_stations_id
			: null;
		$sortKey = in_array('sort_key', $keys) ? $body->sort_key : null;
		$trackName = in_array('track_name', $keys) ? $body->track_name : null;
		$trackHidden = in_array('track_hidden', $keys) ? $body->track_hidden : null;
		$isOperationOnlyStop = in_array('is_operation_only_stop', $keys)
			? $body->is_operation_only_stop
			: null;
		$isPass = in_array('is_pass', $keys) ? $body->is_pass : null;
		$hasDriveTimeMm = in_array('drive_time_mm', $keys);
		$driveTimeMm = $hasDriveTimeMm ? $body->drive_time_mm : null;
		$hasDriveTimeSs = in_array('drive_time_ss', $keys);
		$driveTimeSs = $hasDriveTimeSs ? $body->drive_time_ss : null;
		$hasDwellTimeMm = in_array('dwell_time_mm', $keys);
		$dwellTimeMm = $hasDwellTimeMm ? $body->dwell_time_mm : null;
		$hasDwellTimeSs = in_array('dwell_time_ss', $keys);
		$dwellTimeSs = $hasDwellTimeSs ? $body->dwell_time_ss : null;
		$showArrive = in_array('show_arrive', $keys) ? $body->show_arrive : null;
		$showDeparture = in_array('show_departure', $keys) ? $body->show_departure : null;
		$hasArriveStr = in_array('arrive_str', $keys);
		$arriveStr = $hasArriveStr ? $body->arrive_str : null;
		$hasDepartureStr = in_array('departure_str', $keys);
		$departureStr = $hasDepartureStr ? $body->departure_str : null;
		$hasRunInLimit = in_array('run_in_limit', $keys);
		$runInLimit = $hasRunInLimit ? $body->run_in_limit : null;
		$hasRunOutLimit = in_array('run_out_limit', $keys);
		$runOutLimit = $hasRunOutLimit ? $body->run_out_limit : null;
		$hasRemarks = in_array('remarks', $keys);
		$remarks = $hasRemarks ? $body->remarks : null;
		$alwaysShowHh = in_array('always_show_hh', $keys) ? $body->always_show_hh : null;

		$updateResult = $this->stopPatternRowsRepo->updateStopPatternRow(
			stopPatternRowId: $stopPatternRowId,
			projectStationsId: $projectStationsId,
			sortKey: $sortKey,
			trackName: $trackName,
			trackHidden: $trackHidden,
			isOperationOnlyStop: $isOperationOnlyStop,
			isPass: $isPass,
			driveTimeMm: $driveTimeMm,
			hasDriveTimeMm: $hasDriveTimeMm,
			driveTimeSs: $driveTimeSs,
			hasDriveTimeSs: $hasDriveTimeSs,
			dwellTimeMm: $dwellTimeMm,
			hasDwellTimeMm: $hasDwellTimeMm,
			dwellTimeSs: $dwellTimeSs,
			hasDwellTimeSs: $hasDwellTimeSs,
			showArrive: $showArrive,
			showDeparture: $showDeparture,
			arriveStr: $arriveStr,
			hasArriveStr: $hasArriveStr,
			departureStr: $departureStr,
			hasDepartureStr: $hasDepartureStr,
			runInLimit: $runInLimit,
			hasRunInLimit: $hasRunInLimit,
			runOutLimit: $runOutLimit,
			hasRunOutLimit: $hasRunOutLimit,
			remarks: $remarks,
			hasRemarks: $hasRemarks,
			alwaysShowHh: $alwaysShowHh,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->stopPatternRowsRepo->selectStopPatternRowOne($userId, $stopPatternRowId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $stopPatternRowId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, stopPatternRowId:{id})",
			['userId' => $userId, 'id' => $stopPatternRowId],
		);

		$selectResult = $this->stopPatternRowsRepo->selectStopPatternRowOne($userId, $stopPatternRowId);
		if ($selectResult->isError) {
			return $selectResult;
		}

		$projectsId = $selectResult->value->projects_id;

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStopPatternRowNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->stopPatternRowsRepo->deleteStopPatternRow($stopPatternRowId);
	}
}
