<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\InviteKeysRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorkGroupsService
{
	private readonly WorkGroupsRepo $workGroupsRepo;
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly InviteKeysRepo $inviteKeysRepo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsRepo = new WorkGroupsRepo($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->inviteKeysRepo = new InviteKeysRepo($db, $logger);
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

		return $this->workGroupsRepo->selectWorkGroupOne($currentUserId, $workGroupsId);
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
			$selectWorkGroupOneResult = $this->workGroupsRepo->selectWorkGroupOne($userId, $workGroupsId);
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

		$selectPrivilegeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($selectPrivilegeTypeResult->isError) {
			return $selectPrivilegeTypeResult;
		}

		$userPrivilegeType = $selectPrivilegeTypeResult->value;
		$this->logger->debug("updateWorkGroup userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			// READ権限不足の場合は、404を返す
			return Utils::errWorkGroupNotFound();
		}
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::write)) {
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

		return $this->workGroupsRepo->selectWorkGroupOne($userId, $workGroupsId);
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

		$selectPrivilegeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($selectPrivilegeTypeResult->isError) {
			return $selectPrivilegeTypeResult;
		}

		$userPrivilegeType = $selectPrivilegeTypeResult->value;
		$this->logger->debug("deleteWorkGroup userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			// READ権限不足の場合は、404を返す
			return Utils::errWorkGroupNotFound();
		}
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// READ権限がありADMIN権限がない場合は、403を返す
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to delete this work group',
			);
		}

		$this->db->beginTransaction();
		try {
			$now = Utils::getUtcNow();
			$this->logger->debug("deleteWorkGroup now: {now}", [
				'now' => $now,
			]);

			$deleteWorkGroupResult = $this->workGroupsRepo->deleteWorkGroup(
				workGroupId: $workGroupsId,
				deletedAt: $now,
			);
			if ($deleteWorkGroupResult->isError) {
				$this->db->rollBack();
				return $deleteWorkGroupResult;
			}

			$deletePrivilegeResult = $this->workGroupsPrivilegesRepo->deleteByWorkGroupId(
				workGroupsId: $workGroupsId,
				deletedAt: $now,
			);
			if ($deletePrivilegeResult->isError && $deletePrivilegeResult->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->db->rollBack();
				return $deletePrivilegeResult;
			}

			$deleteInviteKeyResult = $this->inviteKeysRepo->deleteByWorkGroupId(
				workGroupsId: $workGroupsId,
				deletedAt: $now,
			);
			if ($deleteInviteKeyResult->isError && $deleteInviteKeyResult->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->db->rollBack();
				return $deleteInviteKeyResult;
			}

			$this->db->commit();
			$this->logger->info(
				"deleteWorkGroup({workGroupsId}) by user:'{userId}' success",
				[
					'workGroupsId' => $workGroupsId,
					'userId' => $userId,
				],
			);
			return RetValueOrError::withValue(null);
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to delete work group: {exception}",
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

	public function getPrivileges(
		UuidInterface $workGroupsId,
		string $senderUserId,
		?string $targetUserId,
	): RetValueOrError {
		$this->logger->debug(
			"getPrivileges(workGroupsId:{workGroupsId}, senderUserId:{userId}, targetUserId:{targetUserId})",
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $senderUserId,
				'targetUserId' => $targetUserId,
			],
		);

		if (is_null($targetUserId)) {
			$targetUserId = $senderUserId;
		}

		$senderPrivilegeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeTypeObject(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeTypeResult->isError) {
			$this->logger->warning(
				'returning error: {statusCode} {errorMsg}',
				[
					'statusCode' => $senderPrivilegeTypeResult->statusCode,
					'errorMsg' => $senderPrivilegeTypeResult->errorMsg,
				],
			);
			return $senderPrivilegeTypeResult;
		}

		$senderPrivilegeType = $senderPrivilegeTypeResult->value->privilege_type;
		$this->logger->debug("getPrivileges userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $senderPrivilegeType,
		]);

		if ($senderUserId === $targetUserId) {
			// READ権限不足の場合は、404を返す
			if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
				return Utils::errWorkGroupNotFound();
			} else {
				return $senderPrivilegeTypeResult;
			}
		}

		if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// adminでない場合、他のユーザーの権限を取得することはできない
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to get privileges of other users'
			);
		}

		$targetPrivilegeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeTypeObject(
			workGroupsId: $workGroupsId,
			userId: $targetUserId,
			includeAnonymous: true,
		);
		if ($targetPrivilegeTypeResult->isError && $targetPrivilegeTypeResult->statusCode === Constants::HTTP_NOT_FOUND) {
			return RetValueOrError::withError(
				Constants::HTTP_NOT_FOUND,
				'Privilege not found',
			);
		}
		return $targetPrivilegeTypeResult;
	}

	public function updatePrivilege(
		UuidInterface $workGroupsId,
		string $senderUserId,
		?string $targetUserId,
		InviteKeyPrivilegeType $newPrivilegeType
	): RetValueOrError {
		$this->logger->debug(
			'updatePrivilege(workGroupsId:{workGroupsId}, senderUserId:{userId}, targetUserId:{targetUserId}, newPrivilegeType:{newPrivilegeType})',
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $senderUserId,
				'targetUserId' => $targetUserId,
				'newPrivilegeType' => $newPrivilegeType,
			],
		);

		if (is_null($targetUserId)) {
			$targetUserId = $senderUserId;
		}

		$senderPrivilegeTypeResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeTypeResult->isError) {
			$this->logger->warning(
				'returning error: {statusCode} {errorMsg}',
				[
					'statusCode' => $senderPrivilegeTypeResult->statusCode,
					'errorMsg' => $senderPrivilegeTypeResult->errorMsg,
				],
			);
			return $senderPrivilegeTypeResult;
		}

		$senderPrivilegeType = $senderPrivilegeTypeResult->value;
		$this->logger->debug("getPrivileges(workGroupsId: {workGroupsId}) userPrivilegeType: {userPrivilegeType}", [
			'workGroupsId' => $workGroupsId,
			'userPrivilegeType' => $senderPrivilegeType,
		]);

		if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}

		if ($senderUserId === $targetUserId) {
			if ($senderPrivilegeType->value < $newPrivilegeType->value) {
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					'You don\'t have permission to update your privilege to higher than your current privilege',
				);
			}
		} else if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// adminでない場合、他のユーザーの権限を編集することはできない
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to update privileges of other users'
			);
		}

		$changePrivilegeResult = $this->workGroupsPrivilegesRepo->changeType(
			workGroupsId: $workGroupsId,
			newPrivilegeType: $newPrivilegeType,
			userId: $targetUserId,
		);
		if ($changePrivilegeResult->isError) {
			if ($changePrivilegeResult->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->logger->warning(
					'returning error: {statusCode} {errorMsg}',
					[
						'statusCode' => $changePrivilegeResult->statusCode,
						'errorMsg' => $changePrivilegeResult->errorMsg,
					],
				);
				return $changePrivilegeResult;
			}

			$insertPrivilegeResult = $this->workGroupsPrivilegesRepo->insert(
				workGroupsId: $workGroupsId,
				userId: $targetUserId,
				privilegeType: $newPrivilegeType,
			);

			if ($insertPrivilegeResult->isError) {
				$this->logger->warning(
					'returning error: {statusCode} {errorMsg}',
					[
						'statusCode' => $insertPrivilegeResult->statusCode,
						'errorMsg' => $insertPrivilegeResult->errorMsg,
					],
				);
				return $insertPrivilegeResult;
			}
		}

		return $this->workGroupsPrivilegesRepo->selectPrivilegeTypeObject(
			workGroupsId: $workGroupsId,
			userId: $targetUserId,
			includeAnonymous: false,
		);
	}
}
