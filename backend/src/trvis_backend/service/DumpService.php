<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\TRViSJsonWorkGroup;
use dev_t0r\trvis_backend\repo\TimetableRowsRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

final class DumpService
{
	private readonly WorkGroupsRepo $workGroupsRepo;
	private readonly WorksRepo $worksRepo;
	private readonly TrainsRepo $trainsRepo;
	private readonly TimetableRowsRepo $timetableRowsRepo;
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsRepo = new WorkGroupsRepo(
			db: $this->db,
			logger: $this->logger,
		);
		$this->worksRepo = new WorksRepo(
			db: $this->db,
			logger: $this->logger,
		);
		$this->trainsRepo = new TrainsRepo(
			db: $this->db,
			logger: $this->logger,
		);
		$this->timetableRowsRepo = new TimetableRowsRepo(
			db: $this->db,
			logger: $this->logger,
		);
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo(
			db: $this->db,
			logger: $this->logger,
		);
	}

		/**
	 * @return RetValueOrError<null>
	 */
	protected function checkPrivilegeToRead(
		UuidInterface $id,
		string $senderUserId,
	): RetValueOrError {
		$senderPrivilegeCheckResult = $this->workGroupsPrivilegesRepo->selectPrivilegeType(
			id: $id,
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
		return RetValueOrError::withValue(null);
	}

	public function dump(
		UuidInterface $workGroupsId,
		string $senderUserId,
	): RetValueOrError
	{
		$this->logger->debug(
			'DumpService::dump() called - {workGroupsId} by {senderUserId}',
			[
				'workGroupsId' => $workGroupsId->toString(),
				'senderUserId' => $senderUserId,
			],
		);

		$senderPrivilegeCheckResult = $this->checkPrivilegeToRead(
			id: $workGroupsId,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		$workGroupsResult = $this->workGroupsRepo->selectWorkGroupOne(
			workGroupId: $workGroupsId,
		);
		if ($workGroupsResult->isError) {
			$this->logger->warning(
				'workGroupsResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $workGroupsResult->errorCode,
					'errorMsg' => $workGroupsResult->errorMsg,
				],
			);
			return $workGroupsResult;
		}
		$this->logger->debug(
			'workGroupsResult: {workGroupsResult}',
			[
				'workGroupsResult' => $workGroupsResult->value->getData(),
			],
		);

		$worksDst = [];
		$worksDumpResult = $this->worksRepo->dump(
			parentIdList: [$workGroupsId],
			dst: $worksDst,
		);
		if ($worksDumpResult->isError) {
			$this->logger->warning(
				'worksDumpResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $worksDumpResult->errorCode,
					'errorMsg' => $worksDumpResult->errorMsg,
				],
			);
			return $worksDumpResult;
		}
		$worksIdList = $worksDumpResult->value;
		$this->logger->debug(
			'worksIdList: {worksIdList}',
			[
				'worksIdList' => $worksIdList,
			],
		);

		$trainsDst = [];
		$trainsDumpResult = $this->trainsRepo->dump(
			parentIdList: $worksIdList,
			dst: $trainsDst,
		);
		if ($trainsDumpResult->isError) {
			$this->logger->warning(
				'trainsDumpResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $trainsDumpResult->errorCode,
					'errorMsg' => $trainsDumpResult->errorMsg,
				],
			);
			return $trainsDumpResult;
		}

		$trainsIdList = $trainsDumpResult->value;
		$this->logger->debug(
			'trainsIdList: {trainsIdList}',
			[
				'trainsIdList' => $trainsIdList,
			],
		);

		$timetableRowsDst = [];
		$timetableRowsDumpResult = $this->timetableRowsRepo->dump(
			parentIdList: $trainsIdList,
			dst: $timetableRowsDst,
		);
		if ($timetableRowsDumpResult->isError) {
			$this->logger->warning(
				'timetableRowsDumpResult -> Error[{errorCode}]: {errorMsg}',
				[
					'errorCode' => $timetableRowsDumpResult->errorCode,
					'errorMsg' => $timetableRowsDumpResult->errorMsg,
				],
			);
			return $timetableRowsDumpResult;
		}
		$timetableRowsIdList = $timetableRowsDumpResult->value;
		$this->logger->debug(
			'timetableRowsIdList: {timetableRowsIdList}',
			[
				'timetableRowsIdList' => $timetableRowsIdList,
			],
		);

		$trainObjs = [];
		foreach ($trainsDst as $trainList) {
			foreach ($trainList as $train) {
				$trainObjs[strtoupper($train->id->getHex()->toString())] = &$train;
			}
		}
		foreach ($timetableRowsDst as $_trainsId => $timetableRowsList) {
			if ($trainObjs[$_trainsId]->data->Direction < 0) {
				$timetableRowsList = array_reverse($timetableRowsList);
			}
			$trainObjs[$_trainsId]->data->TimetableRows = $timetableRowsList;
		}

		$workObjs = [];
		foreach ($worksDst as $workList) {
			foreach ($workList as $work) {
				$workObjs[strtoupper($work->id->getHex()->toString())] = &$work;
			}
		}
		foreach ($trainsDst as $_worksId => $trainsList) {
			$workObjs[$_worksId]->data->Trains = $trainsList;
		}

		$workGroups = new TRViSJsonWorkGroup();
		$workGroups->setData([
			'Name' => $workGroupsResult->value->name,
			'DBVersion' => 1,
			'Works' => [],
		]);
		$workGroupObjs = [
			strtoupper($workGroupsId->getHex()->toString()) => &$workGroups,
		];
		foreach ($worksDst as $key => $value) {
			$workGroupObjs[strtoupper($key)]->Works = $value;
		}

		$this->logger->debug('workGroup dump complete');

		return RetValueOrError::withValue($workGroups);
	}
}
