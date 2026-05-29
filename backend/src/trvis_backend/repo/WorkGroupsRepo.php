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

	private static function fetchResultToWorkGroup(
		mixed $data
	): WorkGroup {
		$workGroup = new WorkGroup();
		$workGroup->setData([
			'work_groups_id' => Uuid::fromBytes($data['work_groups_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'created_at' => $data['created_at'],
			'description' => $data['description'],
			'name' => $data['name'],
			'privilege_type' => InviteKeyPrivilegeType::fromInt($data['privilege_type']),
		]);
		return $workGroup;
	}

	/**
	 * @return RetValueOrError<WorkGroup>
	 */
	public function selectWorkGroupOne(
		string $userId,
		UuidInterface $workGroupId
	): RetValueOrError {
		$this->logger->debug("selectOne workGroupId: {workGroupId}", ['workGroupId' => $workGroupId]);
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		// Project = 権限ルート: WGの権限は所属Project (projects_privileges) から導出
		$query = $this->db->prepare(<<<SQL
			SELECT
				work_groups.work_groups_id,
				work_groups.projects_id,
				work_groups.created_at,
				work_groups.description,
				work_groups.name,
				projects_privileges.privilege_type
			FROM
				work_groups
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				AND
					$WHERE_PRIVILEGE_TYPE
				GROUP BY
					projects_id
				) AS projects_privileges
			ON
				projects_privileges.projects_id = work_groups.projects_id
			WHERE
				work_groups.work_groups_id = :work_groups_id
			AND
				work_groups.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
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

		$workGroup = $this->fetchResultToWorkGroup($data);
		$this->logger->debug("select result - workGroup: {workGroup}", ['workGroup' => $workGroup]);
		return RetValueOrError::withValue($workGroup);
	}

	private static function getSelectPageQuery(
		string $userId,
		bool $hasTopId,
		bool $countOnly,
		bool $hasProjectId = false,
	): string {
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$WHERE_PROJECT_ID = $hasProjectId ? ' work_groups.projects_id = :projects_id AND ' : ' ';
		$WHERE_TOP_ID = $hasTopId ? ' work_groups.work_groups_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			work_groups_id,
			work_groups.projects_id AS projects_id,
			work_groups.created_at AS created_at,
			work_groups.description AS description,
			name,
			projects_privileges.privilege_type

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				work_groups_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				work_groups
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					$WHERE_PRIVILEGE_TYPE
				AND
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				GROUP BY
					projects_id
				) AS projects_privileges
			ON
				projects_privileges.projects_id = work_groups.projects_id
			WHERE
				$WHERE_PROJECT_ID
				$WHERE_TOP_ID
				work_groups.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<WorkGroup>>
	 */
	public function selectWorkGroupPageByProjectId(
		UuidInterface $projectId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug("selectPageByProjectId(projectId:{projectId}, userId:{userId}, page:{page})", [
			'projectId' => $projectId,
			'userId' => $userId,
			'page' => $pageFrom1,
		]);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false, true));
		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);
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

		$workGroups = array_map(
			fn ($data) => $this->fetchResultToWorkGroup($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($workGroups);
	}

	/**
	 * @return RetValueOrError<number>
	 */
	public function selectWorkGroupPageByProjectIdTotalCount(
		UuidInterface $projectId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true, true));
		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);

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
		$row = $query->fetch(PDO::FETCH_ASSOC);
		$totalCount = $row ? (int)$row['count'] : 0;
		return RetValueOrError::withValue($totalCount);
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
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
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
			fn ($data) => $this->fetchResultToWorkGroup($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);

		$this->logger->debug("select result - workGroup: {workGroups}", ['workGroups' => $workGroups]);
		return RetValueOrError::withValue($workGroups);
	}

	/**
	 * @return RetValueOrError<number>
	 */
	public function selectWorkGroupPageTotalCount(
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug("selectPageTotalCount(userId:{userId}, topId:{topId})", [
			'userId' => $userId,
			'topId' => $topId,
		]);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);

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
		$row = $query->fetch(PDO::FETCH_ASSOC);
		$totalCount = $row ? (int)$row['count'] : 0;

		$this->logger->debug("select result - totalCount: {totalCount}", ['totalCount' => $totalCount]);
		return RetValueOrError::withValue($totalCount);
	}

	/**
	 * 指定WorkGroupが属するProjectのIDを取得する。
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectProjectsIdByWorkGroupsId(
		UuidInterface $workGroupId,
	): RetValueOrError {
		try {
			$query = $this->db->prepare(
				'SELECT projects_id FROM work_groups WHERE work_groups_id = :work_groups_id AND deleted_at IS NULL;'
			);
			$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$row = $query->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				return Utils::errWorkGroupNotFound();
			}
			return RetValueOrError::withValue(Uuid::fromBytes($row['projects_id']));
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

	/**
	 * @return RetValueOrError<null>
	 */
	public function insertWorkGroup(
		UuidInterface $workGroupId,
		UuidInterface $projectsId,
		string $owner,
		string $description,
		string $name
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO work_groups (
				work_groups_id,
				projects_id,
				owner,
				description,
				name
			) VALUES (
				:work_groups_id,
				:projects_id,
				:owner,
				:description,
				:name
			);
			SQL
		);

		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "WorkGroup");
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
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			[
				"errorCode" => $errCode,
				"errorInfo" => $errorInfo,
			],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "WorkGroup");
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
