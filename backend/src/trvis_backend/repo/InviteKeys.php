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
			'use_limit' => $data['use_limit'],
			'privilege_type' => InviteKeyPrivilegeType::fromInt($data['privilege_type'])->__toString(),
		]);
		return $inviteKey;
	}

	const SQL_INSERT_INVITE_KEY = <<<SQL
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
SQL;
	public function insertInviteKey(
		UuidInterface $inviteKeyId,
		UuidInterface $workGroupId,
		UuidInterface $owner,
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
		$query = $this->db->prepare($this::SQL_INSERT_INVITE_KEY);
		$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner->getBytes(), PDO::PARAM_STR);
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
			}

			$errCode = $query->errorCode();
			$errInfo = implode('\n\t', $query->errorInfo());
			if (is_numeric($errCode)) {
				$errCodeInt = intval($errCode);
			} else {
				$errCodeInt = 500;
			}
		} catch (\Throwable $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
		}
		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errInfo,
			]
		);
		return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCodeInt);
	}

	const SQL_SELECT_INVITE_KEY_COLUMNS = <<<SQL
	invite_keys_id,
	work_groups_id,
	created_at,
	description,
	valid_from,
	expires_at,
	use_limit,
	disabled_at,
	privilege_type
FROM
	invite_keys
SQL;
	const SQL_SELECT_INVITE_KEY_ONE = $this::SQL_SELECT_INVITE_KEY_COLUMNS . <<<SQL
WHERE
	invite_keys_id = :invite_keys_id
;
SQL;
	public function selectInviteKey(
		UuidInterface $inviteKeyId,
		bool $useTransaction = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"selectInviteKey inviteKeyId: {inviteKeyId}",
			[
				'inviteKeyId' => $inviteKeyId,
			]
		);
		$sql = ($selectForUpdate ? 'SELECT FOR UPDATE' : 'SELECT') . $this::SQL_SELECT_INVITE_KEY_ONE;
		$query = $this->db->prepare($sql);
		$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);

		try {
			if ($useTransaction) {
				$this->db->beginTransaction();
			}

			$isSuccess = $query->execute();
			$this->logger->debug(
				"selectInviteKey isSuccess: {isSuccess}",
				[
					'isSuccess' => $isSuccess,
				]
			);
			if ($isSuccess) {
				$inviteKey = $query->fetch(PDO::FETCH_ASSOC);
				if ($inviteKey === false) {
					return RetValueOrError::withError(404, "InviteKey not found");
				}
				return RetValueOrError::withValue($this::_fetchResultToInviteKey($inviteKey));
			}

			$errCode = $query->errorCode();
			$errInfo = implode('\n\t', $query->errorInfo());
			if (is_numeric($errCode)) {
				$errCodeInt = intval($errCode);
			} else {
				$errCodeInt = 500;
			}
		} catch (\Throwable $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
		} finally {
			if ($useTransaction) {
				$this->db->commit();
			}
		}
		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errInfo,
			]
		);
		return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCodeInt);
	}

	const SQL_WHERE_TOP_ID = ' invite_keys_id <= :top_id ';
	const SQL_SELECT_INVITE_KEY_PAGING = <<<SQL
ORDER BY
	invite_keys_id DESC
OFFSET
	:offset
LIMIT
	:limit
;
SQL;
const SQL_SELECT_INVITE_KEY_EXCLUDE_EXPIRED = <<<SQL
	disabled_at IS NULL
AND
(
		expires_at IS NULL
	OR
		:currentDateTime BETWEEN valid_from AND expires_at
)
SQL;
	static function _getSql_SelectInviteKeyList(
		bool $hasTopId,
		bool $includeExpired,
	): string {
		$sql = 'SELECT' . self::SQL_SELECT_INVITE_KEY_COLUMNS . ' WHERE ';
		if ($hasTopId) {
			$sql .= self::SQL_WHERE_TOP_ID;
		}
		if (!$includeExpired) {
			if ($hasTopId) {
					$sql .= ' AND ';
			}
			$sql .= self::SQL_SELECT_INVITE_KEY_EXCLUDE_EXPIRED;
		}

		return $sql . self::SQL_SELECT_INVITE_KEY_PAGING;
	}
	const SQL_SELECT_INVITE_KEY_INCLUDE_EXPIRED = <<<SQL
SELECT
	invite_keys_id,
	work_groups_id,
	created_at,
	description,
	valid_from,
	expires_at,
	use_limit,
	disabled_at,
	privilege_type
FROM
	invite_keys
