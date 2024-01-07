<?php

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\WorkGroupsPrivilege;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorkGroupsPrivileges
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
		UuidInterface $workGroupsId,
		InviteKeyPrivilegeType $privilegeType = InviteKeyPrivilegeType::none,
		string $userId = '',
		?UuidInterface $inviteKeysId = null,
	): RetValueOrError {
		$this->logger->debug(
			'inserting work group privilege (user:{userId}, WorkGroup:{workGroupsId}, InviteKey:{inviteKeysId}, PrivilegeType:{privilegeType})',
			[
				'userId' => $userId,
				'workGroupsId' => $workGroupsId,
				'inviteKeysId' => $inviteKeysId,
				'privilegeType' => $privilegeType,
			]
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				INSERT INTO
					work_groups_privileges
				(
					uid,
					work_groups_id,
					invite_keys_id,
					privilege_type
				) VALUES (
					:userId,
					:workGroupsId,
					:inviteKeysId,
					:privilegeType
				)
				;
				SQL
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
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
				'failed to insert work group  ({errorCode} -> {errorInfo})',
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
		UuidInterface $workGroupsId,
		InviteKeyPrivilegeType $newPrivilegeType = InviteKeyPrivilegeType::none,
		string $userId = '',
		?UuidInterface $inviteKeysId = null,
	): RetValueOrError {
		$this->logger->debug(
			'changing work group privilege (user:{userId}, WorkGroup:{workGroupsId}, InviteKey:{inviteKeysId}, NewPrivilegeType:{privilegeType})',
			[
				'userId' => $userId,
				'workGroupsId' => $workGroupsId,
				'inviteKeysId' => $inviteKeysId,
				'privilegeType' => $newPrivilegeType,
			]
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					work_groups_privileges
				SET
					invite_keys_id = :inviteKeysId,
					privilege_type = :privilegeType
				WHERE
						uid = :userId
					AND
						work_groups_id = :workGroupsId
				;
				SQL
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':inviteKeysId', $inviteKeysId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':privilegeType', $newPrivilegeType->value, PDO::PARAM_INT);
			$query->execute();
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $e)
		{
			$errCode = $e->getCode();
			$errInfo = $e->errorInfo;
			$this->logger->error(
				'failed to change work group  ({errorCode} -> {errorInfo})',
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
		UuidInterface $workGroupsId,
		string $userId = Constants::UID_ANONUMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			'selecting work group privilege (user:{userId}, WorkGroup:{workGroupsId})',
			[
				'userId' => $userId,
				'workGroupsId' => $workGroupsId,
			]
		);

		if ($userId === Constants::UID_ANONUMOUS)
		{
			// リクエスト対象自体がAnonumousの場合は、わざわざOR条件にする必要はない
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
					work_groups_privileges
				WHERE
					work_groups_id = :workGroupsId
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
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0)
			{
				return Utils::errWorkGroupNotFound();
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
				'failed to select work group  ({errorCode} -> {errorInfo})',
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
	 * @return RetValueOrError<WorkGroupsPrivilege>
	 */
	public function selectPrivilegeTypeObject(
		UuidInterface $workGroupsId,
		string $userId = Constants::UID_ANONUMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			'selecting work group privilege (user:{userId}, WorkGroup:{workGroupsId}, includeAnonymous:{includeAnonymous}, selectForUpdate:{selectForUpdate})',
			[
				'userId' => $userId,
				'workGroupsId' => $workGroupsId,
				'includeAnonymous' => $includeAnonymous ? 'true' : 'false',
				'selectForUpdate' => $selectForUpdate ? 'true' : 'false',
			]
		);

		if ($userId === Constants::UID_ANONUMOUS)
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
					work_groups_id,
					invite_keys_id,
					created_at,
					updated_at,
					privilege_type
				FROM
					work_groups_privileges
				WHERE
					work_groups_id = :workGroupsId
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
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$this->logger->debug(
				'rowCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			if ($query->rowCount() === 0)
			{
				return Utils::errWorkGroupNotFound();
			}

			$privilegeTypeList = $query->fetchAll(PDO::FETCH_ASSOC);
			$maximumPrivilegeTypeValue = InviteKeyPrivilegeType::none->value;
			$privilegeTypeObject = new WorkGroupsPrivilege();
			foreach ($privilegeTypeList as $row)
			{
				$privilegeTypeValue = intval($row['privilege_type']);
				$inviteKeysId = $row['invite_keys_id'];
				$obj = [
					'uid' => $row['uid'],
					'work_groups_id' => Uuid::fromBytes($row['work_groups_id']),
					'invite_keys_id' => is_null($inviteKeysId) ? null : Uuid::fromBytes($inviteKeysId),
					'created_at' => Utils::dbDateStrToDateTime($row['created_at']),
					'updated_at'=> Utils::dbDateStrToDateTime($row['updated_at']),
					'privilege_type' => InviteKeyPrivilegeType::fromInt($privilegeTypeValue),
				];
				$this->logger->debug(
					'privilege type: {privilege_type} (UID:{uid}, InviteKey:{invite_keys_id})',
					$obj
				);
				if ($maximumPrivilegeTypeValue < $privilegeTypeValue)
				{
					$maximumPrivilegeTypeValue = $privilegeTypeValue;

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
				'failed to select work group  ({errorCode} -> {errorInfo})',
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
	public function deleteByWorkGroupId(
		UuidInterface $workGroupsId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			'deleting work group privileges (WorkGroup:{workGroupsId}, deletedAt:{deletedAt})',
			[
				'workGroupsId' => $workGroupsId,
				'deletedAt' => $deletedAt,
			]
		);

		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					work_groups_privileges
				SET
					deleted_at = $deletedAtPlaceholder
				WHERE
					work_groups_id = :workGroupsId
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
			if ($hasDeletedAt)
			{
				$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'deleted work group privileges (WorkGroup:{workGroupsId}, deletedAt:{deletedAt}, rowCount:{rowCount})',
				[
					'workGroupsId' => $workGroupsId,
					'deletedAt' => $deletedAt,
					'rowCount' => $rowCount,
				]
			);
			if ($rowCount === 0) {
				return RetValueOrError::withError(
					Constants::HTTP_NOT_FOUND,
					'work group privileges not found',
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
				'failed to delete work group privileges ({errorCode} -> {errorInfo})',
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
