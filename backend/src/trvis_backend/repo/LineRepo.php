<?php

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Repo for Line (table: project_lines, PK: project_lines_id).
 *
 * Privilege root is **direct via projects_id** — resolved through
 * projects_privileges keyed on the line's projects_id. The
 * selectPrivilegeType() method does a JOIN from project_lines →
 * projects_privileges so the Service can check privilege with a single
 * call keyed on the line UUID.
 *
 * Column aliasing: `project_lines_id AS lines_id` / `projects_id` so the
 * result set matches the `Line` model's OAS_PROPERTIES.
 */
final class LineRepo
{
	public function __construct(
		private PDO $db,
		private LoggerInterface $logger,
	) {
	}

	private static function fetchResultToLine(
		mixed $data
	): Line {
		$line = new Line();
		$line->setData([
			'lines_id' => Uuid::fromBytes($data['lines_id']),
			'projects_id' => Uuid::fromBytes($data['projects_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'description' => $data['description'],
			'name' => $data['name'],
		]);
		return $line;
	}

	/**
	 * Resolve the maximum privilege_type for $userId on the project that
	 * owns the given line. Returns InviteKeyPrivilegeType (or errLineNotFound
	 * when the line does not exist / is deleted).
	 *
	 * Called directly from tests and from LineService.
	 *
	 * @return RetValueOrError<InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId,
		bool $includeAnonymous,
	): RetValueOrError {
		$this->logger->debug(
			"selectPrivilegeType(lineId:{id}, userId:{userId}, includeAnonymous:{includeAnonymous})",
			['id' => $id, 'userId' => $userId, 'includeAnonymous' => $includeAnonymous]
		);

		if ($userId === Constants::UID_ANONYMOUS) {
			$includeAnonymous = false;
		}
		$WHERE_UID = $includeAnonymous ? ' uid IN (:userId, \'\') ' : ' uid = :userId ';

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					pp.privilege_type
				FROM
					project_lines AS pl
				JOIN
					projects_privileges AS pp
				ON
					pp.projects_id = pl.projects_id
				WHERE
					pl.project_lines_id = :lineId
				AND
					pl.deleted_at IS NULL
				AND
					$WHERE_UID
				AND
					pp.deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':lineId', $id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':userId', $userId, PDO::PARAM_STR);
			$query->execute();

			if ($query->rowCount() === 0) {
				return Utils::errLineNotFound();
			}

			$rows = $query->fetchAll(PDO::FETCH_ASSOC);
			$maxPriv = InviteKeyPrivilegeType::none->value;
			foreach ($rows as $row) {
				$v = intval($row['privilege_type']);
				if ($v > $maxPriv) {
					$maxPriv = $v;
				}
			}
			return RetValueOrError::withValue(InviteKeyPrivilegeType::fromInt($maxPriv));
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $ex->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * @return RetValueOrError<Line>
	 */
	public function selectLineOne(
		string $userId,
		UuidInterface $lineId,
	): RetValueOrError {
		$this->logger->debug("selectLineOne(lineId:{lineId})", ['lineId' => $lineId]);
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';

		$query = $this->db->prepare(<<<SQL
			SELECT
				pl.project_lines_id AS lines_id,
				pl.projects_id,
				pl.description,
				pl.created_at,
				pl.name
			FROM
				project_lines AS pl
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
				) AS pp
			ON
				pp.projects_id = pl.projects_id
			WHERE
				pl.project_lines_id = :lineId
			AND
				pl.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':lineId', $lineId->getBytes(), PDO::PARAM_STR);

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

		$data = $query->fetch(PDO::FETCH_ASSOC);
		if (!$data) {
			$this->logger->info("Line not found ({lineId})", ['lineId' => $lineId]);
			return Utils::errLineNotFound();
		}

		return RetValueOrError::withValue($this->fetchResultToLine($data));
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
		$WHERE_TOP_ID = $hasTopId ? ' pl.project_lines_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			pl.project_lines_id AS lines_id,
			pl.projects_id,
			pl.description,
			pl.created_at,
			pl.name

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				pl.project_lines_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				project_lines AS pl
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
				) AS pp
			ON
				pp.projects_id = pl.projects_id
			WHERE
				pl.projects_id = :projects_id
			AND
				$WHERE_TOP_ID
				pl.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Line>>
	 */
	public function selectLinePage(
		string $userId,
		UuidInterface $projectsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
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

		$lines = array_map(
			fn ($data) => $this->fetchResultToLine($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($lines);
	}

	/**
	 * @return RetValueOrError<number>
	 */
	public function selectLinePageTotalCount(
		string $userId,
		UuidInterface $projectsId,
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
	public function insertLine(
		UuidInterface $lineId,
		UuidInterface $projectsId,
		string $owner,
		string $description,
		string $name,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO project_lines (
				project_lines_id,
				projects_id,
				owner,
				description,
				name
			) VALUES (
				:lineId,
				:projects_id,
				:owner,
				:description,
				:name
			);
			SQL
		);
		$query->bindValue(':lineId', $lineId->getBytes(), PDO::PARAM_STR);
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
			["errorCode" => $errCode, "errorInfo" => $errorInfo],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Line");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateLine(
		UuidInterface $lineId,
		?string $description,
		?string $name,
	): RetValueOrError {
		$hasName = !is_null($name);
		$hasDescription = !is_null($description);
		$this->logger->info(
			"updateLine({lineId}, '{description}', '{name}') -> hasName:{hasName}, hasDescription:{hasDescription}",
			[
				'lineId' => $lineId,
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
			UPDATE project_lines SET
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
				project_lines_id = :lineId
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':lineId', $lineId->getBytes(), PDO::PARAM_STR);
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
					$this->logger->info("Line not found ({lineId})", ['lineId' => $lineId]);
					return Utils::errLineNotFound();
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
			["errorCode" => $errCode, "errorInfo" => $errorInfo],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Line");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteLine(
		UuidInterface $lineId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			"deleteLine({lineId}, {deletedAt})",
			['lineId' => $lineId, 'deletedAt' => $deletedAt]
		);
		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(<<<SQL
			UPDATE
				project_lines
			SET
				deleted_at = $deletedAtPlaceholder
			WHERE
				project_lines_id = :lineId
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':lineId', $lineId->getBytes(), PDO::PARAM_STR);
		if ($hasDeletedAt) {
			$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
		}

		try {
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info("Line not found ({lineId})", ['lineId' => $lineId]);
				return Utils::errLineNotFound();
			} else {
				return RetValueOrError::withValue(null);
			}
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $ex->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}
}
