<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\repo\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\WorkGroups;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivileges;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorkGroupsService
{
	private readonly WorkGroups $workGroupsRepo;
	private readonly WorkGroupsPrivileges $workGroupsPrivilegesRepo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsRepo = new WorkGroups($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivileges($db, $logger);
	}

	public function selectWorkGroupOne(
		UuidInterface $workGroupsId,
		?string $currentUserId
	): RetValueOrError {
		$this->logger->debug(
			"selectOne workGroupsId: {workGroupsId}, currentUserId: {currentUserId}",
			[
				'workGroupsId' => $workGroupsId,
				'currentUserId' => $currentUserId,
			],
		);

		// 本当は権限チェックはJOINを使ってやるべきだが、面倒なので別クエリでやる
		// (頻繁に使われるAPIじゃないし、そこまでパフォーマンスに影響はないはず)
		$selectPriviledeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $currentUserId ?? Constants::UID_ANONUMOUS,
			includeAnonymous: true,
		);
		if ($selectPriviledeTypeResult->isError) {
			return $selectPriviledeTypeResult;
		}

		$privilegeType = $selectPriviledeTypeResult->value;
		$this->logger->debug("selectOne privilegeType: {privilegeType}", [
			'workGroupsId' => $workGroupsId,
			'privilegeType' => $privilegeType,
		]);
		if ($privilegeType < InviteKeyPrivilegeType::read) {
			// 権限不足の場合は、404を返す
			return RetValueOrError::withError(
				Constants::HTTP_NOT_FOUND,
				'Work group not found',
			);
		}

		return $this->workGroupsRepo->selectWorkGroupOne($workGroupsId);
	}

	public function selectWorkGroupPage(
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(userId:{userId}, pageFrom1:{page}, perPage:{perPage}, topId:{topId})",
			[
				'userId' => $userId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		return $this->workGroupsRepo->selectWorkGroupPage(
			userId: $userId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	public function createWorkGroup(
		string $userId,
		string $name,
		string $description,
	): RetValueOrError {
		$this->logger->debug(
			"createWorkGroup(userId:{userId}, name:{name}, description:{description})",
			[
				'userId' => $userId,
				'name' => $name,
				'description' => $description,
			],
		);

		$this->db->beginTransaction();

		try {
			$workGroupsId = Uuid::uuid7();
			$insertResult = $this->workGroupsRepo->insertWorkGroup(
				workGroupId: $workGroupsId,
				owner: $userId,
				name: $name,
				description: $description,
			);
			if ($insertResult->isError) {
				$this->db->rollBack();
				return $insertResult;
			}

			$insertPrivilegeResult = $this->workGroupsPrivilegesRepo->insert(
				workGroupsId: $workGroupsId,
				userId: $userId,
				privilegeType: InviteKeyPrivilegeType::admin,
			);
			if ($insertPrivilegeResult->isError) {
				$this->db->rollBack();
				return $insertPrivilegeResult;
			}

			$this->db->commit();
			$selectWorkGroupOneResult = $this->workGroupsRepo->selectWorkGroupOne($workGroupsId);
			if ($selectWorkGroupOneResult->isError) {
				return $selectWorkGroupOneResult;
			} else {
				return RetValueOrError::withValue(
					$selectWorkGroupOneResult->value,
					Constants::HTTP_CREATED,
				);
			}
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create work group: {exception}",
				[
					'exception' => $e,
				],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'unknown error - ',
				$e->getCode(),
			);
		}
	}

	public function updateWorkGroup(
		UuidInterface $workGroupsId,
		string $userId,
		?string $name,
		?string $description,
	): RetValueOrError {
		$this->logger->debug(
			"updateWorkGroup(workGroupsId:{workGroupsId}, userId:{userId}, name:{name}, description:{description})",
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $userId,
				'name' => $name,
				'description' => $description,
			],
		);

		$selectPriviledeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $userId,
			includeAnonymous: false,
		);
		if ($selectPriviledeTypeResult->isError) {
			return $selectPriviledeTypeResult;
		}

		$userPrivilegeType = $selectPriviledeTypeResult->value;
		$this->logger->debug("updateWorkGroup userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if ($userPrivilegeType < InviteKeyPrivilegeType::read) {
			// READ権限不足の場合は、404を返す
			return RetValueOrError::withError(
				Constants::HTTP_NOT_FOUND,
				'Work group not found',
			);
		}
		if ($userPrivilegeType < InviteKeyPrivilegeType::write) {
			// READ権限がありWRITE権限がない場合は、403を返す
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to update this work group',
			);
		}
		$updateWorkGroupResult = $this->workGroupsRepo->updateWorkGroup(
			workGroupId: $workGroupsId,
			name: $name,
			description: $description,
		);
		if ($updateWorkGroupResult->isError) {
			return $updateWorkGroupResult;
		}

		return $this->workGroupsRepo->selectWorkGroupOne($workGroupsId);
	}

	public function deleteWorkGroup(
		UuidInterface $workGroupsId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteWorkGroup(workGroupsId:{workGroupsId}, userId:{userId})",
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $userId,
			],
		);

		$selectPriviledeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $userId,
			includeAnonymous: false,
		);
		if ($selectPriviledeTypeResult->isError) {
			return $selectPriviledeTypeResult;
		}

		$userPrivilegeType = $selectPriviledeTypeResult->value;
		$this->logger->debug("deleteWorkGroup userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if ($userPrivilegeType < InviteKeyPrivilegeType::read) {
			// READ権限不足の場合は、404を返す
			return RetValueOrError::withError(
				Constants::HTTP_NOT_FOUND,
				'Work group not found',
			);
		}
		if ($userPrivilegeType < InviteKeyPrivilegeType::admin) {
			// READ権限がありADMIN権限がない場合は、403を返す
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to delete this work group',
			);
		}

		return $this->workGroupsRepo->deleteWorkGroup($workGroupsId);
	}
}
