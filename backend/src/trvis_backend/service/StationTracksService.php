<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class StationTracksService
{
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly StationTracksRepo $stationTracksRepo;
	private readonly StationsRepo $stationsRepo;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->stationTracksRepo = new StationTracksRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function create(
		?UuidInterface $workGroupsId,
		UuidInterface $stationsId,
		string $senderUserId,
		/** @param array<StationTrack> $stationTracksList */
		array $stationTracksList,
	): RetValueOrError {
		$this->logger->debug(
			"createStationTrack workGroupsId: {workGroupsId}, senderUserId: {senderUserId}, stationTracksList: {stationTracksList}",
			[
				'workGroupsId' => $workGroupsId,
				'senderUserId' => $senderUserId,
				'stationTracksList' => $stationTracksList,
			]
		);

		if (is_null($workGroupsId)) {
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
			$this->logger->debug(
				'checkIdResult -> {checkIdResult}',
				[
					'checkIdResult' => $checkIdResult->value,
				],
			);
			$workGroupsId = $checkIdResult->value;
		}

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

		$stationsCount = count($stationTracksList);
		$stationTracksIdList = array_fill(0, $stationsCount, null);
		for ($i = 0; $i < $stationsCount; $i++) {
			$stationTracksIdList[$i] = Uuid::uuid7();
		}
		$this->logger->debug(
			'stationTracksIdList: {stationTracksIdList}',
			[
				'stationTracksIdList' => $stationTracksIdList,
			],
		);
		$insertResult = $this->stationTracksRepo->insertList(
			stationsId: $stationsId,
			ownerUserId: $senderUserId,
			stationTracksIdList: $stationTracksIdList,
			stationTracks: $stationTracksList,
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
			'stationTracks inserted -> {stationTracksIdList}',
			[
				'stationTracksIdList' => $stationTracksIdList,
			],
		);

		return $this->stationTracksRepo->selectList(
			stationTracksIdList: $stationTracksIdList,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $stationTracksId,
		?UuidInterface $workGroupsId,
		?UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'deleteStationTrack senderUserId: {senderUserId}, stationTracksId: {stationTracksId}, workGroupsId: {workGroupsId}, stationsId: {stationsId}',
			[
				'senderUserId' => $senderUserId,
				'stationTracksId' => $stationTracksId,
				'workGroupsId' => $workGroupsId,
				'stationsId' => $stationsId,
			],
		);

		if (is_null($workGroupsId) || is_null($stationsId)) {
			$checkIdResult = $this->stationTracksRepo->selectWorkGroupsId(
				stationTracksId: $stationTracksId,
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
		}

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
		$deleteResult = $this->stationTracksRepo->deleteOne(
			stationTracksId: $stationTracksId,
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
	 * @return RetValueOrError<StationTrack>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $stationTracksId,
		?UuidInterface $workGroupsId,
		?UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'getOneStationTrack senderUserId: {senderUserId}, stationTracksId: {stationTracksId}, workGroupsId: {workGroupsId}',
			[
				'senderUserId' => $senderUserId,
				'stationTracksId' => $stationTracksId,
				'workGroupsId' => $workGroupsId,
			],
		);

		if (is_null($workGroupsId) || is_null($stationsId)) {
			$checkIdResult = $this->stationTracksRepo->selectWorkGroupsId(
				stationTracksId: $stationTracksId,
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
		}

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

		return $this->stationTracksRepo->selectOne(
			stationTracksId: $stationTracksId,
			workGroupsId: $workGroupsId,
			stationsId: $stationsId,
		);
	}

	/**
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function getPage(
		string $senderUserId,
		?UuidInterface $workGroupsId,
		UuidInterface $stationsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'getPageStationTrack senderUserId: {senderUserId}, workGroupsId: {workGroupsId}, stationsId: {stationsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'senderUserId' => $senderUserId,
				'workGroupsId' => $workGroupsId,
				'stationsId' => $stationsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$workGroupsIdOrig = $workGroupsId;
		if (is_null($workGroupsId)) {
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
		}

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

		return $this->stationTracksRepo->selectPage(
			workGroupsId: $workGroupsIdOrig,
			stationsId: $stationsId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	/**
	 * @return RetValueOrError<StationTrack>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $stationTracksId,
		?UuidInterface $workGroupsId,
		?UuidInterface $stationsId,
		StationTrack $data,
		object|array $requestBody,
	): RetValueOrError {
		$this->logger->debug(
			'updateStationTrack senderUserId: {senderUserId}, stationTracksId: {stationTracksId}, workGroupsId: {workGroupsId}, data: {data}',
			[
				'senderUserId' => $senderUserId,
				'stationTracksId' => $stationTracksId,
				'workGroupsId' => $workGroupsId,
				'data' => $data,
			],
		);

		if (is_null($workGroupsId) || is_null($stationsId)) {
			$checkIdResult = $this->stationTracksRepo->selectWorkGroupsId(
				stationTracksId: $stationTracksId,
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
		}

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
				'run_in_limit',
				'run_out_limit',
			],
			$requestBody,
			$data->getData(),
		);
		$updateResult = $this->stationTracksRepo->update(
			stationTracksId: $stationTracksId,
			stationTracksProps: $kvpArray,
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

		return $this->stationTracksRepo->selectOne(
			stationTracksId: $stationTracksId,
			workGroupsId: null,
			stationsId: null,
		);
	}
}
