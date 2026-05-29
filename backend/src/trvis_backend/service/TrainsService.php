<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Train service. Work-rooted (works_id FK) — privilege checks for
 * create/getPage use WorksRepo->selectPrivilegeType (parent); for
 * getOne/update/delete use TrainsRepo->selectPrivilegeType (target).
 * This mirrors the legacy MyServiceBase logic faithfully without the
 * base-class machinery.
 */
final class TrainsService
{
	private readonly TrainsRepo $trainsRepo;
	private readonly WorksRepo $worksRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->trainsRepo = new TrainsRepo($db, $logger);
		$this->worksRepo = new WorksRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<Train>
	 */
	public function getOne(
		string $userId,
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, trainsId:{trainsId})",
			[
				'userId' => $userId,
				'trainsId' => $trainsId,
			],
		);

		$privResult = $this->trainsRepo->selectPrivilegeType(
			id: $trainsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		return $this->trainsRepo->selectTrainOne($trainsId);
	}

	/**
	 * @return RetValueOrError<array<Train>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $worksId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, worksId:{worksId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'worksId' => $worksId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$privResult = $this->worksRepo->selectPrivilegeType(
			id: $worksId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->trainsRepo->selectTrainPageTotalCount(
			worksId: $worksId,
			topId: $topId,
		);
		$selectResult = $this->trainsRepo->selectTrainPage(
			worksId: $worksId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);

		return RetValueOrError::withTotalCount(
			value: $selectResult,
			totalCount: $totalCountResult,
		);
	}

	/**
	 * @return RetValueOrError<array<Train>>
	 */
	public function create(
		UuidInterface $worksId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(worksId:{worksId}, userId:{userId})",
			[
				'worksId' => $worksId,
				'userId' => $userId,
			],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->worksRepo->selectPrivilegeType(
			id: $worksId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $d) {
				$trainsId = Uuid::uuid7();
				$insertResult = $this->trainsRepo->insertTrain(
					trainsId: $trainsId,
					worksId: $worksId,
					owner: $userId,
					description: $d->description,
					trainNumber: $d->train_number,
					maxSpeed: $d->max_speed,
					speedType: $d->speed_type,
					nominalTractiveCapacity: $d->nominal_tractive_capacity,
					carCount: $d->car_count,
					destination: $d->destination,
					beginRemarks: $d->begin_remarks,
					afterRemarks: $d->after_remarks,
					remarks: $d->remarks,
					beforeDeparture: $d->before_departure,
					afterArrive: $d->after_arrive,
					trainInfo: $d->train_info,
					direction: $d->direction,
					dayCount: $d->day_count,
					isRideOnMoving: $d->is_ride_on_moving ?? false,
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $trainsId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create train: {exception}",
				['exception' => $e],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'unknown error',
				$e->getCode(),
			);
		}

		$selectListResult = $this->trainsRepo->selectListByIds($insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->trains_id] = $m;
		}
		if (count($byId) !== count($insertedIds)) {
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'inconsistent read-back after bulk insert',
			);
		}
		$results = [];
		foreach ($insertedIds as $id) {
			$results[] = $byId[(string)$id];
		}

		return RetValueOrError::withValue($results, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<Train>
	 */
	public function update(
		string $userId,
		UuidInterface $trainsId,
		object $body,
		array $requestedKeys,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, trainsId:{trainsId})",
			[
				'userId' => $userId,
				'trainsId' => $trainsId,
			],
		);

		$privResult = $this->trainsRepo->selectPrivilegeType(
			id: $trainsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errTrainNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$hasKey = fn (string $k): bool => in_array($k, $requestedKeys) || array_key_exists($k, $requestedKeys);

		$updateResult = $this->trainsRepo->updateTrain(
			trainsId: $trainsId,
			description: $hasKey('description') ? $body->description : null,
			hasDescription: $hasKey('description'),
			trainNumber: $hasKey('train_number') ? $body->train_number : null,
			hasTrainNumber: $hasKey('train_number'),
			maxSpeed: $hasKey('max_speed') ? $body->max_speed : null,
			hasMaxSpeed: $hasKey('max_speed'),
			speedType: $hasKey('speed_type') ? $body->speed_type : null,
			hasSpeedType: $hasKey('speed_type'),
			nominalTractiveCapacity: $hasKey('nominal_tractive_capacity') ? $body->nominal_tractive_capacity : null,
			hasNominalTractiveCapacity: $hasKey('nominal_tractive_capacity'),
			carCount: $hasKey('car_count') ? $body->car_count : null,
			hasCarCount: $hasKey('car_count'),
			destination: $hasKey('destination') ? $body->destination : null,
			hasDestination: $hasKey('destination'),
			beginRemarks: $hasKey('begin_remarks') ? $body->begin_remarks : null,
			hasBeginRemarks: $hasKey('begin_remarks'),
			afterRemarks: $hasKey('after_remarks') ? $body->after_remarks : null,
			hasAfterRemarks: $hasKey('after_remarks'),
			remarks: $hasKey('remarks') ? $body->remarks : null,
			hasRemarks: $hasKey('remarks'),
			beforeDeparture: $hasKey('before_departure') ? $body->before_departure : null,
			hasBeforeDeparture: $hasKey('before_departure'),
			afterArrive: $hasKey('after_arrive') ? $body->after_arrive : null,
			hasAfterArrive: $hasKey('after_arrive'),
			trainInfo: $hasKey('train_info') ? $body->train_info : null,
			hasTrainInfo: $hasKey('train_info'),
			direction: $hasKey('direction') ? $body->direction : null,
			hasDirection: $hasKey('direction'),
			dayCount: $hasKey('day_count') ? $body->day_count : null,
			hasDayCount: $hasKey('day_count'),
			isRideOnMoving: $hasKey('is_ride_on_moving') ? $body->is_ride_on_moving : null,
			hasIsRideOnMoving: $hasKey('is_ride_on_moving'),
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->trainsRepo->selectTrainOne($trainsId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, trainsId:{trainsId})",
			[
				'userId' => $userId,
				'trainsId' => $trainsId,
			],
		);

		$privResult = $this->trainsRepo->selectPrivilegeType(
			id: $trainsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errTrainNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// Child TimetableRows are NOT cascaded in-request by design (M6).
		// Orphaned rows are reclaimed out-of-band by bin/cleanup-orphans.php (run via cron).
		// Note: this supersedes the §8 in-request-cascade expectation for this pair.
		return $this->trainsRepo->deleteTrain($trainsId);
	}
}
