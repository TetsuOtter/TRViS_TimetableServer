<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class TimetableRowsRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		timetable_rows.timetable_rows_id AS timetable_rows_id,
		timetable_rows.trains_id AS trains_id,
		timetable_rows.stations_id AS stations_id,
		timetable_rows.station_tracks_id AS station_tracks_id,
		timetable_rows.colors_id AS colors_id_marker,

		timetable_rows.description AS description,
		timetable_rows.created_at AS created_at,

		timetable_rows.drive_time_mm AS drive_time_mm,
		timetable_rows.drive_time_ss AS drive_time_ss,

		timetable_rows.is_operation_only_stop AS is_operation_only_stop,
		timetable_rows.is_pass AS is_pass,
		timetable_rows.has_bracket AS has_bracket,
		timetable_rows.is_last_stop AS is_last_stop,

		timetable_rows.arrive_time_hh AS arrive_time_hh,
		timetable_rows.arrive_time_mm AS arrive_time_mm,
		timetable_rows.arrive_time_ss AS arrive_time_ss,

		timetable_rows.departure_time_hh AS departure_time_hh,
		timetable_rows.departure_time_mm AS departure_time_mm,
		timetable_rows.departure_time_ss AS departure_time_ss,

		timetable_rows.run_in_limit AS run_in_limit,
		timetable_rows.run_out_limit AS run_out_limit,

		timetable_rows.remarks AS remarks,

		timetable_rows.arrive_str AS arrive_str,
		timetable_rows.departure_str AS departure_str,

		timetable_rows.marker_text AS marker_text,

		timetable_rows.work_type AS work_type,

	SQL;

	private static function _fetchResultToObj(
		mixed $d,
	): TimetableRow {
		$result = new TimetableRow();
		$result->setData([
			'timetable_rows_id' => Uuid::fromBytes($d['timetable_rows_id']),
			'trains_id' => Uuid::fromBytes($d['trains_id']),
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'colors_id_marker' => Uuid::fromBytes($d['colors_id_marker']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),

			'drive_time_mm' => $d['drive_time_mm'],
			'drive_time_ss' => $d['drive_time_ss'],

			'is_operation_only_stop' => $d['is_operation_only_stop'],
			'is_pass' => $d['is_pass'],
			'has_bracket' => $d['has_bracket'],
			'is_last_stop' => $d['is_last_stop'],

			'arrive_time_hh' => $d['arrive_time_hh'],
			'arrive_time_mm' => $d['arrive_time_mm'],
			'arrive_time_ss' => $d['arrive_time_ss'],

			'departure_time_hh' => $d['departure_time_hh'],
			'departure_time_mm' => $d['departure_time_mm'],
			'departure_time_ss' => $d['departure_time_ss'],

			'run_in_limit' => $d['run_in_limit'],
			'run_out_limit' => $d['run_out_limit'],

			'remarks' => $d['remarks'],

			'arrive_str' => $d['arrive_str'],
			'departure_str' => $d['departure_str'],

			'marker_text' => $d['marker_text'],

			'work_type' => $d['work_type'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		timetable_rows_id,
		trains_id,
		stations_id,
		colors_id,
		description,
		owner,

		drive_time_mm,
		drive_time_ss,

		is_operation_only_stop,
		is_pass,
		has_bracket,
		is_last_stop,

		arrive_time_hh,
		arrive_time_mm,
		arrive_time_ss,

		departure_time_hh,
		departure_time_mm,
		departure_time_ss,

		run_in_limit,
		run_out_limit,

		remarks,

		arrive_str,
		departure_str,

		marker_text,

		work_type
	)
	SQL;
	static function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:timetable_rows_id_{$i},
				:trains_id,
				:stations_id_{$i},
				:colors_id_{$i},
				:description_{$i},
				:owner,

				:drive_time_mm_{$i},
				:drive_time_ss_{$i},

				:is_operation_only_stop_{$i},
				:is_pass_{$i},
				:has_bracket_{$i},
				:is_last_stop_{$i},

				:arrive_time_hh_{$i},
				:arrive_time_mm_{$i},
				:arrive_time_ss_{$i},

				:departure_time_hh_{$i},
				:departure_time_mm_{$i},
				:departure_time_ss_{$i},

				:run_in_limit_{$i},
				:run_out_limit_{$i},

				:remarks_{$i},

				:arrive_str_{$i},
				:departure_str_{$i},

				:marker_text_{$i},

				:work_type_{$i}
			)
		SQL;
	}
	static function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $timetableRowsId,
		TimetableRow $d,
	) {
		$query->bindValue(":timetable_rows_id_$i", $timetableRowsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":stations_id_$i", $d->stations_id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":colors_id_$i", $d->colors_id_marker?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);

		$query->bindValue(":drive_time_mm_$i", $d->drive_time_mm, PDO::PARAM_INT);
		$query->bindValue(":drive_time_ss_$i", $d->drive_time_ss, PDO::PARAM_INT);

		$query->bindValue(":is_operation_only_stop_$i", $d->is_operation_only_stop, PDO::PARAM_BOOL);
		$query->bindValue(":is_pass_$i", $d->is_pass, PDO::PARAM_BOOL);
		$query->bindValue(":has_bracket_$i", $d->has_bracket, PDO::PARAM_BOOL);
		$query->bindValue(":is_last_stop_$i", $d->is_last_stop, PDO::PARAM_BOOL);

		$query->bindValue(":arrive_time_hh_$i", $d->arrive_time_hh, PDO::PARAM_INT);
		$query->bindValue(":arrive_time_mm_$i", $d->arrive_time_mm, PDO::PARAM_INT);
		$query->bindValue(":arrive_time_ss_$i", $d->arrive_time_ss, PDO::PARAM_INT);

		$query->bindValue(":departure_time_hh_$i", $d->departure_time_hh, PDO::PARAM_INT);
		$query->bindValue(":departure_time_mm_$i", $d->departure_time_mm, PDO::PARAM_INT);
		$query->bindValue(":departure_time_ss_$i", $d->departure_time_ss, PDO::PARAM_INT);

		$query->bindValue(":run_in_limit_$i", $d->run_in_limit, PDO::PARAM_INT);
		$query->bindValue(":run_out_limit_$i", $d->run_out_limit, PDO::PARAM_INT);

		$query->bindValue(":remarks_$i", $d->remarks, PDO::PARAM_STR);

		$query->bindValue(":arrive_str_$i", $d->arrive_str, PDO::PARAM_STR);
		$query->bindValue(":departure_str_$i", $d->departure_str, PDO::PARAM_STR);

		$query->bindValue(":marker_text_$i", $d->marker_text, PDO::PARAM_STR);

		$query->bindValue(":work_type_$i", $d->work_type, PDO::PARAM_STR);
	}

	/**
	 * @return RetValueOrError<TimetableRow>
	 */
	public function selectOne(
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne timetable_rowsId: {timetableRowsId}',
			[
				'timetableRowsId' => $timetableRowsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					timetable_rows
				WHERE
					timetable_rows.timetable_rows_id = :timetable_rows_id
				AND
					timetable_rows.deleted_at IS NULL
				SQL
			);

			$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes());

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne({timetableRowsId}) - rowCount is 0',
					[
						'timetableRowsId' => $timetableRowsId,
					],
				);
				return Utils::errTimetableRowNotFound();
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
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId timetableRowsId: {timetableRowsId}',
			[
				'timetableRowsId' => $timetableRowsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					works.work_groups_id AS work_groups_id
				FROM
					timetable_rows
				JOIN
					trains
				USING
					(trains_id)
				JOIN
					works
				USING
					(works_id)
				WHERE
					timetable_rows.timetable_rows_id = :timetable_rows_id
				AND
					timetable_rows.deleted_at IS NULL
				AND
					trains.deleted_at IS NULL
				AND
					works.deleted_at IS NULL
				SQL
			);

			$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return Utils::errTimetableRowNotFound();
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
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function selectPage(
		UuidInterface $trainsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList trainsId: {trainsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'trainsId' => $trainsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$hasTopId = !is_null($topId);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					timetable_rows
				WHERE
					timetable_rows.trains_id = :trains_id
				AND
					timetable_rows.deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : ' AND timetable_row.timetable_rows_id <= :top_id ')
				.
				<<<SQL
				ORDER BY
					timetable_rows_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
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
				return Utils::errTimetableRowNotFound();
			}

			$timetableRow = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($timetableRow);
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
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $timetableRowsIdList */
		array $timetableRowsIdList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList timetableRowsIdList: {timetableRowsIdList}",
			[
				"timetableRowsIdList" => $timetableRowsIdList,
			],
		);

		$timetableRowsIdListCount = count($timetableRowsIdList);
		$placeholders = implode(',', array_map(
			fn($i) => ":timetable_rows_id_$i",
			range(0, $timetableRowsIdListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					timetable_row
				WHERE
					timetable_rows_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $timetableRowsIdListCount; $i++) {
				$query->bindValue(":timetable_rows_id_$i", $timetableRowsIdList[$i]->getBytes(), PDO::PARAM_STR);
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
				return Utils::errTimetableRowNotFound();
			}

			$timetableRow = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($timetableRow);
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
		UuidInterface $trainsId,
		string $ownerUserId,
		/** @param array<UuidInterface> $timetableRowsIdList */
		array $timetableRowsIdList,
		/** @param array<TimetableRow> $timetableRow */
		array $timetableRows,
	): RetValueOrError {
		$this->logger->debug(
			'insertList trainsId: {trainsId}, timetableRow: {timetableRow}',
			[
				'trainsId' => $trainsId,
				'timetableRow' => $timetableRows,
			],
		);

		try
		{
			$timetableRowCount = count($timetableRows);
			$query = $this->db->prepare(
				'INSERT INTO timetable_row'
				.
				self::SQL_INSERT_COLUMNS
				.
				' VALUES '
				.
				implode(',', array_map(
					fn($i) => self::_genInsertValuesQuerySegment($i),
					range(0, $timetableRowCount - 1),
				))
			);
			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $timetableRowCount; $i++) {
				self::_setInsertValues($query, $i, $timetableRowsIdList[$i], $timetableRows[$i]);
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
		UuidInterface $timetableRowsId,
		array $timetableRowsProps,
	): RetValueOrError {
		$this->logger->debug(
			'updateList timetableRowsId: {timetableRowsId}, timetableRow: {timetableRow}',
			[
				'timetableRowsId' => $timetableRowsId,
				'timetableRow' => $timetableRowsProps,
			],
		);

		if (count($timetableRowsProps) === 0) {
			return RetValueOrError::withValue(null);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE timetable_row SET "
				.
				implode(',', array_map(fn($key) => "{$key} = :{$key}", array_keys($timetableRowsProps)))
				.
				" WHERE timetable_rows_id = :timetable_rows_id AND deleted_at IS NULL"
			);
			$query->bindValue(':timetable_rows_id', $timetableRowsId->getBytes(), PDO::PARAM_STR);
			foreach ($timetableRowsProps as $key => $value) {
				$paramType = PDO::PARAM_STR;
				if (is_int($value)) {
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
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne timetableRowsId: {timetableRowsId}",
			[
				"timetableRowsId" => $timetableRowsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					timetable_row
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					timetable_rows_id = :timetable_rows_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":timetable_rows_id", $timetableRowsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteOne - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"TimetableRow not found ({timetableRowsId})",
					[
						"timetableRowsId" => $timetableRowsId,
					],
				);
				return Utils::errTimetableRowNotFound();
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

	public function deleteByTrainsId(
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByTrainsId trainsId: {trainsId}",
			[
				"trainsId" => $trainsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					timetable_row
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					trains_id = :trains_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":trains_id", $trainsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$this->logger->debug(
				"deleteByTrainsId - rowCount: {rowCount}",
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
					timetable_row
				JOIN
					trains
				USING
					(trains_id)
				JOIN
					works
				USING
					(works_id)
				SET
					timetable_row.deleted_at = CURRENT_TIMESTAMP()
				WHERE
					works.work_groups_id = :work_groups_id
				AND
					timetable_row.deleted_at IS NULL
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
