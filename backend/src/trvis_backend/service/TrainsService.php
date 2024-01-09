<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class TrainsService
{
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly TrainsRepo $trainsRepo;
	private readonly WorksRepo $worksRepo;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->trainsRepo = new TrainsRepo($db, $logger);
		$this->worksRepo = new WorksRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<array<Train>>
	 */
	public function create(
		UuidInterface $worksId,
		string $senderUserId,
		/** @param array<Train> $trainsList */
		array $trainsList,
	): RetValueOrError {
		$this->logger->debug(
			"createTrains senderUserId: {senderUserId}, trainsList: {trainsList}",
			[
				'senderUserId' => $senderUserId,
				'trainsList' => $trainsList,
			]
		);

		$checkIdResult = $this->worksRepo->selectWorkGroupsId(
			worksId: $worksId,
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
				'User[{userId}] does not have permission to create works',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to create works',
			);
		}

		$worksCount = count($trainsList);
		$trainsIdList = array_fill(0, $worksCount, null);
		for ($i = 0; $i < $worksCount; $i++) {
			$trainsIdList[$i] = Uuid::uuid7();
		}
		$this->logger->debug(
			'trainsIdList: {trainsIdList}',
			[
				'trainsIdList' => $trainsIdList,
			],
		);
		$insertResult = $this->trainsRepo->insertList(
			worksId: $worksId,
			ownerUserId: $senderUserId,
			trainsIdList: $trainsIdList,
			trains: $trainsList,
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
			'trains inserted -> {trainsIdList}',
			[
				'trainsIdList' => $trainsIdList,
			],
		);

		return $this->trainsRepo->selectList(
			trainsIdList: $trainsIdList,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			'deleteTrains senderUserId: {senderUserId}, trainsId: {trainsId}',
			[
				'senderUserId' => $senderUserId,
				'trainsId' => $trainsId,
			],
		);

		$checkIdResult = $this->trainsRepo->selectWorkGroupsId(
			trainsId: $trainsId,
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
				'User[{userId}] does not have permission to delete works',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to delete works',
			);
		}
		$deleteResult = $this->trainsRepo->deleteOne(
			trainsId: $trainsId,
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
	 * @return RetValueOrError<Train>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $trainsId,
	): RetValueOrError {
		$this->logger->debug(
			'getOneTrains senderUserId: {senderUserId}, trainsId: {trainsId}',
			[
				'senderUserId' => $senderUserId,
				'trainsId' => $trainsId,
			],
		);

		$checkIdResult = $this->trainsRepo->selectWorkGroupsId(
			trainsId: $trainsId,
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

		return $this->trainsRepo->selectOne(
			trainsId: $trainsId,
		);
	}

	/**
	 * @return RetValueOrError<array<Train>>
	 */
	public function getPage(
		string $senderUserId,
		UuidInterface $worksId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'getPageTrains senderUserId: {senderUserId}, worksId: {worksId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'senderUserId' => $senderUserId,
				'worksId' => $worksId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$checkIdResult = $this->worksRepo->selectWorkGroupsId(
			worksId: $worksId,
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

		return $this->trainsRepo->selectPage(
			worksId: $worksId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	/**
	 * @return RetValueOrError<Train>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $trainsId,
		Train $data,
		object|array $requestBody,
	): RetValueOrError {
		$this->logger->debug(
			'updateTrains senderUserId: {senderUserId}, trainsId: {trainsId}, data: {data}',
			[
				'senderUserId' => $senderUserId,
				'trainsId' => $trainsId,
				'data' => $data,
			],
		);

		$checkIdResult = $this->trainsRepo->selectWorkGroupsId(
			trainsId: $trainsId,
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
				'User[{userId}] does not have permission to edit works',
				[
					'userId' => $senderUserId,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_FORBIDDEN,
				'You do not have permission to edit works',
			);
		}

		$kvpArray = Utils::getArrayForUpdateSource(
			[
				'train_number',
				'description',
				'max_speed',
				'speed_type',
				'nominal_tractive_capacity',
				'car_count',
				'destination',
				'begin_remarks',
				'after_remarks',
				'remarks',
				'before_remarks',
				'after_remarks',
				'train_info',
				'direction',
				'day_count',
				'is_ride_on_moving',
			],
			$requestBody,
			$data->getData(),
		);
		$updateResult = $this->trainsRepo->update(
			trainsId: $trainsId,
			trainsProps: $kvpArray,
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

		return $this->trainsRepo->selectOne(
			trainsId: $trainsId,
		);
	}
}
