<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\WorkGroup;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorkGroups
{
	public function __construct(
		private PDO $db,
		private LoggerInterface $logger,
	) {
	}

	private static function _fetchResultToWorkGroup(
		mixed $data
	): WorkGroup {
		$workGroup = new WorkGroup();
		$workGroup->setData([
			'work_groups_id' => Uuid::fromBytes($data['work_groups_id']),
			'created_at' => $data['created_at'],
			'description' => $data['description'],
			'name' => $data['name']
		]);
		return $workGroup;
	}

	const SQL_SELECT_WORK_GROUP_ONE = <<<SQL
SELECT
	work_groups_id,
	created_at,
	description,
	name
FROM
	work_groups
WHERE
	work_groups_id = :work_groups_id
;
SQL;
	public function selectWorkGroupOne(
		UuidInterface $workGroupId
	): RetValueOrError {
		$this->logger->debug("selectOne workGroupId: {workGroupId}", ['workGroupId' => $workGroupId]);
		$query = $this->db->prepare($this::SQL_SELECT_WORK_GROUP_ONE);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => implode('\n\t', $query->errorInfo()),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}

		$this->logger->debug("select success - rowCount: {rowCount}", ['rowCount' => $query->rowCount()]);

		$data = $query->fetch(PDO::FETCH_ASSOC);
		if (!$data) {
			$this->logger->info(
				"WorkGroup not found ({workGroupId})",
				[
					'workGroupId' => $workGroupId,
				]
			);
			return RetValueOrError::withError(404, "WorkGroup not found");
		}

		$workGroup = $this->_fetchResultToWorkGroup($data);
		$this->logger->debug("select result - workGroup: {workGroup}", ['workGroup' => $workGroup]);
		return RetValueOrError::withValue($workGroup);
	}


	const SQL_CREATE_WORK_GROUP = <<<SQL
INSERT INTO work_groups (
	work_groups_id,
	owner,
	description,
	name
) VALUES (
	:work_groups_id,
	:owner,
	:description,
	:name
);
SQL;
	public function insertWorkGroup(
		UuidInterface $workGroupId,
		string $owner,
		string $description,
		string $name
	): RetValueOrError {
		$query = $this->db->prepare($this::SQL_CREATE_WORK_GROUP);

		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfo = implode('\n\t', $query->errorInfo());
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);
		if ($errCode === '23000') {
			return RetValueOrError::withError(Constants::HTTP_CONFLICT, "WorkGroup already exists");
		}

		return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, "Failed to execute SQL - " . $errCode);
	}

	const SQL_UPDATE_WORK_GROUP = <<<SQL
UPDATE work_groups SET
	description = :description,
	name = :name
WHERE
	work_groups_id = :work_groups_id
;
SQL;
	const SQL_UPDATE_WORK_GROUP_NAME = <<<SQL
UPDATE work_groups SET
	name = :name
WHERE
	work_groups_id = :work_groups_id
;
SQL;
	const SQL_UPDATE_WORK_GROUP_DESCRIPTION = <<<SQL
UPDATE work_groups SET
	description = :description
WHERE
	work_groups_id = :work_groups_id
;
SQL;
	public function updateWorkGroup(
		UuidInterface $workGroupId,
		?string $description,
		?string $name
	): RetValueOrError {
		$sql = '';
		$query = $this->db->prepare($this::SQL_UPDATE_WORK_GROUP);
		$hasName = !is_null($name);
		$hasDescription = !is_null($description);
		$this->logger->info(
			"updateWorkGroup({workGroupId}, '{description}', '{name}') -> hasName: {hasName}, hasDescription: {hasDescription}",
			[
				'workGroupId' => $workGroupId,
				'description' => $description,
				'name' => $name,
				'hasName' => $hasName,
				'hasDescription' => $hasDescription,
			],
		);
		if ($hasName && $hasDescription) {
			$sql = $this::SQL_UPDATE_WORK_GROUP;
		} else if ($hasName) {
			$sql = $this::SQL_UPDATE_WORK_GROUP_NAME;
		} else if ($hasDescription) {
			$sql = $this::SQL_UPDATE_WORK_GROUP_DESCRIPTION;
		} else {
			return $this->selectWorkGroupOne($workGroupId);
		}

		$query = $this->db->prepare($sql);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		if ($hasDescription) {
			$query->bindValue(':description', $description, PDO::PARAM_STR);
		}
		if ($hasName) {
			$query->bindValue(':name', $name, PDO::PARAM_STR);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				return $this->selectWorkGroupOne($workGroupId);
			}

			$errCode = $query->errorCode();
			$errorInfo = implode('\n\t', $query->errorInfo());
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);
		if ($errCode === '23000') {
			return RetValueOrError::withError(Constants::HTTP_CONFLICT, "WorkGroup already exists");
		}

		return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, "Failed to execute SQL - " . $errCode);
	}

	const SQL_DELETE_WORK_GROUP = <<<SQL
DELETE FROM work_groups
WHERE
	work_groups_id = :work_groups_id
;
SQL;
	public function deleteWorkGroup(
		UuidInterface $workGroupId
	): RetValueOrError {
		$query = $this->db->prepare($this::SQL_DELETE_WORK_GROUP);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => implode('\n\t', $query->errorInfo()),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}

		$rowCount = $query->rowCount();
		if ($rowCount === 0) {
			$this->logger->info(
				"WorkGroup not found ({workGroupId})",
				[
					'workGroupId' => $workGroupId,
				]
			);
			return RetValueOrError::withError(404, "WorkGroup not found");
		}
		$this->logger->info(
			"WorkGroup deleted ({workGroupId})",
			[
				'workGroupId' => $workGroupId,
			]
		);
		return RetValueOrError::withValue(null);
	}
}
