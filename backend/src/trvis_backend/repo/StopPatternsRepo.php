<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stop_patterns — Project-rooted (direct via projects_id).
 *
 * DB note: `project_lines_id` (DB) ↔ `lines_id` (API).
 * SELECT aliases project_lines_id AS lines_id; UPDATE maps 'lines_id' key to
 * "project_lines_id = :lines_id" in the SET clause.
 *
 * The `description` column (NOT NULL, DEFAULT '') is intentionally absent from
 * the API schema — INSERT relies on the default.
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in the
 * 4-exclude carve-out). Canonical precedent: ProjectsRepo::fetchResultToProject.
 */
final class StopPatternsRepo
{
	public function __construct(
		private PDO $db,
		private LoggerInterface $logger,
	) {
	}

	private static function fetchResultToStopPattern(
		mixed $data
	): StopPattern {
		$stopPattern = new StopPattern();
		$stopPattern->setData([
			'stop_patterns_id' => Uuid::fromBytes($data['stop_patterns_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'lines_id' => Uuid::fromBytes($data['lines_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'name' => $data['name'],
			'from_project_stations_id' => is_null($data['from_project_stations_id'])
				? null
				: Uuid::fromBytes($data['from_project_stations_id']),
			'to_project_stations_id' => is_null($data['to_project_stations_id'])
				? null
				: Uuid::fromBytes($data['to_project_stations_id']),
			'direction' => intval($data['direction']),
		]);
		return $stopPattern;
	}

	/**
	 * @return RetValueOrError<StopPattern>
	 */
	public function selectStopPatternOne(
		string $userId,
		UuidInterface $stopPatternId
	): RetValueOrError {
		$this->logger->debug(
			"selectOne stopPatternId: {stopPatternId}",
			['stopPatternId' => $stopPatternId],
		);
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$query = $this->db->prepare(<<<SQL
			SELECT
				stop_patterns.stop_patterns_id,
				stop_patterns.projects_id,
				stop_patterns.project_lines_id AS lines_id,
				stop_patterns.created_at,
				stop_patterns.name,
				stop_patterns.from_project_stations_id,
				stop_patterns.to_project_stations_id,
				stop_patterns.direction
			FROM
				stop_patterns
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
				projects_privileges.projects_id = stop_patterns.projects_id
			WHERE
				stop_patterns.stop_patterns_id = :stop_patterns_id
			AND
				stop_patterns.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':stop_patterns_id', $stopPatternId->getBytes(), PDO::PARAM_STR);

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
				"StopPattern not found ({stopPatternId})",
				['stopPatternId' => $stopPatternId],
			);
			return Utils::errStopPatternNotFound();
		}

		$stopPattern = $this->fetchResultToStopPattern($data);
		$this->logger->debug(
			"select result - stopPattern: {stopPattern}",
			['stopPattern' => $stopPattern],
		);
		return RetValueOrError::withValue($stopPattern);
	}

