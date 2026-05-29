<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Work service. WorkGroup-rooted (work_groups_id FK) — privilege checks use
 * WorkGroupsPrivilegesRepo (which in turn delegates to ProjectsPrivilegesRepo
 * for new-style WGs).
 */
final class WorksService
{
	private readonly WorksRepo $worksRepo;
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->worksRepo = new WorksRepo($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<Work>
	 */
	public function getOne(
		string $userId,
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, worksId:{worksId})",
			[
				'userId' => $userId,
				'worksId' => $worksId,
			],
		);

		return $this->worksRepo->selectWorkOne($userId, $worksId);
	}

	/**
	 * @return RetValueOrError<array<Work>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $workGroupsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, workGroupsId:{workGroupsId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'workGroupsId' => $workGroupsId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->worksRepo->selectWorkPageTotalCount(
			workGroupsId: $workGroupsId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->worksRepo->selectWorkPage(
			workGroupsId: $workGroupsId,
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
	 * @return RetValueOrError<array<Work>>
	 */
	public function create(
		UuidInterface $workGroupsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(workGroupsId:{workGroupsId}, userId:{userId})",
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $userId,
			],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $d) {
				$worksId = Uuid::uuid7();
				$insertResult = $this->worksRepo->insertWork(
					worksId: $worksId,
					workGroupsId: $workGroupsId,
					owner: $userId,
					name: $d->name,
					description: $d->description,
					affectDate: $d->affect_date,
					affixContentType: $d->affix_content_type,
					remarks: $d->remarks,
					hasETrainTimetable: $d->has_e_train_timetable ?? false,
					eTrainTimetableContentType: $d->e_train_timetable_content_type,
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $worksId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create work: {exception}",
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

		$selectListResult = $this->worksRepo->selectListByIds($userId, $insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->works_id] = $m;
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
	 * @return RetValueOrError<Work>
	 */
	public function update(
		string $userId,
		UuidInterface $worksId,
		object $body,
		array $requestedKeys,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, worksId:{worksId})",
			[
				'userId' => $userId,
				'worksId' => $worksId,
			],
		);

		$privResult = $this->worksRepo->selectPrivilegeType(
			id: $worksId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// requestedKeys may be an indexed list (from the API handler) or an
		// associative array (from direct-service test callers).
		$hasKey = fn (string $k): bool => in_array($k, $requestedKeys) || array_key_exists($k, $requestedKeys);
		$hasName = $hasKey('name');
		$hasDescription = $hasKey('description');
		$hasAffectDate = $hasKey('affect_date');
		$hasAffixContentType = $hasKey('affix_content_type');
		$hasRemarks = $hasKey('remarks');
		$hasHasETrainTimetable = $hasKey('has_e_train_timetable');
		$hasETrainTimetableContentType = $hasKey('e_train_timetable_content_type');

		$updateResult = $this->worksRepo->updateWork(
			worksId: $worksId,
			name: $hasName ? $body->name : null,
			hasName: $hasName,
			description: $hasDescription ? $body->description : null,
			hasDescription: $hasDescription,
			affectDate: $hasAffectDate ? $body->affect_date : null,
			hasAffectDate: $hasAffectDate,
			affixContentType: $hasAffixContentType ? $body->affix_content_type : null,
			hasAffixContentType: $hasAffixContentType,
			remarks: $hasRemarks ? $body->remarks : null,
			hasRemarks: $hasRemarks,
			hasETrainTimetable: $hasHasETrainTimetable ? $body->has_e_train_timetable : null,
			hasHasETrainTimetable: $hasHasETrainTimetable,
			eTrainTimetableContentType: $hasETrainTimetableContentType ? $body->e_train_timetable_content_type : null,
			hasETrainTimetableContentType: $hasETrainTimetableContentType,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->worksRepo->selectWorkOne($userId, $worksId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, worksId:{worksId})",
			[
				'userId' => $userId,
				'worksId' => $worksId,
			],
		);

		$privResult = $this->worksRepo->selectPrivilegeType(
			id: $worksId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// Trains and TimetableRows are NOT cascaded in-request by design (M6); orphans are
		// reclaimed out-of-band by bin/cleanup-orphans.php (run via cron).
		// Note: this supersedes the §8 in-request-cascade expectation for this pair.
		return $this->worksRepo->deleteWork(
			worksId: $worksId,
		);
	}
}