;
SQL;
	public function selectInviteKeyList(
		?int $page,
		?int $perPage,
		?UuidInterface $topId,
		bool $includeExpired = false,
		?\DateTime $currentDateTime = null,
	): RetValueOrError {
		$this->logger->debug(
			"selectInviteKeyList page: {page}, perPage: {perPage}, topId: {topId}, includeExpired: {includeExpired}, currentDateTime: {currentDateTime}",
			[
				'page' => $page,
				'perPage' => $perPage,
				'topId' => $topId,
				'includeExpired' => $includeExpired,
				'currentDateTime' => $currentDateTime,
			]
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare($this->_getSql_SelectInviteKeyList(
			$hasTopId,
			$includeExpired,
		));
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		if (!$includeExpired) {
			$currentDateTime ??= new \DateTime;
			$query->bindValue(':currentDateTime', Utils::utcDateStrOrNull($currentDateTime), PDO::PARAM_STR);
		}

		try {
			$isSuccess = $query->execute();
			$this->logger->debug(
				"selectInviteKeyList isSuccess: {isSuccess}",
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
					"selectInviteKeyList inviteKeyList->length: {inviteKeyList}",
					[
						'inviteKeyList' => count($inviteKeyList),
					]
				);
				return RetValueOrError::withValue(
					array_map(fn($inviteKey) => $this::_fetchResultToInviteKey($inviteKey), $inviteKeyList)
				);
			}

			$errCode = $query->errorCode();
			$errInfo = implode('\n\t', $query->errorInfo());
			if (is_numeric($errCode)) {
				$errCodeInt = intval($errCode);
			} else {
				$errCodeInt = 500;
			}
		} catch (\Throwable $th) {
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
		}
		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errInfo,
			]
		);
		return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode, $errCodeInt);
	}

	const SQL_SELECT_PRIVILEGES = <<<SQL
SELECT
	privilege_type
FROM
	work_groups_privileges
WHERE
	uid = :user_id
AND
	work_groups_id = :work_groups_id
