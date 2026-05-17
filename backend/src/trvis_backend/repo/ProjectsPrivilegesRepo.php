<?php

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\ProjectsPrivilege;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectsPrivilegesRepo implements IMyRepoSelectPrivilegeType
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function insert(
		UuidInterface $projectsId,
		InviteKeyPrivilegeType $privilegeType = InviteKeyPrivilegeType::none,
		string $userId = '',
		?UuidInterface $inviteKeysId = null,
	): RetValueOrError {
		$this->logger->debug(
			'inserting project privilege (user:{userId}, Project:{projectsId}, InviteKey:{inviteKeysId}, PrivilegeType:{privilegeType})',
			[
				'userId' => $userId,
				'projectsId' => $projectsId,
				'inviteKeysId' => $inviteKeysId,
				'privilegeType' => $privilegeType,
			]
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				INSERT INTO
					projects_privileges
				(
					uid,
					projects_id,
					invite_keys_id,
					privilege_type
				) VALUES (
					:userId,
					:projectsId,
					:inviteKeysId,
					:privilegeType
				)
				;
				SQL
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':projectsId', $projectsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':inviteKeysId', $inviteKeysId?->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':privilegeType', $privilegeType->value, PDO::PARAM_INT);
			$query->execute();
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to insert project  ({errorCode} -> {errorInfo})',
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

	/**
	 * @return RetValueOrError<null>
	 */
	public function changeType(
		UuidInterface $projectsId,
		InviteKeyPrivilegeType $newPrivilegeType = InviteKeyPrivilegeType::none,
		string $userId = '',
		?UuidInterface $inviteKeysId = null,
	): RetValueOrError {
		$this->logger->debug(
			'changing project privilege (user:{userId}, Project:{projectsId}, InviteKey:{inviteKeysId}, NewPrivilegeType:{privilegeType})',
			[
				'userId' => $userId,
				'projectsId' => $projectsId,
				'inviteKeysId' => $inviteKeysId,
				'privilegeType' => $newPrivilegeType,
			]
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					projects_privileges
				SET
					updated_at = CURRENT_TIMESTAMP(),
					invite_keys_id = :inviteKeysId,
					privilege_type = :privilegeType
				WHERE
						uid = :userId
					AND
						projects_id = :projectsId
				;
				SQL
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':projectsId', $projectsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':inviteKeysId', $inviteKeysId?->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':privilegeType', $newPrivilegeType->value, PDO::PARAM_INT);
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->warning(
					'project privileges for specified user not found (user:{userId}, Project:{projectsId})',
					[
						'userId' => $userId,
						'projectsId' => $projectsId,
					]
				);
				return RetValueOrError::withError(
					Constants::HTTP_NOT_FOUND,
					'project privileges for specified user not found'
				);
			}
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to change project  ({errorCode} -> {errorInfo})',
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

	/**
	 * @return RetValueOrError<InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			'selecting project privilege (user:{userId}, Project:{projectsId})',
			[
				'userId' => $userId,
				'projectsId' => $id,
			]
		);

		if ($userId === Constants::UID_ANONYMOUS)
		{
			// リクエスト対象自体がAnonymousの場合は、わざわざOR条件にする必要はない
			$includeAnonymous = false;
		}
		try
		{
			$query = $this->db->prepare(
				'SELECT'
				.
				(<<<SQL
					privilege_type,
					uid,
					invite_keys_id
				FROM
					projects_privileges
				WHERE
					projects_id = :projectsId
				AND
				SQL)
				.
				($includeAnonymous ? ' uid IN (:userId, \'\')' : ' uid = :userId')
				.
				' AND deleted_at IS NULL'
				.
				($selectForUpdate ? ' FOR UPDATE' : '')
				.
				';'
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':projectsId', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0)
			{
				return Utils::errProjectNotFound();
			}

			$privilegeTypeList = $query->fetchAll(PDO::FETCH_ASSOC);
			$maximumPrivilegeTypeValue = InviteKeyPrivilegeType::none->value;
			foreach ($privilegeTypeList as $row)
			{
				$privilegeTypeValue = intval($row['privilege_type']);
				$inviteKeysId = $row['invite_keys_id'];
				$this->logger->debug(
					'privilege type: {privilegeType} (UID:{uid}, InviteKey:{inviteKeysId})',
					[
						'privilegeType' => $privilegeTypeValue,
						'uid' => $row['uid'],
						'inviteKeysId' => is_null($inviteKeysId) ? null : Uuid::fromBytes($inviteKeysId),
					]
				);
				if ($maximumPrivilegeTypeValue < $privilegeTypeValue)
				{
					$maximumPrivilegeTypeValue = $privilegeTypeValue;
				}
			}
			$this->logger->debug(
				'maximum privilege type: {privilegeType}',
				[
					'privilegeType' => $maximumPrivilegeTypeValue,
				]
			);
			return RetValueOrError::withValue(
				InviteKeyPrivilegeType::fromInt($maximumPrivilegeTypeValue)
			);
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to select project  ({errorCode} -> {errorInfo})',
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

	/**
	 * @return RetValueOrError<ProjectsPrivilege>
	 */
	public function selectPrivilegeTypeObject(
		UuidInterface $projectsId,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			'selecting project privilege (user:{userId}, Project:{projectsId}, includeAnonymous:{includeAnonymous}, selectForUpdate:{selectForUpdate})',
			[
				'userId' => $userId,
				'projectsId' => $projectsId,
				'includeAnonymous' => $includeAnonymous ? 'true' : 'false',
				'selectForUpdate' => $selectForUpdate ? 'true' : 'false',
			]
		);

		if ($userId === Constants::UID_ANONYMOUS)
		{
			$this->logger->debug('userId is anonymous, so includeAnonymous is set to false');
			$includeAnonymous = false;
		}
		try
		{
			$query = $this->db->prepare(
				'SELECT'
				.
				(<<<SQL
					uid,
					projects_id,
					invite_keys_id,
					created_at,
					updated_at,
					privilege_type
				FROM
					projects_privileges
				WHERE
					projects_id = :projectsId
				AND
				SQL)
				.
				($includeAnonymous ? ' uid IN (:userId, \'\')' : ' uid = :userId')
				.
				' AND deleted_at IS NULL'
				.
				($selectForUpdate ? ' FOR UPDATE' : '')
				.
				';'
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':projectsId', $projectsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$this->logger->debug(
				'rowCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			if ($query->rowCount() === 0)
			{
				return Utils::errProjectNotFound();
			}

			$privilegeTypeList = $query->fetchAll(PDO::FETCH_ASSOC);
			$maximumPrivilegeTypeValue = InviteKeyPrivilegeType::none->value;
			$privilegeTypeObject = null;
			foreach ($privilegeTypeList as $row)
			{
				$privilegeTypeValue = intval($row['privilege_type']);
				$inviteKeysId = $row['invite_keys_id'];
				$obj = [
					'uid' => $row['uid'],
					'projects_id' => Uuid::fromBytes($row['projects_id']),
					'invite_keys_id' => is_null($inviteKeysId) ? null : Uuid::fromBytes($inviteKeysId),
					'created_at' => Utils::dbDateStrToDateTime($row['created_at']),
					'updated_at'=> Utils::dbDateStrToDateTime($row['updated_at']),
					'privilege_type' => InviteKeyPrivilegeType::fromInt($privilegeTypeValue),
				];
				$this->logger->debug(
					'privilege type: {privilege_type} (UID:{uid}, InviteKey:{invite_keys_id})',
					$obj
				);
				if ($maximumPrivilegeTypeValue < $privilegeTypeValue || is_null($privilegeTypeObject))
				{
					$maximumPrivilegeTypeValue = $privilegeTypeValue;

					$privilegeTypeObject ??= new ProjectsPrivilege();
					$privilegeTypeObject->setData($obj);
				}
			}
			$this->logger->debug(
				'maximum privilege type: {privilegeType}',
				[
					'privilegeType' => $maximumPrivilegeTypeValue,
				]
			);
			return RetValueOrError::withValue($privilegeTypeObject);
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to select project  ({errorCode} -> {errorInfo})',
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

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteByProjectId(
		UuidInterface $projectsId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			'deleting project privileges (Project:{projectsId}, deletedAt:{deletedAt})',
			[
				'projectsId' => $projectsId,
				'deletedAt' => $deletedAt,
			]
		);

		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					projects_privileges
				SET
					deleted_at = $deletedAtPlaceholder
				WHERE
					projects_id = :projectsId
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':projectsId', $projectsId->getBytes(), PDO::PARAM_STR);
			if ($hasDeletedAt)
			{
				$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'deleted project privileges (Project:{projectsId}, deletedAt:{deletedAt}, rowCount:{rowCount})',
				[
					'projectsId' => $projectsId,
					'deletedAt' => $deletedAt,
					'rowCount' => $rowCount,
				]
			);
			if ($rowCount === 0) {
				return RetValueOrError::withError(
					Constants::HTTP_NOT_FOUND,
					'project privileges not found',
				);
			} else {
				return RetValueOrError::withValue(null);
			}
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to delete project privileges ({errorCode} -> {errorInfo})',
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
