<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKey;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class InviteKeys
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	const SQL_COLUMNS = <<<SQL
		invite_keys_id,
		work_groups_id,
		owner,
		description,
		valid_from,
		expires_at,
		use_limit,
		disabled_at,
		privilege_type
	SQL;

	static function _fetchResultToInviteKey(
		mixed $data
	): InviteKey {
		$inviteKey = new InviteKey();
		$inviteKey->setData([
			'invite_keys_id' => Uuid::fromBytes($data['invite_keys_id']),
			'work_groups_id' => Uuid::fromBytes($data['work_groups_id']),
			'owner' => Uuid::fromBytes($data['owner']),
			'description' => $data['description'],
			'valid_from' => Utils::utcDateStrToDateTime($data['valid_from']),
			'expires_at' => Utils::utcDateStrToDateTime($data['expires_at']),
			'disabled_at' => Utils::utcDateStrToDateTime($data['disabled_at']),
			'use_limit' => $data['use_limit'],
			'privilege_type' => InviteKeyPrivilegeType::fromInt($data['privilege_type'])->__toString(),
		]);
		return $inviteKey;
	}

	public function insertInviteKey(
		UuidInterface $inviteKeyId,
		UuidInterface $workGroupId,
		string $owner,
		string $description,
		?\DateTimeInterface $validFrom,
		?\DateTimeInterface $expiresAt,
		?int $useLimit,
		InviteKeyPrivilegeType $privilegeType
	): RetValueOrError {
		$this->logger->debug(
			"insertInviteKey inviteKeyId: {inviteKeyId}, workGroupId: {workGroupId}, owner: {owner}, description: '{description}', validFrom: {validFrom}, expiresAt: {expiresAt}, useLimit: {useLimit}, privilegeType: {privilegeType}",
			[
				'inviteKeyId' => $inviteKeyId,
				'workGroupId' => $workGroupId,
				'owner' => $owner,
				'description' => $description,
				'validFrom' => $validFrom,
				'expiresAt' => $expiresAt,
				'useLimit' => $useLimit,
				'privilegeType' => $privilegeType
			]
		);

		$query = $this->db->prepare(<<<SQL
			INSERT INTO
				invite_keys (
					invite_keys_id,
					work_groups_id,
					owner,
					description,
					valid_from,
					expires_at,
					use_limit,
					privilege_type
				)
			VALUES (
				:invite_keys_id,
				:work_groups_id,
				:owner,
				:description,
				:valid_from,
				:expires_at,
				:use_limit,
				:privilege_type
			)
			;
			SQL
		);

		$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':valid_from', Utils::utcDateStrOrNull($validFrom), PDO::PARAM_STR);
		$query->bindValue(':expires_at', Utils::utcDateStrOrNull($expiresAt), PDO::PARAM_STR);
		$query->bindValue(':use_limit', $useLimit, PDO::PARAM_INT);
		$query->bindValue(':privilege_type', $privilegeType->value, PDO::PARAM_INT);

		try {
			$isSuccess = $query->execute();
			$this->logger->debug(
				"insertInviteKey isSuccess: {isSuccess}",
				[
					'isSuccess' => $isSuccess,
				]
			);
			if ($isSuccess) {
				return RetValueOrError::withValue(null);
			} else {
				$this->logger->error("Unknown error");
				return RetValueOrError::withError(500, "Unknown error");
			}
		} catch (\PDOException $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $errInfo,
				]
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCode);
		}
	}

	public function selectInviteKey(
		UuidInterface $inviteKeyId,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"selectInviteKey inviteKeyId: {inviteKeyId}",
			[
				'inviteKeyId' => $inviteKeyId,
			]
		);

		$query = $this->db->prepare(
			'SELECT'
			.
			($selectForUpdate ? ' FOR UPDATE ' : '')
			.
			self::SQL_COLUMNS
			.
			<<<SQL
			FROM
				invite_keys
			WHERE
				invite_keys_id = :invite_keys_id
			;
			SQL
		);

		$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);

		try {
			$isSuccess = $query->execute();
			$this->logger->debug(
				"selectInviteKey isSuccess: {isSuccess}",
				[
					'isSuccess' => $isSuccess,
				]
			);
			if (!$isSuccess) {
				$this->logger->error("Unknown error");
				return RetValueOrError::withError(500, "Unknown error");
			}
			$inviteKey = $query->fetch(PDO::FETCH_ASSOC);
			if ($inviteKey === false) {
				return RetValueOrError::withError(404, "InviteKey not found");
			}
			return RetValueOrError::withValue($this::_fetchResultToInviteKey($inviteKey));
		} catch (\PDOException $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $errInfo,
				]
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCode);
		}
	}

	public function selectInviteKeyList(
		string|UuidInterface $ownerOrWorkGroupsId,
		?int $page,
		?int $perPage,
		?UuidInterface $topId,
		bool $includeExpired = false,
		?\DateTime $currentDateTime = null,
	): RetValueOrError {
		$isRequestWithOwnerUid = is_string($ownerOrWorkGroupsId);
		$this->logger->debug(
			"selectInviteKeyListWithOwnerUid isRequestWithOwnerUid: {isRequestWithOwnerUid}, ownerOrWorkGroupsId: {ownerOrWorkGroupsId}, page: {page}, perPage: {perPage}, topId: {topId}, includeExpired: {includeExpired}, currentDateTime: {currentDateTime}",
			[
				'isRequestWithOwnerUid' => $isRequestWithOwnerUid,
				'ownerOrWorkGroupsId' => $ownerOrWorkGroupsId,
				'page' => $page,
				'perPage' => $perPage,
				'topId' => $topId,
				'includeExpired' => $includeExpired,
				'currentDateTime' => $currentDateTime,
			]
		);

		$hasTopId = !is_null($topId);
		$hasCurrentDateTimeValue = !is_null($currentDateTime);
		$currentDateTimePlaceholder = $hasCurrentDateTimeValue ? ':currentDateTime' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(
			'SELECT'
			.
			self::SQL_COLUMNS
			.
			<<<SQL
			FROM
				invite_keys
			WHERE
			SQL
			.
			($isRequestWithOwnerUid ? ' owner = :ownerUid ' : ' work_groups_id = :workGroupsId ')
			.
			($hasTopId ? 'AND invite_keys_id <= :top_id ' : '')
			.
			(
				$includeExpired ? '' :
					<<<SQL
					AND
						disabled_at IS NULL
					AND
					(
							expires_at IS NULL
						OR
							{$currentDateTimePlaceholder} BETWEEN valid_from AND expires_at
					)
					SQL
			)
		);
		if ($isRequestWithOwnerUid) {
			$query->bindValue(':ownerUid', $ownerOrWorkGroupsId, PDO::PARAM_STR);
		} else {
			$query->bindValue(':workGroupsId', $ownerOrWorkGroupsId->getBytes(), PDO::PARAM_STR);
		}
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		if (!$includeExpired && $hasCurrentDateTimeValue) {
			$query->bindValue(':currentDateTime', Utils::utcDateStrOrNull($currentDateTime), PDO::PARAM_STR);
		}

		try {
			$isSuccess = $query->execute();
			$this->logger->debug(
				"selectInviteKeyListWithOwnerUid isSuccess: {isSuccess}",
				[
					'isSuccess' => $isSuccess,
				]
			);
			if ($isSuccess) {
				$inviteKeyList = $query->fetchAll(PDO::FETCH_ASSOC);
				if ($inviteKeyList === false) {
					return RetValueOrError::withError(404, "InviteKeys not found");
				}

				$this->logger->debug(
					"selectInviteKeyListWithOwnerUid inviteKeyList->length: {inviteKeyList}",
					[
						'inviteKeyList' => count($inviteKeyList),
					]
				);
				return RetValueOrError::withValue(
					array_map(fn($inviteKey) => $this::_fetchResultToInviteKey($inviteKey), $inviteKeyList)
				);
			} else {
				$this->logger->error("Unknown error");
				return RetValueOrError::withError(500, "Unknown error");
			}
		} catch (\PDOException $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $errInfo,
				]
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCode);
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

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					invite_keys
				SET
					disabled_at = CURRENT_TIMESTAMP()
				WHERE
					invite_keys_id = :invite_keys_id
				;
				SQL
			);
			$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':user_id', $userId, PDO::PARAM_STR);

			if ($query->execute()) {
				$this->logger->debug("disableInviteKey Success");
				return RetValueOrError::withValue(null);
			} else {
				$this->logger->error("disableInviteKey Unknown error");
				return RetValueOrError::withError(500, "Unknown error");
			}
		} catch (\PDOException $th) {
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