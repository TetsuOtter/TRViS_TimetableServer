<?php

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Project;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectsRepo
{
	public function __construct(
		private PDO $db,
		private LoggerInterface $logger,
	) {
	}

	private static function _fetchResultToProject(
		mixed $data
	): Project {
		$project = new Project();
		$project->setData([
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'created_at' => $data['created_at'],
			'description' => $data['description'],
			'name' => $data['name'],
			'privilege_type' => InviteKeyPrivilegeType::fromInt($data['privilege_type']),
		]);
		return $project;
	}

	/**
	 * @return RetValueOrError<Project>
	 */
	public function selectProjectOne(
		string $userId,
		UuidInterface $projectId
	): RetValueOrError {
		$this->logger->debug("selectOne projectId: {projectId}", ['projectId' => $projectId]);
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$query = $this->db->prepare(<<<SQL
			SELECT
				projects.projects_id,
				projects.created_at,
				projects.description,
				projects.name,
				projects_privileges.privilege_type
			FROM
				projects
			JOIN
				(SELECT
					projects_id,
					MAX(privilege_type) AS privilege_type
				FROM
					projects_privileges
				WHERE
					projects_id = :projects_id
				AND
					deleted_at IS NULL
				AND
					$WHERE_USER_ID
				AND
					$WHERE_PRIVILEGE_TYPE
				) AS projects_privileges
			USING
				(projects_id)
			WHERE
				projects.projects_id = :projects_id
			AND
				projects.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);

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
				"Project not found ({projectId})",
				[
					'projectId' => $projectId,
				]
			);
			return Utils::errProjectNotFound();
		}

		$project = $this->_fetchResultToProject($data);
		$this->logger->debug("select result - project: {project}", ['project' => $project]);
		return RetValueOrError::withValue($project);
	}

	private static function getSelectPageQuery(
		string $userId,
		bool $hasTopId,
		bool $countOnly,
	): string {
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$WHERE_TOP_ID = $hasTopId ? ' projects.projects_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			projects_id,
			projects.created_at AS created_at,
			projects.description AS description,
			name,
			projects_privileges.privilege_type

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				projects_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				projects
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
			USING
				(projects_id)
			WHERE
				$WHERE_TOP_ID
				projects.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Project>>
	 */
	public function selectProjectPage(
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

		$projects = array_map(
			fn ($data) => $this->_fetchResultToProject($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);

		$this->logger->debug("select result - project: {projects}", ['projects' => $projects]);
		return RetValueOrError::withValue($projects);
	}

	/**
	 * @return RetValueOrError<number>
	 */
	public function selectProjectPageTotalCount(
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
		$totalCount = $query->fetch(PDO::FETCH_ASSOC)['count'];

		$this->logger->debug("select result - totalCount: {totalCount}", ['totalCount' => $totalCount]);
		return RetValueOrError::withValue($totalCount);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function insertProject(
		UuidInterface $projectId,
		string $owner,
		string $description,
		string $name
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO projects (
				projects_id,
				owner,
				description,
				name
			) VALUES (
				:projects_id,
				:owner,
				:description,
				:name
			);
			SQL
		);

		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);
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
			return RetValueOrError::withError(Constants::HTTP_CONFLICT, "Project already exists");
		}

		return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, "Failed to execute SQL - " . $errCode);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateProject(
		UuidInterface $projectId,
		?string $description,
		?string $name
	): RetValueOrError {
		$hasName = !is_null($name);
		$hasDescription = !is_null($description);
		$this->logger->info(
			"updateProject({projectId}, '{description}', '{name}') -> hasName: {hasName}, hasDescription: {hasDescription}",
			[
				'projectId' => $projectId,
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
			UPDATE projects SET
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
				projects_id = :projects_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);
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
						"Project not found ({projectId})",
						[
							'projectId' => $projectId,
						]
					);
					return Utils::errProjectNotFound();
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
			return RetValueOrError::withError(Constants::HTTP_CONFLICT, "Project already exists");
		}

		return RetValueOrError::withError(Constants::HTTP_INTERNAL_SERVER_ERROR, "Failed to execute SQL - " . $errCode);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteProject(
		UuidInterface $projectId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			"deleteProject({projectId}, {deletedAt})",
			[
				'projectId' => $projectId,
				'deletedAt' => $deletedAt,
			]
		);
		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(<<<SQL
			UPDATE
				projects
			SET
				deleted_at = $deletedAtPlaceholder
			WHERE
				projects_id = :projects_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':projects_id', $projectId->getBytes(), PDO::PARAM_STR);
		if ($hasDeletedAt) {
			$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
		}

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"Project not found ({projectId})",
					[
						'projectId' => $projectId,
					]
				);
				return Utils::errProjectNotFound();
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
