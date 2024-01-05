<?php

namespace dev_t0r\trvis_backend\repo;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
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
				($selectForUpdate ? ' FOR UPDATE' : '')
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
				($includeAnonymous ? 'uid = :userId' : 'uid IN (:userId, \'\')')
				.
				';'
			);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':workGroupsId', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0)
			{
				return RetValueOrError::withError(
					Constants::HTTP_NOT_FOUND,
					'work group privilege not found',
					0,
				);
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
}
