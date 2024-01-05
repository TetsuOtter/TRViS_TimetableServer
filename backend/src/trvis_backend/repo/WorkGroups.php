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

	public function selectWorkGroupOne(
		UuidInterface $workGroupId
	): RetValueOrError {
		$this->logger->debug("selectOne workGroupId: {workGroupId}", ['workGroupId' => $workGroupId]);
		$query = $this->db->prepare(<<<SQL
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
			SQL
		);
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

	const PAGE_MIN_VALUE = 1;
	const PAGE_DEFAULT_VALUE = 1;
	const PER_PAGE_DEFAULT_VALUE = 10;
	const PER_PAGE_MIN_VALUE = 5;
	const PER_PAGE_MAX_VALUE = 100;
	public function selectWorkGroupPage(
		?int $page,
		?int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug("selectPage(page:{page}, perPage:{perPage}, topId:{topId})", [
			'page' => $page,
			'perPage' => $perPage,
			'topId' => $topId,
		]);
		if (is_null($page) || $page < $this::PAGE_MIN_VALUE) {
			$page = $this::PAGE_DEFAULT_VALUE;
		}
		if (is_null($perPage) || $perPage < $this::PER_PAGE_DEFAULT_VALUE) {
			$perPage = $this::PER_PAGE_DEFAULT_VALUE;
		} else if ($this::PER_PAGE_MAX_VALUE < $perPage) {
			$perPage = $this::PER_PAGE_MAX_VALUE;
		}
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(
			<<<SQL
			SELECT
				work_groups_id,
				created_at,
				description,
				name
			FROM
				work_groups
			SQL
			.
			($hasTopId ? 'WHERE work_groups_id <= :top_id ' : '')
			.
			<<<SQL
			ORDER BY
				work_groups_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			;
			SQL
		);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
		$query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);

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

		$workGroups = [];
		while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
			$workGroup = $this->_fetchResultToWorkGroup($data);
			array_push($workGroups, $workGroup);
		}

		$this->logger->debug("select result - workGroup: {workGroups}", ['workGroups' => $workGroups]);
		return RetValueOrError::withValue($workGroups);
	}

	public function insertWorkGroup(
		UuidInterface $workGroupId,
		string $owner,
		string $description,
		string $name
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
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
			SQL
		);

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

	public function updateWorkGroup(
		UuidInterface $workGroupId,
		?string $description,
		?string $name
	): RetValueOrError {
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
		if (!$hasName && !$hasDescription) {
			return $this->selectWorkGroupOne($workGroupId);
		}

		$query = $this->db->prepare(<<<SQL
			UPDATE work_groups SET
			SQL
			.
			($hasName ? 'name = :name ' : '')
			.
			($hasName && $hasDescription ? ', ' : '')
			.
			($hasDescription ? 'description = :description ' : '')
			.
			<<<SQL
			WHERE
				work_groups_id = :work_groups_id
			;
			SQL
		);
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

	public function deleteWorkGroup(
		UuidInterface $workGroupId
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			DELETE FROM work_groups
			WHERE
				work_groups_id = :work_groups_id
			;
			SQL
		);
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
