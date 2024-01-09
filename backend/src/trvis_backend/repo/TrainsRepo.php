<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class TrainsRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		trains.trains_id AS trains_id,
		trains.works_id AS works_id,
		trains.description AS description,
		trains.created_at AS created_at,
		trains.train_number AS train_number,
		trains.max_speed AS max_speed,
		trains.speed_type AS speed_type,
		trains.nominal_tractive_capacity AS nominal_tractive_capacity,
		trains.car_count AS car_count,
		trains.destination AS destination,
		trains.begin_remarks AS begin_remarks,
		trains.after_remarks AS after_remarks,
		trains.remarks AS remarks,
		trains.before_departure AS before_departure,
		trains.after_arrive AS after_arrive,
		trains.train_info AS train_info,
		trains.direction AS direction,
		trains.day_count AS day_count,
		trains.is_ride_on_moving AS is_ride_on_moving

	SQL;

	private static function _fetchResultToObj(
		mixed $d,
	): Train {
		$result = new Train();
		$result->setData([
			'trains_id' => Uuid::fromBytes($d['trains_id']),
			'works_id' => Uuid::fromBytes($d['works_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'train_number' => $d['train_number'],
			'max_speed' => $d['max_speed'],
			'speed_type' => $d['speed_type'],
			'nominal_tractive_capacity' => $d['nominal_tractive_capacity'],
			'car_count' => $d['car_count'],
			'destination' => $d['destination'],
			'begin_remarks' => $d['begin_remarks'],
			'after_remarks' => $d['after_remarks'],
			'remarks' => $d['remarks'],
			'before_departure' => $d['before_departure'],
			'after_arrive' => $d['after_arrive'],
			'train_info' => $d['train_info'],
			'direction' => $d['direction'],
			'day_count' => $d['day_count'],
			'is_ride_on_moving' => $d['is_ride_on_moving'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		trains_id,
		works_id,
		description,
		owner,
		train_number,
		max_speed,
		speed_type,
		nominal_tractive_capacity,
		car_count,
		destination,
		begin_remarks,
		after_remarks,
		remarks,
		before_departure,
		after_arrive,
		train_info,
		direction,
		day_count,
		is_ride_on_moving
	)
	SQL;
	static function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:trains_id_{$i},
				:works_id,
				:description_{$i},
				:owner,
				:train_number_{$i},
				:max_speed_{$i},
				:speed_type_{$i},
				:nominal_tractive_capacity_{$i},
				:car_count_{$i},
				:destination_{$i},
				:begin_remarks_{$i},
				:after_remarks_{$i},
				:remarks_{$i},
				:before_departure_{$i},
				:after_arrive_{$i},
				:train_info_{$i},
				:direction_{$i},
				:day_count_{$i},
				:is_ride_on_moving_{$i}
			)
		SQL;
	}
	static function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $trainsId,
		Train $d,
	) {
		$query->bindValue(":trains_id_$i", $trainsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":train_number_$i", $d->train_number, PDO::PARAM_STR);
		$query->bindValue(":max_speed_$i", $d->max_speed, PDO::PARAM_STR);
		$query->bindValue(":speed_type_$i", $d->speed_type, PDO::PARAM_STR);
		$query->bindValue(":nominal_tractive_capacity_$i", $d->nominal_tractive_capacity, PDO::PARAM_STR);
		$query->bindValue(":car_count_$i", $d->car_count, PDO::PARAM_INT);
		$query->bindValue(":destination_$i", $d->destination, PDO::PARAM_STR);
		$query->bindValue(":begin_remarks_$i", $d->begin_remarks, PDO::PARAM_STR);
		$query->bindValue(":after_remarks_$i", $d->after_remarks, PDO::PARAM_STR);
		$query->bindValue(":remarks_$i", $d->remarks, PDO::PARAM_STR);
		$query->bindValue(":before_departure_$i", $d->before_departure, PDO::PARAM_STR);
		$query->bindValue(":after_arrive_$i", $d->after_arrive, PDO::PARAM_STR);
		$query->bindValue(":train_info_$i", $d->train_info, PDO::PARAM_STR);
		$query->bindValue(":direction_$i", $d->direction, PDO::PARAM_INT);
		$query->bindValue(":day_count_$i", $d->day_count, PDO::PARAM_INT);
		$query->bindValue(":is_ride_on_moving_$i", $d->is_ride_on_moving, PDO::PARAM_BOOL);
	}

	/**
	 * @return RetValueOrError<Train>
	 */
	public function selectOne(
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne trainsId: {trainsId}',
			[
				'trainsId' => $trainsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					trains
				WHERE
					trains.trains_id = :trains_id
				AND
					trains.deleted_at IS NULL
				SQL
			);

			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne({trainsId}) - rowCount is 0',
					[
						'trainsId' => $trainsId,
					],
				);
				return Utils::errTrainNotFound();
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
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId trainsId: {trainsId}',
			[
				'trainsId' => $trainsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					works.work_groups_id
				FROM
					works
				JOIN
					trains
				USING
					(works_id)
				WHERE
					trains.trains_id = :trains_id
				AND
					works.deleted_at IS NULL
				AND
					trains.deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return Utils::errTrainNotFound();
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
	 * @return RetValueOrError<array<Train>>
	 */
	public function selectPage(
		UuidInterface $worksId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList worksId: {worksId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'worksId' => $worksId,
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
					trains
				WHERE
					trains.works_id = :works_id
				AND
					trains.deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : ' AND trains.trains_id <= :top_id ')
				.
				<<<SQL
				ORDER BY
					trains_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
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
				return Utils::errTrainNotFound();
			}

			$trains = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($trains);
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
	 * @return RetValueOrError<array<Train>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $trainsIdList */
		array $trainsIdList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList trainsIdList: {trainsIdList}",
			[
				"trainsIdList" => $trainsIdList,
			],
		);

		$trainsIdListCount = count($trainsIdList);
		$placeholders = implode(',', array_map(
			fn($i) => ":trains_id_$i",
			range(0, $trainsIdListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					trains
				WHERE
					trains_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $trainsIdListCount; $i++) {
				$query->bindValue(":trains_id_$i", $trainsIdList[$i]->getBytes(), PDO::PARAM_STR);
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
				return Utils::errTrainNotFound();
			}

			$trains = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($trains);
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
		UuidInterface $worksId,
		string $ownerUserId,
		/** @param array<UuidInterface> $trainsIdList */
		array $trainsIdList,
		/** @param array<Train> $trains */
		array $trains,
	): RetValueOrError {
		$this->logger->debug(
			'insertList worksId: {worksId}, trains: {trains}',
			[
				'worksId' => $worksId,
				'trains' => $trains,
			],
		);

		try
		{
			$trainsCount = count($trains);
			$query = $this->db->prepare(
				'INSERT INTO trains'
				.
				self::SQL_INSERT_COLUMNS
				.
				' VALUES '
				.
				implode(',', array_map(
					fn($i) => self::_genInsertValuesQuerySegment($i),
					range(0, $trainsCount - 1),
				))
			);
			$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $trainsCount; $i++) {
				self::_setInsertValues($query, $i, $trainsIdList[$i], $trains[$i]);
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
		UuidInterface $trainsId,
		array $trainsProps,
	): RetValueOrError {
		$this->logger->debug(
			'updateList trainsId: {trainsId}, trains: {trains}',
			[
				'trainsId' => $trainsId,
				'trains' => $trainsProps,
			],
		);

		if (count($trainsProps) === 0) {
			return RetValueOrError::withValue(null);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE trains SET "
				.
				implode(',', array_map(fn($key) => "{$key} = :{$key}", array_keys($trainsProps)))
				.
				" WHERE trains_id = :trains_id AND deleted_at IS NULL"
			);
			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
			foreach ($trainsProps as $key => $value) {
				$paramType = PDO::PARAM_STR;
				if ($key === 'run_in_limit' || $key === 'run_out_limit') {
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
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne trainsId: {trainsId}",
			[
				"trainsId" => $trainsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					trains
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
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteOne - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"Train not found ({trainsId})",
					[
						"trainsId" => $trainsId,
					],
				);
				return Utils::errTrainNotFound();
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

	public function deleteByWorksId(
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByWorksId worksId: {worksId}",
			[
				"worksId" => $worksId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					trains
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
			$this->logger->debug(
				"deleteByWorksId - rowCount: {rowCount}",
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
					trains
				JOIN
					works
				USING
					(works_id)
				SET
					trains.deleted_at = CURRENT_TIMESTAMP()
				WHERE
					works.work_groups_id = :work_groups_id
				AND
					trains.deleted_at IS NULL
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
