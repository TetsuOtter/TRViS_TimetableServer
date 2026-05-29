<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * StationsService — Project-rooted CRUD for Station (the consolidated station
 * model; the former work-group-rooted `stations` table was abolished and the
 * project-rooted `project_stations` renamed to `stations`).
 *
 * `location_km` / `record_type` are optional on create/update; the DB defaults
 * them to 0 / normal (consumed by the TRViS-JSON dump).
 */
final class StationsService
{
	private readonly StationsRepo $stationsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsRepo = new StationsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	public function selectStationOne(
		UuidInterface $stationsId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"selectStationOne(id:{id}, userId:{userId})",
			['id' => $stationsId, 'userId' => $userId],
		);
		return $this->stationsRepo->selectStationOne($userId, $stationsId);
	}

	public function selectStationPage(
		UuidInterface $projectsId,
		string $userId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectStationPage(projectsId:{projectsId}, userId:{userId}, page:{page})",
			['projectsId' => $projectsId, 'userId' => $userId, 'page' => $pageFrom1],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->stationsRepo->selectStationPageTotalCount(
			projectsId: $projectsId,
			userId: $userId,
			topId: $topId,
		);
		$selectResult = $this->stationsRepo->selectStationPage(
			projectsId: $projectsId,
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

	public function createStation(
		UuidInterface $projectsId,
		string $userId,
		string $name,
		?string $fullName,
		float $locationKm,
		?StationLocationLonlat $locationLonlat,
		?float $onStationDetectRadiusM,
		StationRecordType $recordType,
		bool $alwaysShowHh,
	): RetValueOrError {
		$this->logger->debug(
			"createStation(projectsId:{projectsId}, userId:{userId}, name:{name})",
			['projectsId' => $projectsId, 'userId' => $userId, 'name' => $name],
		);

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errProjectNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$stationId = Uuid::uuid7();
		$insertResult = $this->stationsRepo->insertStation(
			stationsId: $stationId,
			projectsId: $projectsId,
			owner: $userId,
			name: $name,
			fullName: $fullName,
			locationKm: $locationKm,
			locationLonlat: $locationLonlat,
			onStationDetectRadiusM: $onStationDetectRadiusM,
			recordType: $recordType,
			alwaysShowHh: $alwaysShowHh,
		);
		if ($insertResult->isError) {
			return $insertResult;
		}

		$selectResult = $this->stationsRepo->selectStationOne($userId, $stationId);
		if ($selectResult->isError) {
			return $selectResult;
		}
		return RetValueOrError::withValue($selectResult->value, Constants::HTTP_CREATED);
	}

	public function updateStation(
		UuidInterface $stationsId,
		string $userId,
		?string $name,
		?string $fullName,
		?float $locationKm,
		bool $hasLocationKm,
		?StationLocationLonlat $locationLonlat,
		bool $hasLocationLonlat,
		?float $onStationDetectRadiusM,
		bool $hasOnStationDetectRadiusM,
		?StationRecordType $recordType,
		?bool $alwaysShowHh,
	): RetValueOrError {
		$this->logger->debug(
			"updateStation(id:{id}, userId:{userId})",
			['id' => $stationsId, 'userId' => $userId],
		);

		// Fetch the station first to check privilege via its projects_id
		$selectResult = $this->stationsRepo->selectStationOne($userId, $stationsId);
		if ($selectResult->isError) {
			return $selectResult;
		}
		$station = $selectResult->value;

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $station->projects_id,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStationNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$updateResult = $this->stationsRepo->updateStation(
			stationsId: $stationsId,
			name: $name,
			fullName: $fullName,
			locationKm: $locationKm,
			hasLocationKm: $hasLocationKm,
			locationLonlat: $locationLonlat,
			hasLocationLonlat: $hasLocationLonlat,
			onStationDetectRadiusM: $onStationDetectRadiusM,
			hasOnStationDetectRadiusM: $hasOnStationDetectRadiusM,
			recordType: $recordType,
			alwaysShowHh: $alwaysShowHh,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->stationsRepo->selectStationOne($userId, $stationsId);
	}

	public function deleteStation(
		UuidInterface $stationsId,
		string $userId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteStation(id:{id}, userId:{userId})",
			['id' => $stationsId, 'userId' => $userId],
		);

		// Fetch the station first to check privilege via its projects_id
		$selectResult = $this->stationsRepo->selectStationOne($userId, $stationsId);
		if ($selectResult->isError) {
			return $selectResult;
		}
		$station = $selectResult->value;

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $station->projects_id,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errStationNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->stationsRepo->deleteStation($stationsId);
	}
}
