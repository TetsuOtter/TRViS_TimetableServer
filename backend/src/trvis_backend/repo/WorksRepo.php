<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use DateTimeInterface;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\DataWithId;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\TRViSJsonWork;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * works — WorkGroup-rooted (work_groups_id FK).
 *
 * selectPrivilegeType resolves work_groups_id from the works table then
 * delegates to WorkGroupsPrivilegesRepo (which in turn resolves through
 * ProjectsPrivilegesRepo for new-style WGs). This reproduces the legacy
 * MyRepoBase::selectPrivilegeType chain without the base-class machinery.
 *
 * affix_content / e_train_timetable_content are schema-present but never
 * persisted — the DB has _file_name columns instead. They always read null;
 * INSERTs write null to the _file_name column.
 *   TODO: implement affix_content / affix_file_name storage
 *   TODO: implement e_train_timetable_content / e_train_timetable_file_name storage
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in the
 * 4-exclude carve-out). Canonical precedent: ProjectsRepo.
 */
final class WorksRepo implements IMyRepoSelectPrivilegeType
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
		private readonly int $dumpMaxRowsPerTable = Constants::DUMP_MAX_ROWS_PER_TABLE,
	) {
	}

	private static function fetchResultToWork(
		mixed $data
	): Work {
		$work = new Work();
		$work->setData([
			'works_id' => Uuid::fromBytes($data['works_id']),
			'work_groups_id' => Uuid::fromBytes($data['work_groups_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'description' => $data['description'],
			'name' => $data['name'],
			'affect_date' => Utils::dbDateStrToDateTime($data['affect_date']),
			'affix_content_type' => TrvisContentType::fromOrNull($data['affix_content_type']),
			// TODO: implement affix_content
			'affix_content' => null,
			'remarks' => $data['remarks'],
			'has_e_train_timetable' => boolval($data['has_e_train_timetable']),
			'e_train_timetable_content_type' => TrvisContentType::fromOrNull($data['e_train_timetable_content_type']),
			// TODO: implement e_train_timetable_content
			'e_train_timetable_content' => null,
		]);
		return $work;
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
			"selectPrivilegeType(userId:{userId}, worksId:{worksId})",
			[
				'userId' => $userId,
				'worksId' => $id,
			],
		);

		try {
			$query = $this->db->prepare(
				'SELECT work_groups_id FROM works WHERE works_id = :works_id AND deleted_at IS NULL'
				. ($selectForUpdate ? ' FOR UPDATE' : '')
				. ';'
			);
			$query->bindValue(':works_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errWorkNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$workGroupsId = Uuid::fromBytes($row['work_groups_id']);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				'failed to resolve work_groups_id for work ({errorCode})',
				['errorCode' => $errCode],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}

		return (new WorkGroupsPrivilegesRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $workGroupsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}

	/**
	 * @return RetValueOrError<Work>
	 */
	public function selectWorkOne(
		string $userId,
		UuidInterface $workId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne workId: {workId}",
			['workId' => $workId],
		);
		$WHERE_USER_ID =
			$userId === Constants::UID_ANONYMOUS
				? ' uid = :userId '
				: ' uid IN (:userId, \'\') ';
		$WHERE_PRIVILEGE_TYPE = ' privilege_type >= ' . InviteKeyPrivilegeType::read->value . ' ';
		$query = $this->db->prepare(<<<SQL
			SELECT
				works.works_id,
				works.work_groups_id,
				works.created_at,
				works.description,
				works.name,
				works.affect_date,
				works.affix_content_type,
				works.remarks,
				works.has_e_train_timetable,
				works.e_train_timetable_content_type
			FROM
				works
			JOIN
				work_groups
			ON
				work_groups.work_groups_id = works.work_groups_id
			AND
				work_groups.deleted_at IS NULL
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
				works.works_id = :works_id
			AND
				works.deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':userId', $userId, PDO::PARAM_STR);
		$query->bindValue(':works_id', $workId->getBytes(), PDO::PARAM_STR);

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
			$this->logger->info(
				"Work not found ({workId})",
				['workId' => $workId],
			);
			return Utils::errWorkNotFound();
		}

		$work = self::fetchResultToWork($data);
		$this->logger->debug(
			"select result - work: {work}",
			['work' => $work],
		);
		return RetValueOrError::withValue($work);
	}

	/**
	 * Bulk faithful mirror of selectWorkOne: identical projection / FROM /
	 * privilege-JOIN (including the `AND work_groups.deleted_at IS NULL`
	 * join predicate) / WHERE filters, only the single-id predicate becomes
	 * `works.works_id IN (...)`. Element order is NOT guaranteed; the caller
	 * reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<Work>>
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
				works.works_id,
				works.work_groups_id,
				works.created_at,
				works.description,
				works.name,
				works.affect_date,
				works.affix_content_type,
				works.remarks,
				works.has_e_train_timetable,
				works.e_train_timetable_content_type
			FROM
				works
			JOIN
				work_groups
			ON
				work_groups.work_groups_id = works.work_groups_id
			AND
				work_groups.deleted_at IS NULL
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
				works.works_id IN ($placeholders)
			AND
				works.deleted_at IS NULL
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

		$works = array_map(
			fn ($data) => self::fetchResultToWork($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($works);
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
		$WHERE_TOP_ID = $hasTopId ? ' works.works_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			works.works_id,
			works.work_groups_id,
			works.created_at,
			works.description,
			works.name,
			works.affect_date,
			works.affix_content_type,
			works.remarks,
			works.has_e_train_timetable,
			works.e_train_timetable_content_type

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				works_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				works
			JOIN
				work_groups
			ON
				work_groups.work_groups_id = works.work_groups_id
			AND
				work_groups.deleted_at IS NULL
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
				works.work_groups_id = :work_groups_id
			AND
				$WHERE_TOP_ID
				works.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Work>>
	 */
	public function selectWorkPage(
		UuidInterface $workGroupsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(workGroupsId:{workGroupsId}, userId:{userId}, page:{page})",
			[
				'workGroupsId' => $workGroupsId,
				'userId' => $userId,
				'page' => $pageFrom1,
			],
		);
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, false));
		$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
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

		$works = array_map(
			fn ($data) => self::fetchResultToWork($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($works);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectWorkPageTotalCount(
		UuidInterface $workGroupsId,
		string $userId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageQuery($userId, $hasTopId, true));
		$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
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
	public function insertWork(
		UuidInterface $worksId,
		UuidInterface $workGroupsId,
		string $owner,
		string $name,
		string $description,
		?DateTimeInterface $affectDate,
		?TrvisContentType $affixContentType,
		?string $remarks,
		bool $hasETrainTimetable,
		?TrvisContentType $eTrainTimetableContentType,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO works (
				works_id,
				work_groups_id,
				owner,
				name,
				description,
				affect_date,
				affix_content_type,
				affix_file_name,
				remarks,
				has_e_train_timetable,
				e_train_timetable_content_type,
				e_train_timetable_file_name
			) VALUES (
				:works_id,
				:work_groups_id,
				:owner,
				:name,
				:description,
				:affect_date,
				:affix_content_type,
				:affix_file_name,
				:remarks,
				:has_e_train_timetable,
				:e_train_timetable_content_type,
				:e_train_timetable_file_name
			);
			SQL
		);

		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':affect_date', Utils::utcDateStrOrNull($affectDate), PDO::PARAM_STR);
		$query->bindValue(':affix_content_type', $affixContentType?->value, PDO::PARAM_INT);
		// TODO: implement affix_content
		$query->bindValue(':affix_file_name', null, PDO::PARAM_STR);
		$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
		$query->bindValue(':has_e_train_timetable', $hasETrainTimetable, PDO::PARAM_BOOL);
		$query->bindValue(':e_train_timetable_content_type', $eTrainTimetableContentType?->value, PDO::PARAM_INT);
		// TODO: implement e_train_timetable_content
		$query->bindValue(':e_train_timetable_file_name', null, PDO::PARAM_STR);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Work");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateWork(
		UuidInterface $worksId,
		?string $name,
		bool $hasName,
		?string $description,
		bool $hasDescription,
		?DateTimeInterface $affectDate,
		bool $hasAffectDate,
		?TrvisContentType $affixContentType,
		bool $hasAffixContentType,
		?string $remarks,
		bool $hasRemarks,
		?bool $hasETrainTimetable,
		bool $hasHasETrainTimetable,
		?TrvisContentType $eTrainTimetableContentType,
		bool $hasETrainTimetableContentType,
	): RetValueOrError {
		$this->logger->info(
			"updateWork({worksId})",
			['worksId' => $worksId],
		);

		if (
			!$hasName && !$hasDescription && !$hasAffectDate
			&& !$hasAffixContentType && !$hasRemarks
			&& !$hasHasETrainTimetable && !$hasETrainTimetableContentType
		) {
			return RetValueOrError::withValue(null);
		}

		$setParts = [];
		if ($hasName) {
			$setParts[] = 'name = :name';
		}
		if ($hasDescription) {
			$setParts[] = 'description = :description';
		}
		if ($hasAffectDate) {
			$setParts[] = 'affect_date = :affect_date';
		}
		if ($hasAffixContentType) {
			$setParts[] = 'affix_content_type = :affix_content_type';
		}
		if ($hasRemarks) {
			$setParts[] = 'remarks = :remarks';
		}
		if ($hasHasETrainTimetable) {
			$setParts[] = 'has_e_train_timetable = :has_e_train_timetable';
		}
		if ($hasETrainTimetableContentType) {
			$setParts[] = 'e_train_timetable_content_type = :e_train_timetable_content_type';
		}

		$setClause = implode(', ', $setParts);

		$query = $this->db->prepare(<<<SQL
			UPDATE works SET
				$setClause
			WHERE
				works_id = :works_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		if ($hasName) {
			$query->bindValue(':name', $name, PDO::PARAM_STR);
		}
		if ($hasDescription) {
			$query->bindValue(':description', $description, PDO::PARAM_STR);
		}
		if ($hasAffectDate) {
			$query->bindValue(':affect_date', Utils::utcDateStrOrNull($affectDate), PDO::PARAM_STR);
		}
		if ($hasAffixContentType) {
			$query->bindValue(':affix_content_type', $affixContentType?->value, PDO::PARAM_INT);
		}
		if ($hasRemarks) {
			$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
		}
		if ($hasHasETrainTimetable) {
			$query->bindValue(':has_e_train_timetable', $hasETrainTimetable, PDO::PARAM_BOOL);
		}
		if ($hasETrainTimetableContentType) {
			$query->bindValue(':e_train_timetable_content_type', $eTrainTimetableContentType?->value, PDO::PARAM_INT);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"Work not found ({worksId})",
						['worksId' => $worksId],
					);
					return Utils::errWorkNotFound();
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

		return RetValueOrError::withError(
			Constants::HTTP_INTERNAL_SERVER_ERROR,
			"Failed to execute SQL - " . $errCode,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteWork(
		UuidInterface $worksId,
		?DateTimeInterface $deletedAt = null,
	): RetValueOrError {
		$this->logger->info(
			"deleteWork({worksId}, {deletedAt})",
			[
				'worksId' => $worksId,
				'deletedAt' => $deletedAt,
			],
		);
		$hasDeletedAt = !is_null($deletedAt);
		$deletedAtPlaceholder = $hasDeletedAt ? ':deleted_at' : 'CURRENT_TIMESTAMP()';
		$query = $this->db->prepare(<<<SQL
			UPDATE
				works
			SET
				deleted_at = $deletedAtPlaceholder
			WHERE
				works_id = :works_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		if ($hasDeletedAt) {
			$query->bindValue($deletedAtPlaceholder, Utils::utcDateStrOrNull($deletedAt), PDO::PARAM_STR);
		}

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"Work not found ({worksId})",
					['worksId' => $worksId],
				);
				return Utils::errWorkNotFound();
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

	/**
	 * @param array<UuidInterface> $parentIdList
	 * @param array<string, array<DataWithId<TRViSJsonWork>>> $dst
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function dump(
		array $parentIdList,
		array &$dst,
	): RetValueOrError {
		$this->logger->debug(
			'WorksRepo::dump() called - {parentIdList}',
			[
				'parentIdList' => $parentIdList,
			],
		);

		$parentIdCount = count($parentIdList);
		if ($parentIdCount === 0) {
			// 親IDが空のとき IN () となり MySQL 構文エラー(42000)になるため、
			// クエリを組み立てず空結果を返す（親が無ければ子も無い）。
			return RetValueOrError::withValue([]);
		}
		$parentIdListPlaceholder = implode(', ', array_fill(0, $parentIdCount, '?'));
		try {
			// station_tracksとのJOINは、本当はstations_idも条件として加えるべきである。
			// しかし、実装上の都合で同じWorkGroupの他の駅に属するstation_tracksも登録できてしまう。
			// そのため、stations_idでの絞り込みは行わない。
			$dumpLimit = $this->dumpMaxRowsPerTable + 1;
			$query = $this->db->prepare(<<<SQL
				SELECT
					HEX(works.work_groups_id) AS parent_id,
					works.works_id AS works_id,
					works.name AS Name,
					works.affect_date AS AffectDate,
					works.affix_content_type AS AffixContentType,
					works.affix_file_name AS AffixFileName,
					works.remarks AS Remarks,
					works.has_e_train_timetable AS HasETrainTimetable,
					works.e_train_timetable_content_type AS ETrainTimetableContentType,
					works.e_train_timetable_file_name AS ETrainTimetableFileName
				FROM
					works
				WHERE
					work_groups_id IN ($parentIdListPlaceholder)
				AND
					works.deleted_at IS NULL
				LIMIT $dumpLimit
				SQL
			);
			for ($i = 0; $i < $parentIdCount; ++$i) {
				$query->bindValue($i + 1, $parentIdList[$i]->getBytes(), PDO::PARAM_STR);
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'selected work {rowCount} rows.',
				[
					'rowCount' => $rowCount,
				],
			);
			if ($rowCount === 0) {
				return RetValueOrError::withValue([]);
			}
			if ($rowCount > $this->dumpMaxRowsPerTable) {
				$this->logger->error(
					'WorksRepo::dump() cap exceeded: {rowCount} rows > {cap}.',
					[
						'rowCount' => $rowCount,
						'cap' => $this->dumpMaxRowsPerTable,
					],
				);
				return RetValueOrError::withError(
					Constants::HTTP_PAYLOAD_TOO_LARGE,
					'Dump aborted: works row count exceeds the per-table limit ('
						. $this->dumpMaxRowsPerTable . '). Reduce the work group size.',
				);
			}

			$rowCountPerParent = [];
			$worksIdList = [];
			$worksIdListIndex = 0;
			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$parentId = $row['parent_id'];
				if (!array_key_exists($parentId, $rowCountPerParent)) {
					$rowCountPerParent[$parentId] = 0;
				}
				$d = self::fetchResultRowToTrvisJsonData($row);
				$dst[$parentId][$rowCountPerParent[$parentId]++] = $d;
				$worksIdList[$worksIdListIndex++] = $d->id;
			}

			return RetValueOrError::withValue($worksIdList);
		} catch (\PDOException $e) {
			$this->logger->error(
				'Failed to dump work rows. - {exception}',
				[
					'exception' => $e,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'Failed to execute SQL - ' . $e->getCode(),
			);
		}
	}

	/**
	 * @param array<string, mixed> $kvpList
	 * @return DataWithId<TRViSJsonWork>
	 */
	private static function fetchResultRowToTrvisJsonData(
		array $kvpList,
	): DataWithId {
		$d = new TRViSJsonWork();
		$d->setData([
			'Name' => $kvpList['Name'],
			'AffectDate' => Utils::dbDateStrToDateTime($kvpList['AffectDate']),
			'AffixContentType' => TrvisContentType::fromOrNull($kvpList['AffixContentType']),
			'AffixContent' => null,
			'Remarks' => $kvpList['Remarks'],
			'HasETrainTimetable' => boolval($kvpList['HasETrainTimetable']),
			'ETrainTimetableContentType' => TrvisContentType::fromOrNull($kvpList['ETrainTimetableContentType']),
			'ETrainTimetableContent' => null,

			'Trains' => [],
		]);
		return new DataWithId(
			id: Uuid::fromBytes($kvpList['works_id']),
			data: $d,
		);
	}
}
