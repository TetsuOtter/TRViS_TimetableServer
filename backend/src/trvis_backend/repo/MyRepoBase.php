<?php

namespace dev_t0r\trvis_backend\repo;
use BackedEnum;
use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T
 */
abstract class MyRepoBase implements IMyRepoBase
{
	const TABLE_NAME_WORK_GROUPS = 'work_groups';
	protected readonly int $parentTableCount;
	protected readonly string $parentTableName;
	protected readonly string $PLACEHOLDER_PARENT_ID;
	protected readonly string $PLACEHOLDER_OWNER;
	protected readonly array $parentTableNameList;
	public function __construct(
		protected readonly PDO $db,
		protected readonly LoggerInterface $logger,
		protected readonly string $TABLE_NAME,
		/**
		 * @param array<string> $parentTableNameList 親(および祖先)のテーブル名リスト
		 *
		 * 近い親が[0]、遠い親が[n-1]
		 */
		array $parentTableNameList,
		protected readonly string $SQL_SELECT_COLUMNS,
		protected readonly string $SQL_INSERT_COLUMNS,
	) {
		$this->parentTableCount = count($parentTableNameList);
		if ($this->parentTableCount === 0) {
			throw new InvalidArgumentException('parentTableNameList must not be empty');
		}
		$this->parentTableName = $parentTableNameList[0];
		if ($parentTableNameList[$this->parentTableCount - 1] === self::TABLE_NAME_WORK_GROUPS) {
			$this->parentTableNameList = array_slice($parentTableNameList, 0, -1);
		}
		$this->PLACEHOLDER_PARENT_ID = ':parent_id';
		$this->PLACEHOLDER_OWNER = ':owner';
	}

	/**
	 * @param array<string, mixed> $d
	 * @return T
	 */
	protected abstract function _fetchResultToObj(
		array $d,
	): mixed;
	protected abstract function _genInsertValuesQuerySegment(
		int $i,
	): string;