	/**
	 * Bulk faithful mirror of selectStopPatternOne: identical projection
	 * (including the `project_lines_id AS lines_id` alias) / FROM /
	 * privilege-JOIN / WHERE filters and row→model mapping
	 * ($this->fetchResultToStopPattern), only the single-id predicate becomes
	 * `stop_patterns.stop_patterns_id IN (...)`. Element order is NOT
	 * guaranteed; the caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<StopPattern>>
	 */
	public function selectListByIds(
		string $userId,
		array $idList,
	): RetValueOrError {
		$this->logger->debug(
			"selectListByIds count: {count}",
			['count' => count($idList)],
		);

		if (empty($idList)) {
			return RetValueOrError::withValue([]);
		}

		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$placeholders = implode(',', array_map(fn ($i) => ":id_$i", array_keys($idList)));
		$query = $this->db->prepare(<<<SQL
			SELECT
				stop_patterns.stop_patterns_id,
				stop_patterns.projects_id,
				stop_patterns.project_lines_id AS lines_id,
				stop_patterns.created_at,
				stop_patterns.name,
				stop_patterns.from_project_stations_id,
				stop_patterns.to_project_stations_id,
				stop_patterns.direction
			FROM
				stop_patterns
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
				projects_privileges.projects_id = stop_patterns.projects_id
			WHERE
				stop_patterns.stop_patterns_id IN ($placeholders)
			AND
				stop_patterns.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		foreach ($idList as $i => $id) {
			$query->bindValue(":id_$i", $id->getBytes(), PDO::PARAM_STR);
		}

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

		$stopPatterns = array_map(
			fn ($data) => $this->fetchResultToStopPattern($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stopPatterns);
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
		$WHERE_TOP_ID = $hasTopId ? ' stop_patterns.stop_patterns_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			stop_patterns.stop_patterns_id,
			stop_patterns.projects_id,
			stop_patterns.project_lines_id AS lines_id,
			stop_patterns.created_at,
			stop_patterns.name,
			stop_patterns.from_project_stations_id,
			stop_patterns.to_project_stations_id,
			stop_patterns.direction

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				stop_patterns_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				stop_patterns
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
				projects_privileges.projects_id = stop_patterns.projects_id
			WHERE
				stop_patterns.projects_id = :projects_id
			AND
				$WHERE_TOP_ID
				stop_patterns.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<StopPattern>>
	 */
	public function selectStopPatternPage(
		UuidInterface $projectsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(projectsId:{projectsId}, userId:{userId}, page:{page})",
			[
				'projectsId' => $projectsId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
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

		$stopPatterns = array_map(
			fn ($data) => $this->fetchResultToStopPattern($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($stopPatterns);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectStopPatternPageTotalCount(
		UuidInterface $projectsId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
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
	 * @return RetValueOrError<null>
	 */
	public function insertStopPattern(
		UuidInterface $stopPatternId,
		UuidInterface $projectsId,
		string $owner,
		string $name,
		UuidInterface $linesId,
		int $direction,
		?UuidInterface $fromProjectStationsId,
		?UuidInterface $toProjectStationsId,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO stop_patterns (
				stop_patterns_id,
				projects_id,
				project_lines_id,
				owner,
				name,
				from_project_stations_id,
				to_project_stations_id,
				direction
			) VALUES (
				:stop_patterns_id,
				:projects_id,
				:lines_id,
				:owner,
				:name,
				:from_project_stations_id,
				:to_project_stations_id,
				:direction
			);
			SQL
		);

		$query->bindValue(':stop_patterns_id', $stopPatternId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':lines_id', $linesId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);
		$query->bindValue(':from_project_stations_id', $fromProjectStationsId?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':to_project_stations_id', $toProjectStationsId?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':direction', $direction, PDO::PARAM_INT);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "StopPattern");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateStopPattern(
		UuidInterface $stopPatternId,
		?string $name,
		?UuidInterface $linesId,
		?int $direction,
		?UuidInterface $fromProjectStationsId,
		?UuidInterface $toProjectStationsId,
	): RetValueOrError {
		$hasName = !is_null($name);
		$hasLinesId = !is_null($linesId);
		$hasDirection = !is_null($direction);
		$hasFromProjectStationsId = !is_null($fromProjectStationsId);
		$hasToProjectStationsId = !is_null($toProjectStationsId);

		$this->logger->info(
			"updateStopPattern({stopPatternId})",
			['stopPatternId' => $stopPatternId],
		);

		if (
            !$hasName && !$hasLinesId && !$hasDirection
			&& !$hasFromProjectStationsId && !$hasToProjectStationsId
		) {
			return RetValueOrError::withValue(null);
		}

		$setParts = [];
		if ($hasName) {
			$setParts[] = 'name = :name';
		}
		if ($hasLinesId) {
			// API `lines_id` -> DB `project_lines_id` alias
			$setParts[] = 'project_lines_id = :lines_id';
		}
		if ($hasDirection) {
			$setParts[] = 'direction = :direction';
		}
		if ($hasFromProjectStationsId) {
			$setParts[] = 'from_project_stations_id = :from_project_stations_id';
		}
		if ($hasToProjectStationsId) {
			$setParts[] = 'to_project_stations_id = :to_project_stations_id';
		}

		$setClause = implode(', ', $setParts);

		$query = $this->db->prepare(<<<SQL
			UPDATE stop_patterns SET
				$setClause
			WHERE
				stop_patterns_id = :stop_patterns_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stop_patterns_id', $stopPatternId->getBytes(), PDO::PARAM_STR);
		if ($hasName) {
			$query->bindValue(':name', $name, PDO::PARAM_STR);
		}
		if ($hasLinesId) {
			$query->bindValue(':lines_id', $linesId->getBytes(), PDO::PARAM_STR);
		}
		if ($hasDirection) {
			$query->bindValue(':direction', $direction, PDO::PARAM_INT);
		}
		if ($hasFromProjectStationsId) {
			$query->bindValue(':from_project_stations_id', $fromProjectStationsId->getBytes(), PDO::PARAM_STR);
		}
		if ($hasToProjectStationsId) {
			$query->bindValue(':to_project_stations_id', $toProjectStationsId->getBytes(), PDO::PARAM_STR);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"StopPattern not found ({stopPatternId})",
						['stopPatternId' => $stopPatternId],
					);
					return Utils::errStopPatternNotFound();
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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "StopPattern");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteStopPattern(
		UuidInterface $stopPatternId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			"deleteStopPattern({stopPatternId}, {deletedAt})",
			[
				'stopPatternId' => $stopPatternId,
				'deletedAt' => $deletedAt,
			],
		);
		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(<<<SQL
			UPDATE
				stop_patterns
			SET
				deleted_at = $deletedAtPlaceholder
			WHERE
				stop_patterns_id = :stop_patterns_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':stop_patterns_id', $stopPatternId->getBytes(), PDO::PARAM_STR);
		if ($hasDeletedAt) {
			$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
		}

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"StopPattern not found ({stopPatternId})",
					['stopPatternId' => $stopPatternId],
				);
				return Utils::errStopPatternNotFound();
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
