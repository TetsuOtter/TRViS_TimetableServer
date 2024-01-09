<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class StationsService
{
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly StationsRepo $stationsRepo;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<array<Station>>
	 */
	public function create(
		UuidInterface $workGroupsId,
		string $senderUserId,
		/** @param array<Station> $stationsList */
		array $stationsList,
	): RetValueOrError {
		$this->logger->debug(
			"createStation workGroupsId: {workGroupsId}, senderUserId: {senderUserId}, stationsList: {stationsList}",
			[
				'workGroupsId' => $workGroupsId,
				'senderUserId' => $senderUserId,
				'stationsList' => $stationsList,
			]
		);

		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeCheckResult->isError) {
			$this->logger->warning(
				'senderPrivilegeCheckResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $senderPrivilegeCheckResult->errorCode,
					'errorMsg' => $senderPrivilegeCheckResult->errorMsg,
				],
			);
			return $senderPrivilegeCheckResult;
		}
		$senderPrivilege = $senderPrivilegeCheckResult->value;
		$this->logger->debug(
			'senderPrivilege: {senderPrivilege}',
			[
				'senderPrivilege' => $senderPrivilege
			],
		);
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::write)) {
			$this->logger->warning(
				'User[{userId}] does not have permission to create stations',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to create stations',
			);
		}

		$stationsCount = count($stationsList);
		$stationsIdList = array_fill(0, $stationsCount, null);
		for ($i = 0; $i < $stationsCount; $i++) {
			$stationsIdList[$i] = Uuid::uuid7();
		}
		$this->logger->debug(
			'stationsIdList: {stationsIdList}',
			[
				'stationsIdList' => $stationsIdList,
			],
		);
		$insertResult = $this->stationsRepo->insertList(
			workGroupsId: $workGroupsId,
			ownerUserId: $senderUserId,
			stationsIdList: $stationsIdList,
			stations: $stationsList,
		);
		if ($insertResult->isError) {
			$this->logger->warning(
				'insertResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $insertResult->errorCode,
					'errorMsg' => $insertResult->errorMsg,
				],
			);
			return $insertResult;
		}

		$this->logger->debug(
			'stations inserted -> {stationsIdList}',
			[
				'stationsIdList' => $stationsIdList,
			],
		);

		return $this->stationsRepo->selectList(
			stationsIdList: $stationsIdList,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'deleteStation senderUserId: {senderUserId}, stationsId: {stationsId}',
			[
				'senderUserId' => $senderUserId,
				'stationsId' => $stationsId,
			],
		);

		$checkIdResult = $this->stationsRepo->selectWorkGroupsId(
			stationsId: $stationsId,
		);
		if ($checkIdResult->isError) {
			$this->logger->warning(
				'checkIdResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $checkIdResult->errorCode,
					'errorMsg' => $checkIdResult->errorMsg,
				],
			);
			return $checkIdResult;
		}
		$workGroupsId = $checkIdResult->value;

		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeCheckResult->isError) {
			$this->logger->warning(
				'senderPrivilegeCheckResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $senderPrivilegeCheckResult->errorCode,
					'errorMsg' => $senderPrivilegeCheckResult->errorMsg,
				],
			);
			return $senderPrivilegeCheckResult;
		}

		$senderPrivilege = $senderPrivilegeCheckResult->value;
		$this->logger->debug(
			'senderPrivilege: {senderPrivilege}',
			[
				'senderPrivilege' => $senderPrivilege
			],
		);
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::write)) {
			$this->logger->warning(
				'User[{userId}] does not have permission to delete stations',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to delete stations',
			);
		}
		$deleteResult = $this->stationsRepo->deleteOne(
			stationsId: $stationsId,
		);
		if ($deleteResult->isError) {
			$this->logger->warning(
				'deleteResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $deleteResult->errorCode,
					'errorMsg' => $deleteResult->errorMsg,
				],
			);
			return $deleteResult;
		}
		return RetValueOrError::withValue(null);
	}

	/**
	 * @return RetValueOrError<Station>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'getOneStation senderUserId: {senderUserId}, stationsId: {stationsId}',
			[
				'senderUserId' => $senderUserId,
				'stationsId' => $stationsId,
			],
		);

		$selectWorkResult = $this->stationsRepo->selectOne(
			stationsId: $stationsId,
		);
		$this->logger->debug(
			'selectWorkResult -> {selectWorkResult}',
			[
				'selectWorkResult' => $selectWorkResult->value,
			],
		);
		if ($selectWorkResult->isError) {
			$this->logger->warning(
				'selectWorkResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $selectWorkResult->errorCode,
					'errorMsg' => $selectWorkResult->errorMsg,
				],
			);
			return $selectWorkResult;
		}
		$workGroupsId = $selectWorkResult->value->work_groups_id;

		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeCheckResult->isError) {
			$this->logger->warning(
				'senderPrivilegeCheckResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $senderPrivilegeCheckResult->errorCode,
					'errorMsg' => $senderPrivilegeCheckResult->errorMsg,
				],
			);
			return $senderPrivilegeCheckResult;
		}

		$senderPrivilege = $senderPrivilegeCheckResult->value;
		$this->logger->debug(
			'senderPrivilege: {senderPrivilege}',
			[
				'senderPrivilege' => $senderPrivilege
			],
		);
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}

		return $selectWorkResult;
	}

	/**
	 * @return RetValueOrError<array<Station>>
	 */
	public function getPage(
		string $senderUserId,
		UuidInterface $workGroupsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'getPageStation senderUserId: {senderUserId}, workGroupsId: {workGroupsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'senderUserId' => $senderUserId,
				'workGroupsId' => $workGroupsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeCheckResult->isError) {
			$this->logger->warning(
				'senderPrivilegeCheckResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $senderPrivilegeCheckResult->errorCode,
					'errorMsg' => $senderPrivilegeCheckResult->errorMsg,
				],
			);
			return $senderPrivilegeCheckResult;
		}

		$senderPrivilege = $senderPrivilegeCheckResult->value;
		$this->logger->debug(
			'senderPrivilege: {senderPrivilege}',
			[
				'senderPrivilege' => $senderPrivilege
			],
		);
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}

		return $this->stationsRepo->selectPage(
			workGroupsId: $workGroupsId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	/**
	 * @return RetValueOrError<Station>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $stationsId,
		Station $data,
		object|array $requestBody,
	): RetValueOrError {
		$this->logger->debug(
			'updateStation senderUserId: {senderUserId}, stationsId: {stationsId}, data: {data}',
			[
				'senderUserId' => $senderUserId,
				'stationsId' => $stationsId,
				'data' => $data,
			],
		);

		$checkIdResult = $this->stationsRepo->selectWorkGroupsId(
			stationsId: $stationsId,
		);
		if ($checkIdResult->isError) {
			$this->logger->warning(
				'checkIdResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $checkIdResult->errorCode,
					'errorMsg' => $checkIdResult->errorMsg,
				],
			);
			return $checkIdResult;
		}
		$workGroupsId = $checkIdResult->value;

		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			workGroupsId: $workGroupsId,
			userId: $senderUserId,
			includeAnonymous: true,
		);
		if ($senderPrivilegeCheckResult->isError) {
			$this->logger->warning(
				'senderPrivilegeCheckResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $senderPrivilegeCheckResult->errorCode,
					'errorMsg' => $senderPrivilegeCheckResult->errorMsg,
				],
			);
			return $senderPrivilegeCheckResult;
		}

		$senderPrivilege = $senderPrivilegeCheckResult->value;
		$this->logger->debug(
			'senderPrivilege: {senderPrivilege}',
			[
				'senderPrivilege' => $senderPrivilege
			],
		);
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errWorkGroupNotFound();
		}
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::write)) {
			$this->logger->warning(
				'User[{userId}] does not have permission to edit stations',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to edit stations',
			);
		}

		$kvpArray = Utils::getArrayForUpdateSource(
			[
				'name',
				'description',
				'location_km',
				'location_lonlat',
				'on_station_detect_radius_m',
				'record_type',
			],
			$requestBody,
			$data->getData(),
		);
		$updateResult = $this->stationsRepo->update(
			stationsId: $stationsId,
			stationsProps: $kvpArray,
		);
		if ($updateResult->isError) {
			$this->logger->warning(
				'updateResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $updateResult->errorCode,
					'errorMsg' => $updateResult->errorMsg,
				],
			);
			return $updateResult;
		}

		return $this->stationsRepo->selectOne(
			stationsId: $stationsId,
		);
	}
}
