<?php

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\WorkGroup;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorkGroupsRepo
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

	/**
	 * @return RetValueOrError<WorkGroup>
	 */
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
			AND
				deleted_at IS NULL
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
			return Utils::errWorkGroupNotFound();
		}

		$workGroup = $this->_fetchResultToWorkGroup($data);
		$this->logger->debug("select result - workGroup: {workGroup}", ['workGroup' => $workGroup]);
		return RetValueOrError::withValue($workGroup);
	}

	/**
	 * @return RetValueOrError<array<WorkGroup>>
	 */
	public function selectWorkGroupPage(
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug("selectPage(userId:{userId}, pageFrom1:{page}, perPage:{perPage}, topId:{topId})", [
			'userId' => $userId,
			'page' => $pageFrom1,
			'perPage' => $perPage,
			'topId' => $topId,
		]);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(
			<<<SQL
			SELECT
				work_groups_id,
				work_groups.created_at AS created_at,
				work_groups.description AS description,
				name
			FROM
				work_groups
			JOIN
				work_groups_privileges
			USING
				(work_groups_id)
			WHERE
			SQL
			.
			($hasTopId ? ' work_groups_id <= :top_id AND ' : '')
			.
			(
				$userId === Constants::UID_ANONYMOUS
					? ' uid = :userId '
					: ' uid IN (:userId, \'\') '
			)
			.
			' AND work_groups.deleted_at IS NULL '
			.
			' AND work_groups_privileges.deleted_at IS NULL '
			.
			'AND privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' '
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
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
		$query->bindValue(':offset', ($pageFrom1 - 1) * $perPage, PDO::PARAM_INT);

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

		$workGroups = array_map(
			fn ($data) => $this->_fetchResultToWorkGroup($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);

		$this->logger->debug("select result - workGroup: {workGroups}", ['workGroups' => $workGroups]);
		return RetValueOrError::withValue($workGroups);
	}

	/**
	 * @return RetValueOrError<null>
	 */
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

	/**
	 * @return RetValueOrError<null>
	 */
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
			return RetValueOrError::withValue(null);
		}

		$query = $this->db->prepare(<<<SQL
			UPDATE work_groups SET
			SQL
			.
			($hasName ? ' name = :name ' : '')
			.
			($hasName && $hasDescription ? ', ' : '')
			.
			($hasDescription ? 'description = :description ' : '')
			.
			<<<SQL
			WHERE
				work_groups_id = :work_groups_id
			AND
				deleted_at IS NULL
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
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"WorkGroup not found ({workGroupId})",
						[
							'workGroupId' => $workGroupId,
						]
					);
					return Utils::errWorkGroupNotFound();
				} else {
					return RetValueOrError::withValue(null);
				}
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

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteWorkGroup(
		UuidInterface $workGroupId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			"deleteWorkGroup({workGroupId}, {deletedAt})",
			[
				'workGroupId' => $workGroupId,
				'deletedAt' => $deletedAt,
			]
		);
		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(<<<SQL
			UPDATE
				work_groups
			SET
				deleted_at = $deletedAtPlaceholder
			WHERE
				work_groups_id = :work_groups_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		if ($hasDeletedAt) {
			$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
		}

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"WorkGroup not found ({workGroupId})",
					[
						'workGroupId' => $workGroupId,
					]
				);
				return Utils::errWorkGroupNotFound();
			} else {
				return RetValueOrError::withValue(null);
			}

		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}
}