;
SQL;
	public function getPrivilegeValue(
		UuidInterface $userId,
		UuidInterface $workGroupId,
		bool $useTransaction = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"getPrivilegeValue userId: {userId}, workGroupId: {workGroupId}, useTransaction: {useTransaction}, selectForUpdate: {selectForUpdate}",
			[
				'userId' => $userId,
				'workGroupId' => $workGroupId,
				'useTransaction' => $useTransaction,
				'selectForUpdate' => $selectForUpdate,
			]
		);

		if ($useTransaction) {
			$this->db->beginTransaction();
		}
		try {
			$query = $this->db->prepare(($selectForUpdate ? 'SELECT FOR UPDATE' : 'SELECT') . $this::SQL_SELECT_PRIVILEGES);
			$query->bindValue(':user_id', $userId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
			if ($query->execute()) {
				$privilege = $query->fetch(PDO::FETCH_ASSOC);
				$this->logger->debug(
					"getPrivilegeValue privilege: {privilege}",
					[
						'privilege' => $privilege,
					]
				);
				if ($privilege === false) {
					return RetValueOrError::withError(Constants::HTTP_NOT_FOUND, "Privilege not found");
				}
				return RetValueOrError::withValue(InviteKeyPrivilegeType::fromInt($privilege['privilege_type']));
			}

			$errCode = $query->errorCode();
			$errInfo = implode('\n\t', $query->errorInfo());
			if (is_numeric($errCode)) {
				$errCodeInt = intval($errCode);
			} else {
				$errCodeInt = Constants::HTTP_INTERNAL_SERVER_ERROR;
			}
		}
		catch (\Throwable $th)
		{
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
		}
		finally
		{
			if ($useTransaction) {
				$this->db->commit();
			}
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errInfo,
			]
		);
		return RetValueOrError::withError(
			Constants::HTTP_INTERNAL_SERVER_ERROR,
			"Failed to execute SQL - " . $errCode
			, $errCodeInt,
		);
}

	public function useInviteKey(
		UuidInterface $inviteKeyId,
		UuidInterface $userId,
		bool $useTransaction = true,
	): RetValueOrError {
		$this->logger->debug(
			"useInviteKey inviteKeyId: {inviteKeyId}, userId: {userId}, useTransaction: {useTransaction}",
			[
				'inviteKeyId' => $inviteKeyId,
				'userId' => $userId,
				'useTransaction' => $useTransaction,
			]
		);

		if ($useTransaction) {
			$this->db->beginTransaction();
		}

		try
		{
			// 将来的にuse_limitを適用したいため、transaction内でselectしておく
			$inviteKeyData = $this->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				useTransaction: false,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->logger->warning(
					"useInviteKey inviteKeyData->isError: {isError}, inviteKeyData->statusCode: {statusCode}",
					[
						'isError' => $inviteKeyData->isError,
						'statusCode' => $inviteKeyData->statusCode,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
				return $inviteKeyData;
			}

			$WorkGroupsPrivilegesRepo = new WorkGroupsPrivileges($this->db, $this->logger);
			$workGroupId = $inviteKeyData->value->work_groups_id;
			$privilegeType = $inviteKeyData->value->privilege_type;
			$currentPrivilegeType = $WorkGroupsPrivilegesRepo->selectPrivilegeType(
				workGroupsId: $workGroupId,
				userId: $userId,
				selectForUpdate: true,
			);
			if ($currentPrivilegeType->isError && $currentPrivilegeType->statusCode !== Constants::HTTP_NOT_FOUND) {
				$this->logger->warning(
					"useInviteKey currentPrivilegeType->isError: {isError}, currentPrivilegeType->statusCode: {statusCode}",
					[
						'isError' => $currentPrivilegeType->isError,
						'statusCode' => $currentPrivilegeType->statusCode,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
				return $currentPrivilegeType;
			}

			$this->logger->debug(
				"useInviteKey requestedPrivilegeType: {requestedPrivilegeType}, currentPrivilegeType: {currentPrivilegeType}",
				[
					'requestedPrivilegeType' => $privilegeType,
					'currentPrivilegeType' => $currentPrivilegeType->value,
				]
			);
			if (!$currentPrivilegeType->isError && $privilegeType->value <= $currentPrivilegeType->value->value) {
				$this->logger->warning(
					"useInviteKey: already have the same or higher privilege - current:{currentPrivilegeType}, requested:{requestedPrivilegeType}",
					[
						'currentPrivilegeType' => $currentPrivilegeType->value,
						'requestedPrivilegeType' => $privilegeType,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
				return RetValueOrError::withError(
					Constants::HTTP_OK,
					"You already have the same or higher privilege - " . $currentPrivilegeType->value->name,
				);
			}

			if ($currentPrivilegeType->isError) {
				$execResult = $WorkGroupsPrivilegesRepo->insert(
					workGroupsId: $workGroupId,
					privilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			} else {
				$execResult = $WorkGroupsPrivilegesRepo->changeType(
					workGroupsId: $workGroupId,
					newPrivilegeType: $privilegeType,
					userId: $userId,
					inviteKeysId: $inviteKeyId,
				);
			}

			if ($useTransaction) {
				if ($execResult->isError) {
					$this->logger->warning('apply invite key failed');
					$this->db->rollBack();
				} else {
					$this->logger->warning('apply invite key success');
					$this->db->commit();
				}
			}
			return $execResult;
		}
		catch (\Throwable $th)
		{
			if ($useTransaction) {
				$this->db->rollBack();
			}

			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
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
				$errCodeInt
			);
		}
	}

	public function disableInviteKey(
		UuidInterface $inviteKeyId,
		UuidInterface $userId,
		bool $useTransaction = true,
	): RetValueOrError {
		$this->logger->debug(
			"disableInviteKey inviteKeyId: {inviteKeyId}, userId: {userId}, useTransaction: {useTransaction}",
			[
				'inviteKeyId' => $inviteKeyId,
				'userId' => $userId,
				'useTransaction' => $useTransaction,
			]
		);

		if ($useTransaction) {
			$this->db->beginTransaction();
		}

		try
		{
			$inviteKeyData = $this->selectInviteKey(
				inviteKeyId: $inviteKeyId,
				useTransaction: false,
				selectForUpdate: true,
			);
			if ($inviteKeyData->isError) {
				$this->logger->warning(
					"disableInviteKey inviteKeyData->isError: {isError}, inviteKeyData->statusCode: {statusCode}",
					[
						'isError' => $inviteKeyData->isError,
						'statusCode' => $inviteKeyData->statusCode,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
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
				if ($useTransaction) {
					$this->db->commit();
				}
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
				if ($useTransaction) {
					$this->db->commit();
				}
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
				if ($useTransaction) {
					$this->db->commit();
				}
				return RetValueOrError::withError(
					Constants::HTTP_BAD_REQUEST,
					"InviteKey is already expired",
				);
			}

			$workGroupId = $inviteKeyData->value->work_groups_id;
			$currentPrivilegeType = $this->getPrivilegeValue(
				userId: $userId,
				workGroupId: $workGroupId,
				useTransaction: false,
				selectForUpdate: true,
			);
			if ($currentPrivilegeType->isError) {
				$this->logger->warning(
					"disableInviteKey currentPrivilegeType->isError: {isError}, currentPrivilegeType->statusCode: {statusCode}",
					[
						'isError' => $currentPrivilegeType->isError,
						'statusCode' => $currentPrivilegeType->statusCode,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
				return $currentPrivilegeType;
			}

			if ($currentPrivilegeType->value->value < InviteKeyPrivilegeType::ADMIN->value) {
				$this->logger->warning(
					"disableInviteKey: not enough privilege (currentPrivilegeType: {currentPrivilegeType})",
					[
						'currentPrivilegeType' => $currentPrivilegeType->value,
					]
				);
				if ($useTransaction) {
					$this->db->commit();
				}
				return RetValueOrError::withError(
					Constants::HTTP_FORBIDDEN,
					"You don't have enough privilege to disable this InviteKey",
				);
			}

			$query = $this->db->prepare($this::SQL_DISABLE_INVITE_KEY);
			$query->bindValue(':invite_keys_id', $inviteKeyId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':user_id', $userId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
			if ($query->execute()) {
				$this->logger->debug("disableInviteKey Success");
				if ($useTransaction) {
					$this->db->commit();
				}
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errInfo = implode('\n\t', $query->errorInfo());
			if (is_numeric($errCode)) {
				$errCodeInt = intval($errCode);
			} else {
				$errCodeInt = 500;
			}
		} catch (\Throwable $th) {
			if ($useTransaction) {
				$this->db->rollBack();
			}
			$errCode = $th->getCode();
			$errInfo = $th->getMessage();
			$errCodeInt = $errCode;
		}

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
			$errCodeInt,
		);
	}
}
