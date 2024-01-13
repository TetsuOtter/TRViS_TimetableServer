<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\DataWithId;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\TRViSJsonTrain;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<Train>
 */
final class TrainsRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'trains',
			parentTableNameList: ['works', 'work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
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

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
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
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:trains_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
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
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":trains_id_$i", $id->getBytes(), PDO::PARAM_STR);
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
	 * @param array<UuidInterface> $parentIdList
	 * @param array<string, array<DataWithId<TRViSJsonTrain>>> $dst
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
		$parentIdListPlaceholder = implode(', ', array_fill(0, $parentIdCount, '?'));
		try
		{
			// station_tracksとのJOINは、本当はstations_idも条件として加えるべきである。
			// しかし、実装上の都合で同じWorkGroupの他の駅に属するstation_tracksも登録できてしまう。
			// そのため、stations_idでの絞り込みは行わない。
			$query = $this->db->prepare(<<<SQL
				SELECT
					HEX(trains.works_id) AS parent_id,
					trains.trains_id AS trains_id,
					trains.train_number AS TrainNumber,
					trains.max_speed AS MaxSpeed,
					trains.speed_type AS SpeedType,
					trains.nominal_tractive_capacity AS NominalTractiveCapacity,
					trains.car_count AS CarCount,
					trains.destination AS Destination,
					trains.begin_remarks AS BeginRemarks,
					trains.after_remarks AS AfterRemarks,
					trains.remarks AS Remarks,
					trains.before_departure AS BeforeDeparture,
					trains.after_arrive AS AfterArrive,
					trains.train_info AS TrainInfo,
					trains.direction AS Direction,
					trains.day_count AS DayCount,
					trains.is_ride_on_moving AS IsRideOnMoving
				FROM
					trains
				WHERE
					{$this->parentTableName}_id IN ($parentIdListPlaceholder)
				SQL
			);
			for ($i = 0; $i < $parentIdCount; ++$i) {
				$query->bindValue($i + 1, $parentIdList[$i]->getBytes(), PDO::PARAM_STR);
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'selected train {rowCount} rows.',
				[
					'rowCount' => $rowCount,
				],
			);
			if ($rowCount === 0) {
				return RetValueOrError::withValue([]);
			}

			$rowCountPerParent = [];
			$trainsIdList = [];
			$trainsIdListIndex = 0;
			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$parentId = $row['parent_id'];
				if (!array_key_exists($parentId, $rowCountPerParent)) {
					$rowCountPerParent[$parentId] = 0;
				}
				$d = self::fetchResultRowToTrvisJsonData($row);
				$dst[$parentId][$rowCountPerParent[$parentId]++] = $d;
				$trainsIdList[$trainsIdListIndex++] = $d->id;
			}

			return RetValueOrError::withValue($trainsIdList);
		}
		catch (\PDOException $e)
		{
			$this->logger->error(
				'Failed to dump train rows. {exception}',
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
	 * @return array<string, DataWithId<TRViSJsonTrain>>
	 */
	private static function fetchResultRowToTrvisJsonData(
		array $kvpList,
	): DataWithId {
		$d = new TRViSJsonTrain();
		$d->setData([
			'TrainNumber' => $kvpList['TrainNumber'],
			'MaxSpeed' => $kvpList['MaxSpeed'],
			'SpeedType' => $kvpList['SpeedType'],
			'NominalTractiveCapacity' => $kvpList['NominalTractiveCapacity'],
			'CarCount' => $kvpList['CarCount'],
			'Destination' => $kvpList['Destination'],
			'BeginRemarks' => $kvpList['BeginRemarks'],
			'AfterRemarks' => $kvpList['AfterRemarks'],
			'Remarks' => $kvpList['Remarks'],
			'BeforeDeparture' => $kvpList['BeforeDeparture'],
			'AfterArrive' => $kvpList['AfterArrive'],
			'TrainInfo' => $kvpList['TrainInfo'],
			'Direction' => $kvpList['Direction'],
			'DayCount' => $kvpList['DayCount'],
			'IsRideOnMoving' => $kvpList['IsRideOnMoving'],

			'TimetableRows' => [],
		]);
		return new DataWithId(
			id: Uuid::fromBytes($kvpList['trains_id']),
			data: $d,
		);
	}


}