	protected abstract function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		/** @param T $d */
		mixed $d,
	);

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		return "{$key} = :{$key}";
	}
	protected function _kvpToValueToBind(
		string $key,
		mixed $value,
	): mixed {
		return $value;
	}

	protected function errNotFound(): RetValueOrError {
		return RetValueOrError::withError(
			Constants::HTTP_NOT_FOUND,
			"{$this->TABLE_NAME} not found",
		);
	}

	/**
	 * @return RetValueOrError<T>
	 */
	public function selectOne(
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne {TABLE_NAME} id: {id}',
			[
				'TABLE_NAME' => $this->TABLE_NAME,
				'id' => $id,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				SELECT
					{$this->SQL_SELECT_COLUMNS}
				FROM
					{$this->TABLE_NAME}
				WHERE
					{$this->TABLE_NAME}_id = :id
				AND
					deleted_at IS NULL
				SQL
			);

			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne({TABLE_NAME}: {id}) - rowCount is 0',
					[
						'TABLE_NAME'=> $this->TABLE_NAME,
						'id' => $id,
					],
				);
				return $this->errNotFound();
			}

			return RetValueOrError::withValue($this->_fetchResultToObj($result));
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectWorkGroupsId(
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId {TABLE_NAME} id: {id}',
			[
				'TABLE_NAME' => $this->TABLE_NAME,
				'id' => $id,
			],
		);

		$JOIN_QUERY = implode(
			' ',
			array_map(
				fn(string $parentTableName): string => <<<SQL
				INNER JOIN
					{$parentTableName}
				USING
					({$parentTableName}_id)
				SQL,
				$this->parentTableNameList,
			),
		);
		$PARENTS_WHERE_DELETED_AT_IS_NULL = implode(
			' ',
			array_map(
				fn(string $parentTableName): string => <<<SQL
				AND
					{$parentTableName}.deleted_at IS NULL
				SQL,
				$this->parentTableNameList,
			),
		);
		$parentTableNameCount = count($this->parentTableNameList);
		$workGroupsIdTableName = 0 < $parentTableNameCount
			? $this->parentTableNameList[$parentTableNameCount - 1]
			: $this->TABLE_NAME;
		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					{$workGroupsIdTableName}.work_groups_id
				FROM
					{$this->TABLE_NAME}

				{$JOIN_QUERY}

				WHERE
					{$this->TABLE_NAME}_id = :id
				AND
					{$this->TABLE_NAME}.deleted_at IS NULL

				{$PARENTS_WHERE_DELETED_AT_IS_NULL}
				SQL
			);

			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return $this->errNotFound();
			}

			$resultId = $result['work_groups_id'];
			return RetValueOrError::withValue(Uuid::fromBytes($resultId));
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * (親テーブルが存在しない場合はこのメソッドを使用できないので注意)
	 * @return RetValueOrError<array<T>>
	 */
	public function selectPage(
		UuidInterface $parentId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList parentId: {parentId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'parentsId' => $parentId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$hasTopId = !is_null($topId);
		try
		{
			$query = $this->db->prepare(<<<SQL
				SELECT
					{$this->SQL_SELECT_COLUMNS}
				FROM
					{$this->TABLE_NAME}
				WHERE
					{$this->parentTableName}_id = :parent_id
				AND
					deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : " AND {$this->TABLE_NAME}_id <= :top_id ")
				.
				<<<SQL
				ORDER BY
				{$this->TABLE_NAME}_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':parent_id', $parentId->getBytes(), PDO::PARAM_STR);
			if ($hasTopId) {
				$query->bindValue(':top_id', $topId);
			}
			$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
			$query->bindValue(':offset', ($pageFrom1 - 1) * $perPage, PDO::PARAM_INT);

			$query->execute();
			$this->logger->debug(
				'rorCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			$result = $query->fetchAll(PDO::FETCH_ASSOC);

			$objList = array_map(
				fn($data) => $this->_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($objList);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $idList */
		array $idList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList {TABLE_NAME} idList: {idList}",
			[
				'TABLE_NAME' => $this->TABLE_NAME,
				'idList' => $idList,
			],
		);

		$idListCount = count($idList);
		$placeholders = implode(',', array_map(
			fn($i) => ":id_$i",
			range(0, $idListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(<<<SQL
				SELECT
					{$this->SQL_SELECT_COLUMNS}
				FROM
					{$this->TABLE_NAME}
				WHERE
					{$this->TABLE_NAME}_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $idListCount; $i++) {
				$query->bindValue(":id_$i", $idList[$i]->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$this->logger->debug(
				'rorCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			$result = $query->fetchAll(PDO::FETCH_ASSOC);

			$objList = array_map(
				fn($data) => $this->_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($objList);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function insertList(
		UuidInterface $parentId,
		string $ownerUserId,
		/** @param array<UuidInterface> $idList */
		array $idList,
		/** @param array<T> $valueList */
		array $valueList,
	): RetValueOrError {
		$this->logger->debug(
			'insertList parentId: {parentId}, ownerUserId: {ownerUserId}, idList: {idList}, valueList: {valueList}',
			[
				'parentId' => $parentId,
				'ownerUserId' => $ownerUserId,
				'idList' => $idList,
				'valueList' => $valueList,
			],
		);

		try
		{
			$valueListCount = count($valueList);
			$query = $this->db->prepare(<<<SQL
				INSERT INTO {$this->TABLE_NAME}
					{$this->SQL_INSERT_COLUMNS}
				VALUES
				SQL
				.
				implode(',', array_map(
					fn($i) => $this->_genInsertValuesQuerySegment($i),
					range(0, $valueListCount - 1),
				))
			);
			if (is_bool($query)) {
				$this->logger->critical(
					'Failed to prepare query. TableName: {TABLE_NAME}, parentId: {parentId}',
					[
						'TABLE_NAME' => $this->TABLE_NAME,
						'parentId' => $parentId,
					],
				);
				throw new \Exception('Failed to prepare query - contact to developer');
			}
			$query->bindValue($this->PLACEHOLDER_PARENT_ID, $parentId->getBytes(), PDO::PARAM_STR);
			$query->bindValue($this->PLACEHOLDER_OWNER, $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $valueListCount; $i++) {
				$this->_setInsertValues($query, $i, $idList[$i], $valueList[$i]);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'insertList - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return RetValueOrError::withValue($rowCount);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function update(
		UuidInterface $id,
		array $props,
	): RetValueOrError {
		$this->logger->debug(
			'updateList id: {id}, props: {props}',
			[
				'id' => $id,
				'props' => $props,
			],
		);

		if (count($props) === 0) {
			return RetValueOrError::withValue(0);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE {$this->TABLE_NAME} SET "
				.
				implode(
					',',
					array_map(
						fn($key) => $this->_keyToUpdateQuerySetLine($key),
						array_keys($props),
					),
				)
				.
				" WHERE {$this->TABLE_NAME}_id = :id AND deleted_at IS NULL"
			);
			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);
			foreach ($props as $key => $value) {
				$newValue = is_null($value) ? null : $this->_kvpToValueToBind($key, $value);

				if (is_null($newValue)) {
					$paramType = PDO::PARAM_NULL;
				} else if ($value instanceof UuidInterface) {
					$newValue = $value->getBytes();
				} else if ($value instanceof DateTimeInterface) {
					$newValue = Utils::utcDateStrOrNull($value);
				} else if ($value instanceof BackedEnum) {
					$newValue = $value->value;
					$paramType = PDO::PARAM_INT;
				} else if (is_int($newValue)) {
					$paramType = PDO::PARAM_INT;
				} else if (is_bool($newValue)) {
					$paramType = PDO::PARAM_BOOL;
				} else {
					$paramType = PDO::PARAM_STR;
				}

				$query->bindValue(":{$key}", $value, $paramType);
			}

			$query->execute();
			return RetValueOrError::withValue($query->rowCount());
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function deleteOne(
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne id: {id}",
			[
				"id" => $id,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					{$this->TABLE_NAME}
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					{$this->TABLE_NAME}_id = :id
				AND
					deleted_at IS NULL
				SQL
			);

			$query->bindValue(":id", $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"delete - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"{TABLE_NAME} not found ({id})",
					[
						'TABLE_NAME' => $this->TABLE_NAME,
						'id' => $id,
					],
				);
				return $this->errNotFound();
			}
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function deleteByWorkGroupsId(
		UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByWorkGroupsId workGroupsId: {workGroupsId}",
			[
				"workGroupsId" => $workGroupsId,
			],
		);

		$JOIN_QUERY = implode(
			' ',
			array_map(
				fn(string $parentTableName): string => <<<SQL
				INNER JOIN
					{$parentTableName}
				USING
					({$parentTableName}_id)
				SQL,
				$this->parentTableNameList,
			),
		);
		$parentTableNameCount = count($this->parentTableNameList);
		$workGroupsIdTableName = 0 < $parentTableNameCount
			? $this->parentTableNameList[$parentTableNameCount - 1]
			: $this->TABLE_NAME;

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					{$this->TABLE_NAME}

				{$JOIN_QUERY}

				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					{$workGroupsIdTableName}.work_groups_id = :work_groups_id
				AND
					{$this->TABLE_NAME}.deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":work_groups_id", $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteByWorkGroupsId - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			return RetValueOrError::withValue($rowCount);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
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
			'selectPrivilegeType {TABLE_NAME} id: {id}, userId: {userId}, includeAnonymous: {includeAnonymous}, selectForUpdate: {selectForUpdate}',
			[
				'TABLE_NAME' => $this->TABLE_NAME,
				'id' => $id,
				'userId' => $userId,
				'includeAnonymous' => $includeAnonymous,
				'selectForUpdate' => $selectForUpdate,
			],
		);

		if ($userId === Constants::UID_ANONYMOUS)
		{
			// リクエスト対象自体がAnonymousの場合は、わざわざOR条件にする必要はない
			$includeAnonymous = false;
		}
		$JOIN_QUERY = implode(
			' ',
			array_map(
				fn(string $parentTableName): string => <<<SQL
				INNER JOIN
					{$parentTableName}
				USING
					({$parentTableName}_id)
				SQL,
				$this->parentTableNameList,
			),
		);
		$PARENTS_WHERE_DELETED_AT_IS_NULL = implode(
			' ',
			array_map(
				fn(string $parentTableName): string => <<<SQL
				AND
					{$parentTableName}.deleted_at IS NULL
				SQL,
				$this->parentTableNameList,
			),
		);
		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					work_groups_privileges.privilege_type,
					work_groups_privileges.uid,
					work_groups_privileges.invite_keys_id
				FROM
					{$this->TABLE_NAME}

				{$JOIN_QUERY}

				INNER JOIN
					work_groups_privileges
				USING
					(work_groups_id)
				WHERE
					{$this->TABLE_NAME}_id = :id
				AND
					{$this->TABLE_NAME}.deleted_at IS NULL

				{$PARENTS_WHERE_DELETED_AT_IS_NULL}

				AND
				SQL
				.
				($includeAnonymous ? ' uid IN (:userId, \'\')' : ' uid = :userId')
				.
				($selectForUpdate ? ' FOR UPDATE' : '')
			);

			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->bindValue(':id', $id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			if ($query->rowCount() === 0)
			{
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
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
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}
}
