<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\repo\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\InviteKeys;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivileges;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class InviteKeysService
{
	private readonly InviteKeys $inviteKeysRepo;
	private readonly WorkGroupsPrivileges $workGroupsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->inviteKeysRepo = new InviteKeys($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivileges($db, $logger);
	}

	public function createInviteKey(
		UuidInterface $workGroupId,
		string $owner,
		string $description,
		?\DateTimeInterface $validFrom,
		?\DateTimeInterface $expiresAt,
		?int $useLimit,
		InviteKeyPrivilegeType $privilegeType,
	): RetValueOrError {
		$this->logger->debug(
			"createInviteKey workGroupId: {workGroupId}, owner: {owner}, description: {description}, validFrom: {validFrom}, expiresAt: {expiresAt}, useLimit: {useLimit}, privilegeType: {privilegeType}",
			[
				'workGroupId' => $workGroupId,
				'owner' => $owner,
				'description' => $description,
				'validFrom' => $validFrom,
				'expiresAt' => $expiresAt,
				'useLimit' => $useLimit,
				'privilegeType' => $privilegeType,
			]
		);

		$ownerPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupId,
			userId: $owner,
			// ここでチェックした直後に権限が変更されても、その変更は無視する
			// ロックして権限の変更を遅らせても、結局はInviteKeyの作成後に権限が変更されることになるため
			selectForUpdate: false,
		);
		if ($ownerPrivilegeType->isError) {
			return $ownerPrivilegeType;
		}

		if ($ownerPrivilegeType->value->value < InviteKeyPrivilegeType::ADMIN->value) {
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				"You don't have enough privilege to create InviteKey",
			);
		}

		$this->db->beginTransaction();

		try
		{
			$inviteKeyId = Uuid::uuid7();
			$this->logger->debug(
				"createInviteKey inviteKeyId: {inviteKeyId}",
				[
					'inviteKeyId' => $inviteKeyId,
				]
			);
			$insertInviteKeyResult = $this->inviteKeysRepo->insertInviteKey(
				inviteKeyId: $inviteKeyId,
				workGroupId: $workGroupId,
				owner: $owner,
				description: $description,
				validFrom: $validFrom,
				expiresAt: $expiresAt,
				useLimit: $useLimit,
				privilegeType: $privilegeType,
			);
			if ($insertInviteKeyResult->isError) {
				$this->db->rollBack();
				return $insertInviteKeyResult;
			}

			$this->db->commit();
			return $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
			);
		}
		catch (\Throwable $th)
		{
			$this->db->rollBack();

			$this->logger->error(
				"Unknown error: {exception}",
				[
					"exception" => $th,
				]
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Unknown error - " . $th->getCode(),
			);
		}
	}

	public function selectInviteKey(
		UuidInterface $inviteKeyId,
	): RetValueOrError {
		return $this->inviteKeysRepo->selectInviteKey(
			inviteKeyId: $inviteKeyId,
		);
	}

	public function selectInviteKeyListWithOwnerUid(
		string $ownerUserId,
		?int $page,
		?int $perPage,
		?UuidInterface $topId,
		bool $includeExpired = false,
		?\DateTime $currentDateTime = null,
	): RetValueOrError {
		return $this->inviteKeysRepo->selectInviteKeyList(
			ownerOrWorkGroupsId: $ownerUserId,
			page: $page,
			perPage: $perPage,
			topId: $topId,
			includeExpired: $includeExpired,
			currentDateTime: $currentDateTime,
		);
	}

	public function selectInviteKeyListWithWorkGroupsId(
		UuidInterface $workGroupsId,
		string $userId,
		?int $page,
		?int $perPage,
		?UuidInterface $topId,
		bool $includeExpired = false,
		?\DateTime $currentDateTime = null,
	): RetValueOrError {
		$privilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $userId,
			selectForUpdate: false,
		);

		if ($privilegeType->isError) {
			return $privilegeType;
		}
		if ($privilegeType->value->value < InviteKeyPrivilegeType::ADMIN->value) {
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				"You don't have enough privilege to get InviteKey list",
			);
		}

		return $this->inviteKeysRepo->selectInviteKeyList(
			ownerOrWorkGroupsId: $workGroupsId,
			page: $page,
			perPage: $perPage,
			topId: $topId,
			includeExpired: $includeExpired,
			currentDateTime: $currentDateTime,
		);
	}

	public function useInviteKey(
		UuidInterface $inviteKeyId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"useInviteKey inviteKeyId: {inviteKeyId}, userId: {userId}",
			[
				'inviteKeyId' => $inviteKeyId,
				'userId' => $userId,
			]
		);

		$this->db->beginTransaction();

		try
		{
			// 将来的にuse_limitを適用したいため、transaction内でselectしておく
			$inviteKeyData = $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->db->rollBack();
				return $inviteKeyData;
			}

			$workGroupId = $inviteKeyData->value->work_groups_id;
			$privilegeType = $inviteKeyData->value->privilege_type;
			$currentPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
				workGroupsId: $workGroupId,
				userId: $userId,
				selectForUpdate: true,
			);
			if ($currentPrivilegeType->isError && $currentPrivilegeType->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->db->rollBack();
				return $currentPrivilegeType;
			}

			$isCreateNew = $currentPrivilegeType->isError;
			if (!$isCreateNew && $privilegeType->value <= $currentPrivilegeType->value->value) {
				$this->logger->warning(
					"useInviteKey: already have the same or higher privilege - current:{currentPrivilegeType}, requested:{requestedPrivilegeType}",
					[
						'currentPrivilegeType' => $currentPrivilegeType->value,
						'requestedPrivilegeType' => $privilegeType,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_OK,
					"You already have the same or higher privilege - " . $currentPrivilegeType->value->name,
				);
			}

			$this->logger->debug(
				"useInviteKey requestedPrivilegeType: {requestedPrivilegeType}, currentPrivilegeType: {currentPrivilegeType}",
				[
					'requestedPrivilegeType' => $privilegeType,
					'currentPrivilegeType' => $currentPrivilegeType->value,
				]
			);
			if ($isCreateNew) {
				$execResult = $this->workGroupsPrivilegesRepo->insert(
					workGroupsId: $workGroupId,
					privilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			} else {
				$execResult = $this->workGroupsPrivilegesRepo->changeType(
					workGroupsId: $workGroupId,
					newPrivilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			}

			if ($execResult->isError) {
				$this->logger->warning('apply invite key failed');
				$this->db->rollBack();
			} else {
				$this->logger->warning('apply invite key success');
				$this->db->commit();
			}
			return $execResult;
		}
		catch (\Throwable $th)
		{
			$this->db->rollBack();

			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $errInfo,
				]
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
				$errCode
			);
		}
	}

	public function disableInviteKey(
		UuidInterface $inviteKeyId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"disableInviteKey inviteKeyId: {inviteKeyId}, userId: {userId}",
			[
				'inviteKeyId' => $inviteKeyId,
				'userId' => $userId,
			]
		);

		$this->db->beginTransaction();

		try
		{
			$inviteKeyData = $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->db->rollBack();
				return $inviteKeyData;
			}

			$inviteKeyData = $inviteKeyData->value;
			if ($inviteKeyData->disabled_at !== null) {
				$this->logger->info(
					"disableInviteKey: already disabled (inviteKeyId: {inviteKeyId})",
					[
						'inviteKeyId' => $inviteKeyId,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey already disabled",
				);
			}
			$now = new \DateTime;
			if ($inviteKeyData->valid_from != null && $now < $inviteKeyData->valid_from) {
				$this->logger->info(
					"disableInviteKey: not valid yet (inviteKeyId: {inviteKeyId}, validFrom: {validFrom}, now: {now})",
					[
						'inviteKeyId' => $inviteKeyId,
						'validFrom' => $inviteKeyData->valid_from,
						'now' => $now,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey is not valid yet",
				);
			}
			if ($inviteKeyData->expires_at != null && $inviteKeyData->expires_at < $now) {
				$this->logger->info(
					"disableInviteKey: already expired (inviteKeyId: {inviteKeyId}, expiresAt: {expiresAt}, now: {now})",
					[
						'inviteKeyId' => $inviteKeyId,
						'expiresAt' => $inviteKeyData->expires_at,
						'now' => $now,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey is already expired",
				);
			}

			$workGroupsId = $inviteKeyData->value->work_groups_id;
			$currentPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
				userId: $userId,
				workGroupsId: $workGroupsId,
				selectForUpdate: true,
			);
			if ($currentPrivilegeType->isError) {
				$this->db->rollBack();
				return $currentPrivilegeType;
			}

			if ($currentPrivilegeType->value->value < InviteKeyPrivilegeType::ADMIN->value) {
				$this->logger->warning(
					"disableInviteKey: not enough privilege (currentPrivilegeType: {currentPrivilegeType})",
					[
						'currentPrivilegeType' => $currentPrivilegeType->value,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					"You don't have enough privilege to disable this InviteKey",
				);
			}

			$disableInviteKeyResult = $this->inviteKeysRepo->disableInviteKey(
				inviteKeyId: $inviteKeyId,
				userId: $userId,
			);
			return $disableInviteKeyResult;
		} catch (\Throwable $th) {
			$this->db->rollBack();
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $errInfo,
				]
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
				$errCode,
			);
		}
	}
}
