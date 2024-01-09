<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class WorksService
{
	private readonly WorksRepo $worksRepo;
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->worksRepo = new WorksRepo($db, $logger);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<array<Work>>
	 */
	public function create(
		UuidInterface $workGroupsId,
		string $senderUserId,
		/** @param array<Work> $worksList */
		array $worksList,
	): RetValueOrError {
		$this->logger->debug(
			"createWork workGroupsId: {workGroupsId}, senderUserId: {senderUserId}, worksList: {worksList}",
			[
				'workGroupsId' => $workGroupsId,
				'senderUserId' => $senderUserId,
				'worksList' => $worksList,
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

		$worksCount = count($worksList);
		$worksIdList = array_fill(0, $worksCount, null);
		for ($i = 0; $i < $worksCount; $i++) {
			$worksIdList[$i] = Uuid::uuid7();
		}
		$this->logger->debug(
			'worksIdList: {worksIdList}',
			[
				'worksIdList' => $worksIdList,
			],
		);
		$insertResult = $this->worksRepo->insertList(
			workGroupsId: $workGroupsId,
			ownerUserId: $senderUserId,
			worksIdList: $worksIdList,
			works: $worksList,
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
			'works inserted -> {worksIdList}',
			[
				'worksIdList' => $worksIdList,
			],
		);

		return $this->worksRepo->selectList(
			worksIdList: $worksIdList,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			'deleteWork senderUserId: {senderUserId}, worksId: {worksId}',
			[
				'senderUserId' => $senderUserId,
				'worksId' => $worksId,
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
		$deleteResult = $this->worksRepo->deleteOne(
			worksId: $worksId,
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
	 * @return RetValueOrError<Work>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $worksId,
	): RetValueOrError {
		$this->logger->debug(
			'getOneWork senderUserId: {senderUserId}, worksId: {worksId}',
			[
				'senderUserId' => $senderUserId,
				'worksId' => $worksId,
			],
		);

		$selectWorkResult = null;
		$selectWorkResult = $this->worksRepo->selectOne(
			worksId: $worksId,
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

		$selectWorkResult ??= $this->worksRepo->selectOne(
			worksId: $worksId,
		);

		return $selectWorkResult;
	}

	/**
	 * @return RetValueOrError<array<Work>>
	 */
	public function getPage(
		string $senderUserId,
		UuidInterface $workGroupsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'getPageWork senderUserId: {senderUserId}, workGroupsId: {workGroupsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
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

		return $this->worksRepo->selectPage(
			workGroupsId: $workGroupsId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	/**
	 * @return RetValueOrError<Work>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $worksId,
		Work $data,
		object|array $requestBody,
	): RetValueOrError {
		$this->logger->debug(
			'updateWork senderUserId: {senderUserId}, worksId: {worksId}, data: {data}',
			[
				'senderUserId' => $senderUserId,
				'worksId' => $worksId,
				'data' => $data,
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
				'name',
				'description',
				'affect_date',
				'affix_content_type',
				'remarks',
				'has_e_train_timetable',
				'e_train_timetable_content_type',
			],
			$requestBody,
			$data->getData(),
		);
		$updateResult = $this->worksRepo->update(
			worksId: $worksId,
			worksProps: $kvpArray,
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

		return $this->worksRepo->selectOne(
			worksId: $worksId,
		);
	}
}
