<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\ProjectsRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectsService
{
	private readonly ProjectsRepo $projectsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->projectsRepo = new ProjectsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	public function selectProjectOne(
		UuidInterface $projectsId,
		?string $currentUserId
	): RetValueOrError {
		$this->logger->debug(
			"selectOne projectsId: {projectsId}, currentUserId: {currentUserId}",
			[
				'projectsId' => $projectsId,
				'currentUserId' => $currentUserId,
			],
		);

		return $this->projectsRepo->selectProjectOne($currentUserId, $projectsId);
	}

	public function selectProjectPage(
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

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->projectsRepo->selectProjectPageTotalCount(
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->projectsRepo->selectProjectPage(
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

	public function createProject(
		string $userId,
		string $name,
		string $description,
	): RetValueOrError {
		$this->logger->debug(
			"createProject(userId:{userId}, name:{name}, description:{description})",
			[
				'userId' => $userId,
				'name' => $name,
				'description' => $description,
			],
		);

		$this->db->beginTransaction();

		try {
			$projectsId = Uuid::uuid7();
			$insertResult = $this->projectsRepo->insertProject(
				projectId: $projectsId,
				owner: $userId,
				name: $name,
				description: $description,
			);
			if ($insertResult->isError) {
				$this->db->rollBack();
				return $insertResult;
			}

			$insertPrivilegeResult = $this->projectsPrivilegesRepo->insert(
				projectsId: $projectsId,
				userId: $userId,
				privilegeType: InviteKeyPrivilegeType::admin,
			);
			if ($insertPrivilegeResult->isError) {
				$this->db->rollBack();
				return $insertPrivilegeResult;
			}

			$selectProjectOneResult = $this->projectsRepo->selectProjectOne($userId, $projectsId);
			if ($selectProjectOneResult->isError) {
				$this->db->rollBack();
				return $selectProjectOneResult;
			}

			$this->db->commit();
			return RetValueOrError::withValue(
				$selectProjectOneResult->value,
				Constants::HTTP_CREATED,
			);
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create project: {exception}",
				[
					'exception' => $e,
				],
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
	}

	public function updateProject(
		UuidInterface $projectsId,
		string $userId,
		?string $name,
		?string $description,
	): RetValueOrError {
		$this->logger->debug(
			"updateProject(projectsId:{projectsId}, userId:{userId}, name:{name}, description:{description})",
			[
				'projectsId' => $projectsId,
				'userId' => $userId,
				'name' => $name,
				'description' => $description,
			],
		);

		$selectPrivilegeTypeResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($selectPrivilegeTypeResult->isError) {
			return $selectPrivilegeTypeResult;
		}

		$userPrivilegeType = $selectPrivilegeTypeResult->value;
		$this->logger->debug("updateProject userPrivilegeType: {userPrivilegeType}", [
			'projectsId' => $projectsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			// READ権限不足の場合は、404を返す
			return Utils::errProjectNotFound();
		}
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::write)) {
			// READ権限がありWRITE権限がない場合は、403を返す
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to update this project',
			);
		}
		$updateProjectResult = $this->projectsRepo->updateProject(
			projectId: $projectsId,
			name: $name,
			description: $description,
		);
		if ($updateProjectResult->isError) {
			return $updateProjectResult;
		}

		return $this->projectsRepo->selectProjectOne($userId, $projectsId);
	}

	public function deleteProject(
		UuidInterface $projectsId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteProject(projectsId:{projectsId}, userId:{userId})",
			[
				'projectsId' => $projectsId,
				'userId' => $userId,
			],
		);

		$selectPrivilegeTypeResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($selectPrivilegeTypeResult->isError) {
			return $selectPrivilegeTypeResult;
		}

		$userPrivilegeType = $selectPrivilegeTypeResult->value;
		$this->logger->debug("deleteProject userPrivilegeType: {userPrivilegeType}", [
			'projectsId' => $projectsId,
			'userPrivilegeType' => $userPrivilegeType,
		]);
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			// READ権限不足の場合は、404を返す
			return Utils::errProjectNotFound();
		}
		if (!$userPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// READ権限がありADMIN権限がない場合は、403を返す
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to delete this project',
			);
		}

		$this->db->beginTransaction();
		try {
			$now = Utils::getUtcNow();
			$this->logger->debug("deleteProject now: {now}", [
				'now' => $now,
			]);

			$deleteProjectResult = $this->projectsRepo->deleteProject(
				projectId: $projectsId,
				deletedAt: $now,
			);
			if ($deleteProjectResult->isError) {
				$this->db->rollBack();
				return $deleteProjectResult;
			}

			$deletePrivilegeResult = $this->projectsPrivilegesRepo->deleteByProjectId(
				projectsId: $projectsId,
				deletedAt: $now,
			);
			if ($deletePrivilegeResult->isError && $deletePrivilegeResult->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->db->rollBack();
				return $deletePrivilegeResult;
			}

			$this->db->commit();
			$this->logger->info(
				"deleteProject({projectsId}) by user:'{userId}' success",
				[
					'projectsId' => $projectsId,
					'userId' => $userId,
				],
			);
			return RetValueOrError::withValue(null);
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to delete project: {exception}",
				[
					'exception' => $e,
				],
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
	}

	public function getPrivileges(
		UuidInterface $projectsId,
		string $senderUserId,
		?string $targetUserId,
	): RetValueOrError {
		$this->logger->debug(
			"getPrivileges(projectsId:{projectsId}, senderUserId:{userId}, targetUserId:{targetUserId})",
			[
				'projectsId' => $projectsId,
				'userId' => $senderUserId,
				'targetUserId' => $targetUserId,
			],
		);

		if (is_null($targetUserId)) {
			$targetUserId = $senderUserId;
		}

		$senderPrivilegeTypeResult = $this->projectsPrivilegesRepo->selectPrivilegeTypeObject(
			projectsId: $projectsId,
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
			'projectsId' => $projectsId,
			'userPrivilegeType' => $senderPrivilegeType,
		]);

		if ($senderUserId === $targetUserId) {
			// READ権限不足の場合は、404を返す
			if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
				return Utils::errProjectNotFound();
			} else {
				return $senderPrivilegeTypeResult;
			}
		}

		if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// adminでない場合、他のユーザーの権限を取得することはできない。
			// GET の権限不足は存在を秘匿するため 404 に統一（非memberと同じ応答）。
			return Utils::errProjectNotFound();
		}

		$targetPrivilegeTypeResult = $this->projectsPrivilegesRepo->selectPrivilegeTypeObject(
			projectsId: $projectsId,
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
		UuidInterface $projectsId,
		string $senderUserId,
		?string $targetUserId,
		InviteKeyPrivilegeType $newPrivilegeType
	): RetValueOrError {
		$this->logger->debug(
			'updatePrivilege(projectsId:{projectsId}, senderUserId:{userId}, targetUserId:{targetUserId}, newPrivilegeType:{newPrivilegeType})',
			[
				'projectsId' => $projectsId,
				'userId' => $senderUserId,
				'targetUserId' => $targetUserId,
				'newPrivilegeType' => $newPrivilegeType,
			],
		);

		if (is_null($targetUserId)) {
			$targetUserId = $senderUserId;
		}

		$senderPrivilegeTypeResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
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
		$this->logger->debug("getPrivileges(projectsId: {projectsId}) userPrivilegeType: {userPrivilegeType}", [
			'projectsId' => $projectsId,
			'userPrivilegeType' => $senderPrivilegeType,
		]);

		if (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errProjectNotFound();
		}

		if ($senderUserId === $targetUserId) {
			// H2: the self-update ceiling MUST be the caller's PERSONAL
			// privilege only. $senderPrivilegeType above is the MAX of the
			// personal row and the public (uid='') row; using it lets a user
			// with no personal row but a public read/write row pass this guard,
			// fall through changeType -> 404 -> insert(), and mint a PERMANENT
			// personal projects_privileges row at the public level (identity
			// laundering: it survives later revocation/downgrade of the public
			// row). Recompute personal-only: no personal row => forbid (no
			// minting from anonymous capability); otherwise only allow
			// same-or-lower than the existing personal privilege.
			$personalPrivilegeResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
				id: $projectsId,
				userId: $senderUserId,
				includeAnonymous: false,
			);
			if ($personalPrivilegeResult->isError) {
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					'You don\'t have permission to update your privilege',
				);
			}
			if ($personalPrivilegeResult->value->value < $newPrivilegeType->value) {
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					'You don\'t have permission to update your privilege to higher than your current privilege',
				);
			}
		} elseif (!$senderPrivilegeType->hasPrivilege(InviteKeyPrivilegeType::admin)) {
			// adminでない場合、他のユーザーの権限を編集することはできない
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You don\'t have permission to update privileges of other users'
			);
		}

		$changePrivilegeResult = $this->projectsPrivilegesRepo->changeType(
			projectsId: $projectsId,
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

			$insertPrivilegeResult = $this->projectsPrivilegesRepo->insert(
				projectsId: $projectsId,
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

		return $this->projectsPrivilegesRepo->selectPrivilegeTypeObject(
			projectsId: $projectsId,
			userId: $targetUserId,
			includeAnonymous: false,
		);
	}
}
