<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * StationTracksService — standalone orchestration for StationTrack
 * (Station-rooted via stations_id).
 *
 * Privilege root: StationsRepo (privilege resolves up through stations →
 * projects_privileges; stations is now Project-rooted). For create/getPage, $stationsId is the
 * privilege anchor resolved via StationsRepo::selectPrivilegeType; for
 * getOne/update/delete, the privilege anchor is also resolved via
 * StationsRepo::selectPrivilegeType, but the stations_id is first fetched from
 * station_tracks via StationTracksRepo::selectStationsIdByStationTracksId.
 *
 * Faithful port of legacy StationTracksService (which extended MyServiceBase
 * with parentRepo=new StationsRepo). The standalone pattern replaces
 * MyServiceBase's generic checkPrivilegeToRead/Write with explicit delegates.
 * WorkGroupsPrivilegesRepo is NOT used here — privilege is routed through
 * StationsRepo, which is the direct parent in the hierarchy.
 *
 * Privilege-delegate invariant (per CONTRIBUTING-P3.md): every privilege check
 * calls EXACTLY ->selectPrivilegeType(id: $x, userId: $userId,
 * includeAnonymous: true) — 3 named args, never selectForUpdate.
 */
final class StationTracksService
{
	private readonly StationTracksRepo $stationTracksRepo;
	private readonly StationsRepo $stationsRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationTracksRepo = new StationTracksRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<StationTrack>
	 */
	public function getOne(
		string $userId,
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(stationTracksId:{id}, userId:{userId})",
			['id' => $stationTracksId, 'userId' => $userId],
		);

		$stationsIdResult = $this->stationTracksRepo->selectStationsIdByStationTracksId($stationTracksId);
		if ($stationsIdResult->isError) {
			return $stationsIdResult;
		}
		$stationsId = $stationsIdResult->value;

		$privResult = $this->stationsRepo->selectPrivilegeType(
			id: $stationsId,
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

		return $this->stationTracksRepo->selectStationTrackOne($userId, $stationTracksId);
	}

	/**
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $stationsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(stationsId:{stationsId}, userId:{userId}, page:{page})",
			['stationsId' => $stationsId, 'userId' => $userId, 'page' => $pageFrom1],
		);

		$privResult = $this->stationsRepo->selectPrivilegeType(
			id: $stationsId,
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
		$totalCountResult = $this->stationTracksRepo->selectStationTrackPageTotalCount(
			stationsId: $stationsId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->stationTracksRepo->selectStationTrackPage(
			stationsId: $stationsId,
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
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function create(
		UuidInterface $stationsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(stationsId:{stationsId}, userId:{userId})",
			['stationsId' => $stationsId, 'userId' => $userId],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->stationsRepo->selectPrivilegeType(
			id: $stationsId,
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
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// C2: the whole bulk insert (and its read-back) must be atomic.
		// Without the transaction a mid-loop failure leaves 0..N-1 rows
		// autocommitted while the client gets an error. Mirrors the
		// TrainsService::create frame (begin/commit + inTransaction-guarded
		// rollBack).
		$results = [];
		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			foreach ($dataList as $data) {
				$stationTrackId = Uuid::uuid7();
				$insertResult = $this->stationTracksRepo->insertStationTrack(
					stationTracksId: $stationTrackId,
					stationsId: $stationsId,
					owner: $userId,
					description: (string)($data->description ?? ''),
					name: (string)$data->name,
					runInLimit: is_null($data->run_in_limit) ? null : intval($data->run_in_limit),
					runOutLimit: is_null($data->run_out_limit) ? null : intval($data->run_out_limit),
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $stationTrackId;
			}

			$selectListResult = $this->stationTracksRepo->selectListByIds($userId, $insertedIds);
			if ($selectListResult->isError) {
				$this->db->rollBack();
				return $selectListResult;
			}

			$byId = [];
			foreach ($selectListResult->value as $m) {
				$byId[(string)$m->station_tracks_id] = $m;
			}
			if (count($byId) !== count($insertedIds)) {
				$this->db->rollBack();
				return RetValueOrError::withError(
					Constants::HTTP_INTERNAL_SERVER_ERROR,
					'inconsistent read-back after bulk insert',
				);
			}
			foreach ($insertedIds as $id) {
				$results[] = $byId[(string)$id];
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create station track: {exception}",
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

		return RetValueOrError::withValue($results, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<StationTrack>
	 */
	public function update(
		string $userId,
		UuidInterface $stationTracksId,
		object $data,
		array|object $requestBody,
	): RetValueOrError {
		$this->logger->debug(
			"update(stationTracksId:{id}, userId:{userId})",
			['id' => $stationTracksId, 'userId' => $userId],
		);

		$stationsIdResult = $this->stationTracksRepo->selectStationsIdByStationTracksId($stationTracksId);
		if ($stationsIdResult->isError) {
			return $stationsIdResult;
		}
		$stationsId = $stationsIdResult->value;

		$privResult = $this->stationsRepo->selectPrivilegeType(
			id: $stationsId,
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
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$checkPropExists = is_array($requestBody)
			? (fn (string $key): bool => array_key_exists($key, $requestBody))
			: (fn (string $key): bool => property_exists($requestBody ?? (object)[], $key));

		$description = $checkPropExists('description') ? ($data->description ?? null) : null;
		$name = $checkPropExists('name') ? ($data->name ?? null) : null;
		$runInLimit = $checkPropExists('run_in_limit')
			? (is_null($data->run_in_limit) ? null : intval($data->run_in_limit))
			: null;
		$runOutLimit = $checkPropExists('run_out_limit')
			? (is_null($data->run_out_limit) ? null : intval($data->run_out_limit))
			: null;

		$updateResult = $this->stationTracksRepo->updateStationTrack(
			stationTracksId: $stationTracksId,
			description: $description,
			name: $name,
			runInLimit: $runInLimit,
			runOutLimit: $runOutLimit,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->stationTracksRepo->selectStationTrackOne($userId, $stationTracksId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(stationTracksId:{id}, userId:{userId})",
			['id' => $stationTracksId, 'userId' => $userId],
		);

		$stationsIdResult = $this->stationTracksRepo->selectStationsIdByStationTracksId($stationTracksId);
		if ($stationsIdResult->isError) {
			return $stationsIdResult;
		}
		$stationsId = $stationsIdResult->value;

		$privResult = $this->stationsRepo->selectPrivilegeType(
			id: $stationsId,
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
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->stationTracksRepo->deleteStationTrack($stationTracksId);
	}
}
