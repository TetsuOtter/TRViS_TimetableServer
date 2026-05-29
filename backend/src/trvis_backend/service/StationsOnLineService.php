<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\repo\StationsOnLineRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * StationsOnLine service. Privilege root is **Line** (indirect):
 * - create / getPage use LineRepo::selectPrivilegeType keyed on linesId
 *   (which joins project_lines → projects_privileges internally).
 * - getOne / update / delete use StationsOnLineRepo::selectPrivilegeType keyed
 *   on stationOnLineId (which resolves project_lines_id then delegates to
 *   LineRepo::selectPrivilegeType).
 *
 * Write-access contract: insufficient WRITE privilege returns 404 (not 403),
 * mirroring the legacy MyServiceBase::checkPrivilegeToWrite behaviour where
 * under-privileged callers see "content not found".
 */
final class StationsOnLineService
{
	private readonly StationsOnLineRepo $stationsOnLineRepo;
	private readonly LineRepo $lineRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsOnLineRepo = new StationsOnLineRepo($db, $logger);
		$this->lineRepo = new LineRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<StationOnLine>
	 */
	public function getOne(
		string $userId,
		UuidInterface $stationOnLineId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, stationOnLineId:{id})",
			['userId' => $userId, 'id' => $stationOnLineId],
		);

		return $this->stationsOnLineRepo->selectStationOnLineOne($userId, $stationOnLineId);
	}

	/**
	 * @return RetValueOrError<array<StationOnLine>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $linesId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, linesId:{linesId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'linesId' => $linesId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->stationsOnLineRepo->selectStationOnLinePageTotalCount(
			linesId: $linesId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->stationsOnLineRepo->selectStationOnLinePage(
			linesId: $linesId,
			userId: $userId,
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
	 * @return RetValueOrError<array<StationOnLine>>
	 */
	public function create(
		UuidInterface $linesId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(linesId:{linesId}, userId:{userId})",
			['linesId' => $linesId, 'userId' => $userId],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->lineRepo->selectPrivilegeType(
			id: $linesId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errLineNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $d) {
				$stationOnLineId = Uuid::uuid7();
				$insertResult = $this->stationsOnLineRepo->insertStationOnLine(
					stationOnLineId: $stationOnLineId,
					linesId: $linesId,
					owner: $userId,
					projectStationsId: $d->project_stations_id,
					locationM: $d->location_m,
					locationLonlat: $d->location_lonlat,
					trackHiddenByDefault: $d->track_hidden_by_default ?? false,
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $stationOnLineId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create station on line: {exception}",
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

		// Select the inserted rows back and return them as an array
		$selectListResult = $this->stationsOnLineRepo->selectListByIds($userId, $insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->stations_on_line_id] = $m;
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
	 * @return RetValueOrError<StationOnLine>
	 */
	public function update(
		string $userId,
		UuidInterface $stationOnLineId,
		object $body,
		array $requestedKeys,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, stationOnLineId:{id})",
			['userId' => $userId, 'id' => $stationOnLineId],
		);

		// Check read access via selectStationOnLineOne (JOINs projects_privileges).
		$selectResult = $this->stationsOnLineRepo->selectStationOnLineOne($userId, $stationOnLineId);
		if ($selectResult->isError) {
			return $selectResult;
		}

		// Check write privilege via StationsOnLineRepo::selectPrivilegeType
		$privResult = $this->stationsOnLineRepo->selectPrivilegeType(
			id: $stationOnLineId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStationOnLineNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$locationM = in_array('location_m', $requestedKeys) ? $body->location_m : null;

		$hasLonlat = in_array('location_lonlat', $requestedKeys);
		$locationLonlat = $hasLonlat ? $body->location_lonlat : null;

		$trackHiddenByDefault = in_array('track_hidden_by_default', $requestedKeys)
			? $body->track_hidden_by_default
			: null;

		$updateResult = $this->stationsOnLineRepo->updateStationOnLine(
			stationOnLineId: $stationOnLineId,
			locationM: $locationM,
			locationLonlat: $locationLonlat,
			hasLocationLonlat: $hasLonlat,
			trackHiddenByDefault: $trackHiddenByDefault,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->stationsOnLineRepo->selectStationOnLineOne($userId, $stationOnLineId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $stationOnLineId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, stationOnLineId:{id})",
			['userId' => $userId, 'id' => $stationOnLineId],
		);

		// Check read access first
		$selectResult = $this->stationsOnLineRepo->selectStationOnLineOne($userId, $stationOnLineId);
		if ($selectResult->isError) {
			return $selectResult;
		}

		// Check write privilege via StationsOnLineRepo::selectPrivilegeType
		$privResult = $this->stationsOnLineRepo->selectPrivilegeType(
			id: $stationOnLineId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStationOnLineNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->stationsOnLineRepo->deleteStationOnLine($stationOnLineId);
	}
}
