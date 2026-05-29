<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\DataWithId;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\TRViSJsonTrain;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * trains — Work-rooted (works_id FK).
 *
 * selectPrivilegeType resolves works_id from the trains table (via INNER JOIN
 * with works), then delegates to WorkGroupsPrivilegesRepo for project-root
 * privilege resolution. This reproduces the legacy MyRepoBase::selectPrivilegeType
 * chain (parentTableNameList=['works','work_groups'], sliced to ['works']) without
 * the base-class machinery.
 *
 * TrainsRepo implements IMyRepoSelectPrivilegeType so that W3 (TimetableRows)
 * can use it as a privilege-delegate parent, mirroring the legacy
 * TimetableRowsService which instantiated `parentRepo: new TrainsRepo(...)`.
 * selectWorkGroupsId is also exposed for the same reason.
 *
 * Method names must NOT have a leading underscore (PSR2.Methods — not in the
 * 4-exclude carve-out). Canonical precedent: WorksRepo.
 */
final class TrainsRepo implements IMyRepoSelectPrivilegeType
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
		private readonly int $dumpMaxRowsPerTable = Constants::DUMP_MAX_ROWS_PER_TABLE,
	) {
	}

	private static function fetchResultToTrain(mixed $data): Train
	{
		$train = new Train();
		$train->setData([
			'trains_id' => Uuid::fromBytes($data['trains_id']),
			'works_id' => Uuid::fromBytes($data['works_id']),
			'created_at' => Utils::dbDateStrToDateTime($data['created_at']),
			'description' => $data['description'],
			'train_number' => $data['train_number'],
			'max_speed' => $data['max_speed'],
			'speed_type' => $data['speed_type'],
			'nominal_tractive_capacity' => $data['nominal_tractive_capacity'],
			'car_count' => $data['car_count'],
			'destination' => $data['destination'],
			'begin_remarks' => $data['begin_remarks'],
			'after_remarks' => $data['after_remarks'],
			'remarks' => $data['remarks'],
			'before_departure' => $data['before_departure'],
			'after_arrive' => $data['after_arrive'],
			'train_info' => $data['train_info'],
			'direction' => $data['direction'],
			'day_count' => $data['day_count'],
			'is_ride_on_moving' => $data['is_ride_on_moving'],
		]);
		return $train;
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
			"selectPrivilegeType(userId:{userId}, trainsId:{trainsId})",
			[
				'userId' => $userId,
				'trainsId' => $id,
			],
		);

		try {
			$query = $this->db->prepare(
				'SELECT works.work_groups_id FROM trains'
				. ' INNER JOIN works USING (works_id)'
				. ' WHERE trains_id = :trains_id'
				. ' AND trains.deleted_at IS NULL'
				. ' AND works.deleted_at IS NULL'
				. ($selectForUpdate ? ' FOR UPDATE' : '')
				. ';'
			);
			$query->bindValue(':trains_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errTrainNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$workGroupsId = Uuid::fromBytes($row['work_groups_id']);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				'failed to resolve work_groups_id for train ({errorCode})',
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
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectWorkGroupsId(
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			"selectWorkGroupsId(trainsId:{trainsId})",
			['trainsId' => $id],
		);

		try {
			$query = $this->db->prepare(
				'SELECT works.work_groups_id FROM trains'
				. ' INNER JOIN works USING (works_id)'
				. ' WHERE trains_id = :trains_id'
				. ' AND trains.deleted_at IS NULL'
				. ' AND works.deleted_at IS NULL;'
			);
			$query->bindValue(':trains_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->warning('selectWorkGroupsId - train not found');
				return Utils::errTrainNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			return RetValueOrError::withValue(Uuid::fromBytes($row['work_groups_id']));
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<Train>
	 */
	public function selectTrainOne(
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			"selectTrainOne(trainsId:{trainsId})",
			['trainsId' => $trainsId],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					trains.trains_id,
					trains.works_id,
					trains.created_at,
					trains.description,
					trains.train_number,
					trains.max_speed,
					trains.speed_type,
					trains.nominal_tractive_capacity,
					trains.car_count,
					trains.destination,
					trains.begin_remarks,
					trains.after_remarks,
					trains.remarks,
					trains.before_departure,
					trains.after_arrive,
					trains.train_info,
					trains.direction,
					trains.day_count,
					trains.is_ride_on_moving
				FROM
					trains
				WHERE
					trains.trains_id = :trains_id
				AND
					trains.deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);

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
					"Train not found ({trainsId})",
					['trainsId' => $trainsId],
				);
				return Utils::errTrainNotFound();
			}

			return RetValueOrError::withValue(self::fetchResultToTrain($data));
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * Bulk faithful mirror of selectTrainOne: identical projection / FROM /
	 * WHERE filters and try/catch wrapping (no $userId, no privilege-JOIN —
	 * exactly like selectTrainOne), only the single-id predicate becomes
	 * `trains.trains_id IN (...)`. Element order is NOT guaranteed; the
	 * caller reorders.
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<Train>>
	 */
	public function selectListByIds(
		array $idList,
	): RetValueOrError {
		$this->logger->debug(
			"selectListByIds count: {count}",
			['count' => count($idList)],
		);

		if (empty($idList)) {
			return RetValueOrError::withValue([]);
		}

		try {
			$placeholders = implode(',', array_map(fn ($i) => ":id_$i", array_keys($idList)));
			$query = $this->db->prepare(<<<SQL
				SELECT
					trains.trains_id,
					trains.works_id,
					trains.created_at,
					trains.description,
					trains.train_number,
					trains.max_speed,
					trains.speed_type,
					trains.nominal_tractive_capacity,
					trains.car_count,
					trains.destination,
					trains.begin_remarks,
					trains.after_remarks,
					trains.remarks,
					trains.before_departure,
					trains.after_arrive,
					trains.train_info,
					trains.direction,
					trains.day_count,
					trains.is_ride_on_moving
				FROM
					trains
				WHERE
					trains.trains_id IN ($placeholders)
				AND
					trains.deleted_at IS NULL
				;
				SQL
			);
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

			$trains = array_map(
				fn ($data) => self::fetchResultToTrain($data),
				$query->fetchAll(PDO::FETCH_ASSOC),
			);
			return RetValueOrError::withValue($trains);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $e->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	private static function getSelectPageSql(
		bool $hasTopId,
		bool $countOnly,
	): string {
		$WHERE_TOP_ID = $hasTopId ? ' AND trains.trains_id <= :top_id ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			trains.trains_id,
			trains.works_id,
			trains.created_at,
			trains.description,
			trains.train_number,
			trains.max_speed,
			trains.speed_type,
			trains.nominal_tractive_capacity,
			trains.car_count,
			trains.destination,
			trains.begin_remarks,
			trains.after_remarks,
			trains.remarks,
			trains.before_departure,
			trains.after_arrive,
			trains.train_info,
			trains.direction,
			trains.day_count,
			trains.is_ride_on_moving

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				trains_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				trains
			WHERE
				trains.works_id = :works_id
			AND
				$WHERE_TOP_ID
				trains.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Train>>
	 */
	public function selectTrainPage(
		UuidInterface $worksId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectTrainPage(worksId:{worksId}, page:{page}, perPage:{perPage})",
			[
				'worksId' => $worksId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
			],
		);

		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageSql($hasTopId, false));
		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
		}
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

		$trains = array_map(
			fn ($data) => self::fetchResultToTrain($data),
			$query->fetchAll(PDO::FETCH_ASSOC),
		);
		return RetValueOrError::withValue($trains);
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectTrainPageTotalCount(
		UuidInterface $worksId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		$query = $this->db->prepare(self::getSelectPageSql($hasTopId, true));
		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		if ($hasTopId) {
			$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
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

		$row = $query->fetch(PDO::FETCH_ASSOC);
		$totalCount = $row ? (int)$row['count'] : 0;
		return RetValueOrError::withValue($totalCount);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function insertTrain(
		UuidInterface $trainsId,
		UuidInterface $worksId,
		string $owner,
		string $description,
		string $trainNumber,
		?string $maxSpeed,
		?string $speedType,
		?string $nominalTractiveCapacity,
		?int $carCount,
		?string $destination,
		?string $beginRemarks,
		?string $afterRemarks,
		?string $remarks,
		?string $beforeDeparture,
		?string $afterArrive,
		?string $trainInfo,
		int $direction,
		int $dayCount,
		bool $isRideOnMoving,
	): RetValueOrError {
		$query = $this->db->prepare(<<<SQL
			INSERT INTO trains (
				trains_id,
				works_id,
				owner,
				description,
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
			) VALUES (
				:trains_id,
				:works_id,
				:owner,
				:description,
				:train_number,
				:max_speed,
				:speed_type,
				:nominal_tractive_capacity,
				:car_count,
				:destination,
				:begin_remarks,
				:after_remarks,
				:remarks,
				:before_departure,
				:after_arrive,
				:train_info,
				:direction,
				:day_count,
				:is_ride_on_moving
			);
			SQL
		);

		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':works_id', $worksId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':train_number', $trainNumber, PDO::PARAM_STR);
		$query->bindValue(':max_speed', $maxSpeed, PDO::PARAM_STR);
		$query->bindValue(':speed_type', $speedType, PDO::PARAM_STR);
		$query->bindValue(':nominal_tractive_capacity', $nominalTractiveCapacity, PDO::PARAM_STR);
		$query->bindValue(':car_count', $carCount, PDO::PARAM_INT);
		$query->bindValue(':destination', $destination, PDO::PARAM_STR);
		$query->bindValue(':begin_remarks', $beginRemarks, PDO::PARAM_STR);
		$query->bindValue(':after_remarks', $afterRemarks, PDO::PARAM_STR);
		$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
		$query->bindValue(':before_departure', $beforeDeparture, PDO::PARAM_STR);
		$query->bindValue(':after_arrive', $afterArrive, PDO::PARAM_STR);
		$query->bindValue(':train_info', $trainInfo, PDO::PARAM_STR);
		$query->bindValue(':direction', $direction, PDO::PARAM_INT);
		$query->bindValue(':day_count', $dayCount, PDO::PARAM_INT);
		$query->bindValue(':is_ride_on_moving', $isRideOnMoving, PDO::PARAM_BOOL);

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
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Train");
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function updateTrain(
		UuidInterface $trainsId,
		?string $description,
		bool $hasDescription,
		?string $trainNumber,
		bool $hasTrainNumber,
		?string $maxSpeed,
		bool $hasMaxSpeed,
		?string $speedType,
		bool $hasSpeedType,
		?string $nominalTractiveCapacity,
		bool $hasNominalTractiveCapacity,
		?int $carCount,
		bool $hasCarCount,
		?string $destination,
		bool $hasDestination,
		?string $beginRemarks,
		bool $hasBeginRemarks,
		?string $afterRemarks,
		bool $hasAfterRemarks,
		?string $remarks,
		bool $hasRemarks,
		?string $beforeDeparture,
		bool $hasBeforeDeparture,
		?string $afterArrive,
		bool $hasAfterArrive,
		?string $trainInfo,
		bool $hasTrainInfo,
		?int $direction,
		bool $hasDirection,
		?int $dayCount,
		bool $hasDayCount,
		?bool $isRideOnMoving,
		bool $hasIsRideOnMoving,
	): RetValueOrError {
		$this->logger->info(
			"updateTrain({trainsId})",
			['trainsId' => $trainsId],
		);

		if (
			!$hasDescription && !$hasTrainNumber && !$hasMaxSpeed
			&& !$hasSpeedType && !$hasNominalTractiveCapacity && !$hasCarCount
			&& !$hasDestination && !$hasBeginRemarks && !$hasAfterRemarks
			&& !$hasRemarks && !$hasBeforeDeparture && !$hasAfterArrive
			&& !$hasTrainInfo && !$hasDirection && !$hasDayCount
			&& !$hasIsRideOnMoving
		) {
			return RetValueOrError::withValue(null);
		}

		$setParts = [];
		if ($hasDescription) {
			$setParts[] = 'description = :description';
		}
		if ($hasTrainNumber) {
			$setParts[] = 'train_number = :train_number';
		}
		if ($hasMaxSpeed) {
			$setParts[] = 'max_speed = :max_speed';
		}
		if ($hasSpeedType) {
			$setParts[] = 'speed_type = :speed_type';
		}
		if ($hasNominalTractiveCapacity) {
			$setParts[] = 'nominal_tractive_capacity = :nominal_tractive_capacity';
		}
		if ($hasCarCount) {
			$setParts[] = 'car_count = :car_count';
		}
		if ($hasDestination) {
			$setParts[] = 'destination = :destination';
		}
		if ($hasBeginRemarks) {
			$setParts[] = 'begin_remarks = :begin_remarks';
		}
		if ($hasAfterRemarks) {
			$setParts[] = 'after_remarks = :after_remarks';
		}
		if ($hasRemarks) {
			$setParts[] = 'remarks = :remarks';
		}
		if ($hasBeforeDeparture) {
			$setParts[] = 'before_departure = :before_departure';
		}
		if ($hasAfterArrive) {
			$setParts[] = 'after_arrive = :after_arrive';
		}
		if ($hasTrainInfo) {
			$setParts[] = 'train_info = :train_info';
		}
		if ($hasDirection) {
			$setParts[] = 'direction = :direction';
		}
		if ($hasDayCount) {
			$setParts[] = 'day_count = :day_count';
		}
		if ($hasIsRideOnMoving) {
			$setParts[] = 'is_ride_on_moving = :is_ride_on_moving';
		}

		$setClause = implode(', ', $setParts);

		$query = $this->db->prepare(<<<SQL
			UPDATE trains SET
				$setClause
			WHERE
				trains_id = :trains_id
			AND
				deleted_at IS NULL
			;
			SQL
		);
		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);
		if ($hasDescription) {
			$query->bindValue(':description', $description, PDO::PARAM_STR);
		}
		if ($hasTrainNumber) {
			$query->bindValue(':train_number', $trainNumber, PDO::PARAM_STR);
		}
		if ($hasMaxSpeed) {
			$query->bindValue(':max_speed', $maxSpeed, PDO::PARAM_STR);
		}
		if ($hasSpeedType) {
			$query->bindValue(':speed_type', $speedType, PDO::PARAM_STR);
		}
		if ($hasNominalTractiveCapacity) {
			$query->bindValue(':nominal_tractive_capacity', $nominalTractiveCapacity, PDO::PARAM_STR);
		}
		if ($hasCarCount) {
			$query->bindValue(':car_count', $carCount, PDO::PARAM_INT);
		}
		if ($hasDestination) {
			$query->bindValue(':destination', $destination, PDO::PARAM_STR);
		}
		if ($hasBeginRemarks) {
			$query->bindValue(':begin_remarks', $beginRemarks, PDO::PARAM_STR);
		}
		if ($hasAfterRemarks) {
			$query->bindValue(':after_remarks', $afterRemarks, PDO::PARAM_STR);
		}
		if ($hasRemarks) {
			$query->bindValue(':remarks', $remarks, PDO::PARAM_STR);
		}
		if ($hasBeforeDeparture) {
			$query->bindValue(':before_departure', $beforeDeparture, PDO::PARAM_STR);
		}
		if ($hasAfterArrive) {
			$query->bindValue(':after_arrive', $afterArrive, PDO::PARAM_STR);
		}
		if ($hasTrainInfo) {
			$query->bindValue(':train_info', $trainInfo, PDO::PARAM_STR);
		}
		if ($hasDirection) {
			$query->bindValue(':direction', $direction, PDO::PARAM_INT);
		}
		if ($hasDayCount) {
			$query->bindValue(':day_count', $dayCount, PDO::PARAM_INT);
		}
		if ($hasIsRideOnMoving) {
			$query->bindValue(':is_ride_on_moving', $isRideOnMoving, PDO::PARAM_BOOL);
		}

		try {
			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"Train not found ({trainsId})",
						['trainsId' => $trainsId],
					);
					return Utils::errTrainNotFound();
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
	public function deleteTrain(
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->info(
			"deleteTrain({trainsId})",
			['trainsId' => $trainsId],
		);
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
		$query->bindValue(':trains_id', $trainsId->getBytes(), PDO::PARAM_STR);

		try {
			$query->execute();

			if ($query->rowCount() === 0) {
				$this->logger->info(
					"Train not found ({trainsId})",
					['trainsId' => $trainsId],
				);
				return Utils::errTrainNotFound();
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
					works_id IN ($parentIdListPlaceholder)
				AND
					trains.deleted_at IS NULL
				LIMIT $dumpLimit
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
			if ($rowCount > $this->dumpMaxRowsPerTable) {
				$this->logger->error(
					'TrainsRepo::dump() cap exceeded: {rowCount} rows > {cap}.',
					[
						'rowCount' => $rowCount,
						'cap' => $this->dumpMaxRowsPerTable,
					],
				);
				return RetValueOrError::withError(
					Constants::HTTP_PAYLOAD_TOO_LARGE,
					'Dump aborted: trains row count exceeds the per-table limit ('
						. $this->dumpMaxRowsPerTable . '). Reduce the work group size.',
				);
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
		} catch (\PDOException $e) {
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
	 * @return DataWithId<TRViSJsonTrain>
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
