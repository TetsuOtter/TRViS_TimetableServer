<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKey;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\InviteKeysRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Service for InviteKey CRUD + use. Faithful port of legacy InviteKeysService.
 *
 * Privilege model: InviteKey is a bearer-capability (UUID = capability token).
 * - createInviteKey, selectInviteKey, selectInviteKeyListWithWorkGroupsId,
 *   disableInviteKey all require WorkGroup `admin`.
 * - selectInviteKeyListWithOwnerUid is owner-only (no privilege gate).
 * - useInviteKey requires a signed-in user; writes to projects_privileges.
 *
 * §7: getUserIdOrAnonymous returns a non-null string in the new backend, so
 * the dead `?? Constants::UID_ANONYMOUS` null-coalesce is NOT carried forward.
 */
final class InviteKeysService
{
	private readonly InviteKeysRepo $inviteKeysRepo;
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly WorkGroupsRepo $workGroupsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->inviteKeysRepo = new InviteKeysRepo($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->workGroupsRepo = new WorkGroupsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	public function createInviteKey(
		UuidInterface $workGroupId,
		string $owner,
		InviteKey $inviteKey,
	): RetValueOrError {
		$this->logger->debug(
			"createInviteKey workGroupId: {workGroupId}, owner: {owner}",
			[
				'workGroupId' => $workGroupId,
				'owner' => $owner,
			]
		);

		$ownerPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $workGroupId,
			userId: $owner,
			includeAnonymous: true,
			selectForUpdate: false,
		);
		if ($ownerPrivilegeType->isError) {
			return $ownerPrivilegeType;
		}

		if (!$ownerPrivilegeType->value->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				"You don't have enough privilege to create InviteKey",
			);
		}

		$this->db->beginTransaction();

