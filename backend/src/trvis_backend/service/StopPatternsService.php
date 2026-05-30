<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\StopPatternsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * StopPattern service. Project-rooted directly via projects_id — privilege
 * checks use ProjectsPrivilegesRepo (same pattern as ProjectsService).
 */
final class StopPatternsService
{
	private readonly StopPatternsRepo $stopPatternsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stopPatternsRepo = new StopPatternsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	/** Coerce a request value (string UUID, or UuidInterface from a direct
	 *  test caller) into a UuidInterface. */
	private static function asUuid(mixed $v): UuidInterface
	{
		return $v instanceof UuidInterface ? $v : Uuid::fromString((string)$v);
	}

	/** Like asUuid() but passes null/empty through as null. */
	private static function asUuidOrNull(mixed $v): ?UuidInterface
	{
		if ($v === null || $v === '') {
			return null;
		}
		return self::asUuid($v);
	}

	/**
	 * @return RetValueOrError<StopPattern>
	 */
	public function getOne(
		string $userId,
		UuidInterface $stopPatternId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, stopPatternId:{stopPatternId})",
			[
				'userId' => $userId,
				'stopPatternId' => $stopPatternId,
			],
		);

		return $this->stopPatternsRepo->selectStopPatternOne($userId, $stopPatternId);
	}

	/**
	 * @return RetValueOrError<array<StopPattern>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $projectsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, projectsId:{projectsId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'projectsId' => $projectsId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->stopPatternsRepo->selectStopPatternPageTotalCount(
			projectsId: $projectsId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->stopPatternsRepo->selectStopPatternPage(
			projectsId: $projectsId,
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
	 * @return RetValueOrError<array<StopPattern>>
	 */
	public function create(
		UuidInterface $projectsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(projectsId:{projectsId}, userId:{userId})",
			[
				'projectsId' => $projectsId,
				'userId' => $userId,
			],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

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
			return Utils::errProjectNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $d) {
				$stopPatternId = Uuid::uuid7();
				$insertResult = $this->stopPatternsRepo->insertStopPattern(
					stopPatternId: $stopPatternId,
					projectsId: $projectsId,
					owner: $userId,
					name: $d->name,
					// The HTTP path delivers these as strings (parsed JSON body);
					// direct-service test callers pass UuidInterface already.
					// The repo demands UuidInterface, so coerce strings here —
					// without this, createStopPattern is a hard 500 (TypeError).
					linesId: self::asUuid($d->lines_id),
					direction: $d->direction ?? 1,
					fromProjectStationsId: self::asUuidOrNull($d->from_project_stations_id),
					toProjectStationsId: self::asUuidOrNull($d->to_project_stations_id),
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $stopPatternId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create stop pattern: {exception}",
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

		// Select the inserted rows back and return them as an array
		$selectListResult = $this->stopPatternsRepo->selectListByIds($userId, $insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->stop_patterns_id] = $m;
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
	 * @return RetValueOrError<StopPattern>
	 */
	public function update(
		string $userId,
		UuidInterface $stopPatternId,
		object $body,
		array $requestedKeys,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, stopPatternId:{stopPatternId})",
			[
				'userId' => $userId,
				'stopPatternId' => $stopPatternId,
			],
		);

		// For get-then-update, we check read access via selectStopPatternOne
		// (which JOINs projects_privileges). If the user has read but not write,
		// we still need a separate privilege check for write.
		$selectResult = $this->stopPatternsRepo->selectStopPatternOne($userId, $stopPatternId);
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
			return Utils::errStopPatternNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$name = in_array('name', $requestedKeys) ? $body->name : null;
		$linesId = in_array('lines_id', $requestedKeys) ? $body->lines_id : null;
		$direction = in_array('direction', $requestedKeys) ? $body->direction : null;
		$fromProjectStationsId = in_array('from_project_stations_id', $requestedKeys)
			? $body->from_project_stations_id
			: null;
		$toProjectStationsId = in_array('to_project_stations_id', $requestedKeys)
			? $body->to_project_stations_id
			: null;

		$updateResult = $this->stopPatternsRepo->updateStopPattern(
			stopPatternId: $stopPatternId,
			name: $name,
			linesId: $linesId,
			direction: $direction,
			fromProjectStationsId: $fromProjectStationsId,
			toProjectStationsId: $toProjectStationsId,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->stopPatternsRepo->selectStopPatternOne($userId, $stopPatternId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $stopPatternId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, stopPatternId:{stopPatternId})",
			[
				'userId' => $userId,
				'stopPatternId' => $stopPatternId,
			],
		);

		// Get the stop pattern to find projects_id (and implicitly check read access)
		$selectResult = $this->stopPatternsRepo->selectStopPatternOne($userId, $stopPatternId);
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
			return Utils::errStopPatternNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->stopPatternsRepo->deleteStopPattern(
			stopPatternId: $stopPatternId,
		);
	}
}
