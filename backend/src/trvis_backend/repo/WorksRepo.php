<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorksRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private const SQL_COLUMNS = <<<SQL
		works_id,
		work_groups_id,
		created_at,
		description,
		name,
		affect_date,
		affix_content_type,
		affix_file_name,
		remarks,
		has_e_train_timetable,
		e_train_timetable_content_type,
		e_train_timetable_file_name

	SQL;

	private static function _fetchResultToObj(
		mixed $d
	): Work {
		$result = new Work();
		$result->setData([
			'works_id' => Uuid::fromBytes($d['works_id']),
			'work_groups_id' => Uuid::fromBytes($d['work_groups_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'description' => $d['description'],
			'name' => $d['name'],
			'affect_date' => Utils::dbDateStrToDateTime($d['affect_date']),
			'affix_content_type' => TrvisContentType::fromOrNull($d['affix_content_type']),
			// TODO: implement affix_content
			'affix_content' => null,
			'remarks' => $d['remarks'],
			'has_e_train_timetable' => boolval($d['has_e_train_timetable']),
			'e_train_timetable_content_type' => TrvisContentType::fromOrNull($d['e_train_timetable_content_type']),
			// TODO: implement e_train_timetable_content
			'e_train_timetable_content' => null,
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		works_id,
		work_groups_id,
		description,
		owner,
		name,
		affect_date,
		affix_content_type,
		affix_file_name,
		remarks,
		has_e_train_timetable,
		e_train_timetable_content_type,
		e_train_timetable_file_name
	)
	SQL;
	static function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:works_id_{$i},
				:work_groups_id,
				:description_{$i},
				:owner,
				:name_{$i},
				:affect_date_{$i},
				:affix_content_type_{$i},
				:affix_file_name_{$i},
				:remarks_{$i},
				:has_e_train_timetable_{$i},
				:e_train_timetable_content_type_{$i},
				:e_train_timetable_file_name_{$i}
			)
		SQL;
	}
	static function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $worksId,
		Work $work,
	) {
		$query->bindValue(":works_id_$i", $worksId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $work->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $work->name, PDO::PARAM_STR);
		$query->bindValue(":affect_date_$i", Utils::utcDateStrOrNull($work->affect_date), PDO::PARAM_STR);
		$query->bindValue(":affix_content_type_$i", $work->affix_content_type?->value, PDO::PARAM_INT);
		// TODO: implement affix_content
		$query->bindValue(":affix_file_name_$i", null, PDO::PARAM_STR);
		$query->bindValue(":remarks_$i", $work->remarks, PDO::PARAM_STR);
		$query->bindValue(":has_e_train_timetable_$i", $work->has_e_train_timetable, PDO::PARAM_BOOL);
		$query->bindValue(":e_train_timetable_content_type_$i", $work->e_train_timetable_content_type?->value, PDO::PARAM_INT);
		// TODO: implement e_train_timetable_content
		$query->bindValue(":e_train_timetable_file_name_$i", null, PDO::PARAM_STR);
	}

	/**
	 * @return RetValueOrError<Work>
	 */
	public function selectOne(
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne workId: {worksId}',
			[
				'worksId' => $worksId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_COLUMNS . <<<SQL
				FROM
					works
				WHERE
					works_id = :works_id
				AND
					deleted_at IS NULL
				SQL
			);

			$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne - rowCount is 0',
					[
						'workId' => $worksId,
					],
				);
				return Utils::errWorkNotFound();
			}

			return RetValueOrError::withValue(self::_fetchResultToObj($result));
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
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId workId: {worksId}',
			[
				'worksId' => $worksId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					work_groups_id
				FROM
					works
				WHERE
					works_id = :works_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return Utils::errWorkNotFound();
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
	 * @return RetValueOrError<array<Work>>
	 */
	public function selectPage(
		UuidInterface $workGroupsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList workGroupsId: {workGroupsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'workGroupsId' => $workGroupsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$hasTopId = !is_null($topId);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_COLUMNS . <<<SQL
				FROM
					works
				WHERE
					work_groups_id = :work_groups_id
				AND
					deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : ' AND works_id <= :top_id ')
				.
				<<<SQL
				ORDER BY
					works_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
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
			if ($result === false) {
				$this->logger->warning('selectList - rowCount is 0');
				return Utils::errWorkNotFound();
			}

			$works = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($works);
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
	 * @return RetValueOrError<array<Work>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $worksIdList */
		array $worksIdList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList worksIdList: {worksIdList}",
			[
				"worksIdList" => $worksIdList,
			],
		);

		$worksIdListCount = count($worksIdList);
		$placeholders = implode(',', array_map(
			fn($i) => ":works_id_$i",
			range(0, $worksIdListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_COLUMNS . <<<SQL
				FROM
					works
				WHERE
					works_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $worksIdListCount; $i++) {
				$query->bindValue(":works_id_$i", $worksIdList[$i]->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$this->logger->debug(
				'rorCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			$result = $query->fetchAll(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectList - rowCount is 0');
				return Utils::errWorkNotFound();
			}

			$works = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($works);
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
	 * @return RetValueOrError<null>
	 */
	public function insertList(
		UuidInterface $workGroupsId,
		string $ownerUserId,
		/** @param array<UuidInterface> $works */
		array $worksIdList,
		/** @param array<Work> $works */
		array $works,
	): RetValueOrError {
		$this->logger->debug(
			'insertList workGroupsId: {workGroupsId}, works: {works}',
			[
				'workGroupsId' => $workGroupsId,
				'works' => $works,
			],
		);

		try
		{
			$worksCount = count($works);
			$query = $this->db->prepare(
				'INSERT INTO works'
				.
				self::SQL_INSERT_COLUMNS
				.
				' VALUES '
				.
				implode(',', array_map(
					fn($i) => self::_genInsertValuesQuerySegment($i),
					range(0, $worksCount - 1),
				))
			);
			$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $worksCount; $i++) {
				self::_setInsertValues($query, $i, $worksIdList[$i], $works[$i]);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'insertList - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
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
	 * @return RetValueOrError<null>
	 */
	public function update(
		UuidInterface $worksId,
		array $worksProps,
	): RetValueOrError {
		$this->logger->debug(
			'updateList worksId: {worksId}, works: {works}',
			[
				'worksId' => $worksId,
				'works' => $worksProps,
			],
		);

		if (count($worksProps) === 0) {
			return RetValueOrError::withValue(null);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE works SET "
				.
				implode(',', array_map(fn($key) => "{$key} = :{$key}", array_keys($worksProps)))
				.
				" WHERE works_id = :works_id AND deleted_at IS NULL"
			);
			$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
			foreach ($worksProps as $key => $value) {
				$paramType = PDO::PARAM_STR;
				if ($key === 'has_e_train_timetable') {
					$paramType = PDO::PARAM_BOOL;
				} elseif ($key === 'affix_content_type' || $key === 'e_train_timetable_content_type') {
					$paramType = PDO::PARAM_INT;
				}
				$query->bindValue(":{$key}", $value, $paramType);
			}

			$query->execute();
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

	public function deleteOne(
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne worksId: {worksId}",
			[
				"worksId" => $worksId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					works
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					works_id = :works_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":works_id", $worksId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteByWorkGroupsId - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"Work not found ({worksId})",
					[
						"worksId" => $worksId,
					],
				);
				return Utils::errWorkNotFound();
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

	public function deleteByWorkGroupsId(
		UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByWorkGroupsId workGroupsId: {workGroupsId}",
			[
				"workGroupsId" => $workGroupsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					works
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					work_groups_id = :work_groups_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":work_groups_id", $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$this->logger->debug(
				"deleteByWorkGroupsId - rowCount: {rowCount}",
				[
					"rowCount" => $query->rowCount(),
				],
			);
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
}