		try {
			$inviteKeyId = Uuid::uuid7();
			$this->logger->debug(
				"createInviteKey inviteKeyId: {inviteKeyId}",
				['inviteKeyId' => $inviteKeyId]
			);
			$insertInviteKeyResult = $this->inviteKeysRepo->insertInviteKey(
				inviteKeyId: $inviteKeyId,
				workGroupId: $workGroupId,
				owner: $owner,
				inviteKey: $inviteKey,
			);
			if ($insertInviteKeyResult->isError) {
				$this->db->rollBack();
				return $insertInviteKeyResult;
			}

			$this->db->commit();
			return $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
			);
		} catch (\Throwable $th) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			$this->logger->error(
				"Unknown error: {exception}",
				["exception" => $th]
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Unknown error - " . $th->getCode(),
			);
		}
	}

	public function selectInviteKey(
		UuidInterface $inviteKeyId,
		string $userId,
	): RetValueOrError {
		$inviteKeyData = $this->inviteKeysRepo->selectInviteKey(
			inviteKeyId: $inviteKeyId,
		);
		if ($inviteKeyData->isError) {
			return $inviteKeyData;
		}

		// InviteKey は UUID を知っていれば使える bearer-capability 的な存在のため、
		// 個別取得 (privilege_type / work_groups_id の開示) は所属WorkGroupの
		// admin のみに限定する (disableInviteKey / selectInviteKeyListWithWorkGroupsId と同じゲート)。
		$privilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $inviteKeyData->value->work_groups_id,
			userId: $userId,
			includeAnonymous: true,
			selectForUpdate: false,
		);
		if ($privilegeType->isError) {
			return $privilegeType;
		}
		if (!$privilegeType->value->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}
		if (!$privilegeType->value->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// GET の権限不足は存在を秘匿するため 404 に統一（非memberと同じ応答）。
			return Utils::errWorkGroupNotFound();
		}

		return $inviteKeyData;
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
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: true,
			selectForUpdate: false,
		);

		if ($privilegeType->isError) {
			return $privilegeType;
		}
		if (!$privilegeType->value->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// GET の権限不足は存在を秘匿するため 404 に統一（非memberと同じ応答）。
			return Utils::errWorkGroupNotFound();
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

		try {
			// 将来的にuse_limitを適用したいため、transaction内でselectしておく
			$inviteKeyData = $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->db->rollBack();
				return $inviteKeyData;
			}

			// H1: enforce the invite-key lifecycle at the redemption path.
			// selectInviteKey only filters `deleted_at IS NULL` (it is shared
			// with owner/admin view paths that must still see disabled/expired
			// keys), so without this gate an explicitly disabled, not-yet-valid
			// or expired key stays redeemable and the granted
			// projects_privileges row is permanent — the creator's revocation
			// is ignored at the one place it is security-relevant. Mirrors the
			// selectInviteKeyList window predicate; 404 so key state is not
			// leaked.
			$inviteKey = $inviteKeyData->value;
			$now = Utils::getUtcNow();
			if (
				$inviteKey->disabled_at !== null
				|| ($inviteKey->valid_from !== null && $now < $inviteKey->valid_from)
				|| ($inviteKey->expires_at !== null && $inviteKey->expires_at < $now)
			) {
				$this->logger->warning(
					"useInviteKey: rejected non-redeemable key (disabled/not-yet-valid/expired) inviteKeyId: {inviteKeyId}",
					['inviteKeyId' => $inviteKeyId],
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_NOT_FOUND,
					"InviteKey not found",
				);
			}

			$workGroupId = $inviteKeyData->value->work_groups_id;
			$privilegeType = $inviteKeyData->value->privilege_type;
			$currentPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
				id: $workGroupId,
				userId: $userId,
				includeAnonymous: false,
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
				// H8(b): redeeming a key you already match is an idempotent
				// success, not an error. Return the target WorkGroup as a 200
				// body so the contract (200 -> WorkGroup) holds on this exit.
				return $this->workGroupsRepo->selectWorkGroupOne(
					userId: $userId,
					workGroupId: $workGroupId,
				);
			}

			$this->logger->debug(
				"useInviteKey requestedPrivilegeType: {requestedPrivilegeType}, currentPrivilegeType: {currentPrivilegeType}",
				[
					'requestedPrivilegeType' => $privilegeType,
					'currentPrivilegeType' => $currentPrivilegeType->value,
				]
			);
			// Project = 権限ルート: 招待キーで付与する権限は所属Projectの
			// projects_privileges に書き込む
			$projectsIdResult = $this->workGroupsRepo->selectProjectsIdByWorkGroupsId($workGroupId);
			if ($projectsIdResult->isError) {
				$this->db->rollBack();
				return $projectsIdResult;
			}
			$projectsId = $projectsIdResult->value;

			if ($isCreateNew) {
				$execResult = $this->projectsPrivilegesRepo->insert(
					projectsId: $projectsId,
					privilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			} else {
				$execResult = $this->projectsPrivilegesRepo->changeType(
					projectsId: $projectsId,
					newPrivilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			}

			if ($execResult->isError) {
				$this->logger->warning('apply invite key failed');
				$this->db->rollBack();
				return $execResult;
			}
			$this->logger->warning('apply invite key success');
			$this->db->commit();
			// H8(b): the contract is 200 -> WorkGroup. The privileges repo
			// returns RetValueOrError<null> on success, so re-read the joined
			// WorkGroup (now visible via the just-committed privilege) and
			// return it as the response body.
			return $this->workGroupsRepo->selectWorkGroupOne(
				userId: $userId,
				workGroupId: $workGroupId,
			);
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

		try {
			$inviteKeyData = $this->inviteKeysRepo->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->db->rollBack();
				return $inviteKeyData;
			}

			$inviteKeyValue = $inviteKeyData->value;
			$currentUserPrivilegeType = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
				userId: $userId,
				id: $inviteKeyValue->work_groups_id,
				includeAnonymous: true,
				selectForUpdate: true,
			);
			if ($currentUserPrivilegeType->isError) {
				$this->db->rollBack();
				return $currentUserPrivilegeType;
			}
			if (!$currentUserPrivilegeType->value->hasPrivilege(InviteKeyPrivilegeType::read)) {
				$this->logger->warning(
					"disableInviteKey: not enough privilege (currentUserPrivilegeType: {currentUserPrivilegeType})",
					['currentUserPrivilegeType' => $currentUserPrivilegeType->value]
				);
				$this->db->rollBack();
				return Utils::errWorkGroupNotFound();
			}
			if (!$currentUserPrivilegeType->value->hasPrivilege(InviteKeyPrivilegeType::admin)) {
				$this->logger->warning(
					"disableInviteKey: not enough privilege (currentUserPrivilegeType: {currentUserPrivilegeType})",
					['currentUserPrivilegeType' => $currentUserPrivilegeType->value]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					"You don't have enough privilege to disable this InviteKey",
				);
			}

			if ($inviteKeyValue->disabled_at !== null) {
				$this->logger->info(
					"disableInviteKey: already disabled (inviteKeyId: {inviteKeyId})",
					['inviteKeyId' => $inviteKeyId]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey already disabled",
				);
			}
			$now = Utils::getUtcNow();
			if ($inviteKeyValue->valid_from != null && $now < $inviteKeyValue->valid_from) {
				$this->logger->info(
					"disableInviteKey: not valid yet (inviteKeyId: {inviteKeyId}, validFrom: {validFrom}, now: {now})",
					[
						'inviteKeyId' => $inviteKeyId,
						'validFrom' => $inviteKeyValue->valid_from,
						'now' => $now,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey is not valid yet",
				);
			}
			if ($inviteKeyValue->expires_at != null && $inviteKeyValue->expires_at < $now) {
				$this->logger->info(
					"disableInviteKey: already expired (inviteKeyId: {inviteKeyId}, expiresAt: {expiresAt}, now: {now})",
					[
						'inviteKeyId' => $inviteKeyId,
						'expiresAt' => $inviteKeyValue->expires_at,
						'now' => $now,
					]
				);
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey is already expired",
				);
			}

			$disableInviteKeyResult = $this->inviteKeysRepo->disableInviteKey(
				inviteKeyId: $inviteKeyId,
				userId: $userId,
			);
			if ($disableInviteKeyResult->isError) {
				$this->db->rollBack();
				return $disableInviteKeyResult;
			}
			$this->db->commit();
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
