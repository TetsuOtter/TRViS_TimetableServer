<?php

namespace dev_t0r\trvis_backend\service;
use dev_t0r\BaseModel;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\IMyRepoBase;
use dev_t0r\trvis_backend\repo\IMyRepoSelectPrivilegeType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T of BaseModel
 * @template TRepoTarget of IMyRepoBase<T>
 * @implements IMyServiceBase<T>
 */
abstract class MyServiceBase implements IMyServiceBase
{
	public function __construct(
		protected PDO $db,
		/** @var TRepoTarget $targetRepo */
		protected readonly IMyRepoBase $targetRepo,
		protected readonly IMyRepoSelectPrivilegeType $parentRepo,
		protected readonly LoggerInterface $logger,
		protected readonly string $dataTypeName,
		/** @var array<string> $keys */
		protected readonly array $keys,
	) {
	}

	/**
	 * @return RetValueOrError<null>
	 */
	protected function checkPrivilegeToRead(
		UuidInterface $id,
		IMyRepoSelectPrivilegeType $repo,
		string $senderUserId,
	): RetValueOrError {
		$senderPrivilegeCheckResult = $repo->selectPrivilegeType(
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
	/**
	 * @return RetValueOrError<null>
	 */
	protected function checkPrivilegeToWrite(
		UuidInterface $id,
		IMyRepoSelectPrivilegeType $repo,
		string $senderUserId,
	): RetValueOrError {
		$senderPrivilegeCheckResult = $repo->selectPrivilegeType(
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
		if (!$senderPrivilege->hasPrivilege(InviteKeyPrivilegeType::write)) {
			$this->logger->warning(
				'User[{userId}] does not have permission to create {dataTypeName}',
				[
					'userId' => $senderUserId,
					'dataTypeName' => $this->dataTypeName,
				],
			);
		}
		return RetValueOrError::withValue(null);
	}

	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function create(
		UuidInterface $parentId,
		string $senderUserId,
		/** @param array<T> $stationsList */
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create{dataTypeName} parentId: {parentId}, senderUserId: {senderUserId}, dataList: {dataList}",
			[
				'dataTypeName' => $this->dataTypeName,
				'parentId' => $parentId,
				'senderUserId' => $senderUserId,
				'dataList' => $dataList,
			]
		);

		$senderPrivilegeCheckResult = $this->checkPrivilegeToWrite(
			id: $parentId,
			repo: $this->parentRepo,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		$dataCount = count($dataList);
		$idList = array_fill(0, $dataCount, null);
		for ($i = 0; $i < $dataCount; $i++) {
			$idList[$i] = Uuid::uuid7();
		}
		$this->logger->debug(
			'idList({dataTypeName}): {idList}',
			[
				'dataTypeName' => $this->dataTypeName,
				'idList' => $idList,
			],
		);

		$this->db->beginTransaction();
		try
		{
			$beforeInsertResult = $this->beforeInsert(
				parentId: $parentId,
				ownerUserId: $senderUserId,
				idList: $idList,
				valueList: $dataList,
			);
			if (!is_null($beforeInsertResult)) {
				$this->logger->warning(
					'beforeInsertResult -> value:{value} -> [{errorCode}]: {errorMsg}',
					[
						'value' => $beforeInsertResult->value,
						'errorCode' => $beforeInsertResult->errorCode,
						'errorMsg' => $beforeInsertResult->errorMsg,
					],
				);
				if ($beforeInsertResult->isError) {
					$this->db->rollBack();
				} else {
					$this->db->commit();
				}
				return $beforeInsertResult;
			}

			$insertResult = $this->targetRepo->insertList(
				parentId: $parentId,
				ownerUserId: $senderUserId,
				idList: $idList,
				valueList: $dataList,
			);

			if ($insertResult->isError) {
				$this->logger->warning(
					'insertResult -> Error[{errorCode}]: {errorMsg}',
					[
						'errorCode' => $insertResult->errorCode,
						'errorMsg' => $insertResult->errorMsg,
					],
				);
				$this->db->rollBack();
				return $insertResult;
			}

			$afterInsertResult = $this->afterInsert(
				parentId: $parentId,
				ownerUserId: $senderUserId,
				idList: $idList,
				valueList: $dataList,
				insertResult: $insertResult,
			);

			if (!is_null($afterInsertResult)) {
				$this->logger->warning(
					'afterInsertResult -> value:{value} -> [{errorCode}]: {errorMsg}',
					[
						'value' => $afterInsertResult->value,
						'errorCode' => $afterInsertResult->errorCode,
						'errorMsg' => $afterInsertResult->errorMsg,
					],
				);
				if ($afterInsertResult->isError) {
					$this->db->rollBack();
				} else {
					$this->db->commit();
				}
				return $afterInsertResult;
			}

			$this->db->commit();
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'insert -> Exception[{exception}]',
				[
					'exception' => $e,
				],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
		}

		$this->logger->debug(
			'{dataTypeName} inserted -> {idList}',
			[
				'dataTypeName' => $this->dataTypeName,
				'idList' => $idList,
			],
		);

		return $this->targetRepo->selectList(
			idList: $idList,
		);
	}

	/**
	 * @return RetValueOrError<mixed>
	 */
	protected function beforeInsert(
		UuidInterface $parentId,
		string $ownerUserId,
		/** @param array<UuidInterface> $idList */
		array $idList,
		/** @param array<T> $valueList */
		array $valueList,
	): ?RetValueOrError {
		return null;
	}

	/**
	 * @return RetValueOrError<mixed>
	 */
	protected function afterInsert(
		UuidInterface $parentId,
		string $ownerUserId,
		/** @param array<UuidInterface> $idList */
		array $idList,
		/** @param array<T> $valueList */
		array $valueList,
		RetValueOrError $insertResult,
	): ?RetValueOrError {
		return null;
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			'delete{dataTypeName} senderUserId: {senderUserId}, id: {id}',
			[
				'dataTypeName' => $this->dataTypeName,
				'senderUserId' => $senderUserId,
				'id' => $id,
			],
		);

		$senderPrivilegeCheckResult = $this->checkPrivilegeToWrite(
			id: $id,
			repo: $this->targetRepo,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		return $this->targetRepo->deleteOne(
			id: $id,
		);
	}

	/**
	 * @return RetValueOrError<T>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $id,
	): RetValueOrError {
		$this->logger->debug(
			'getOne{dataTypeName} senderUserId: {senderUserId}, id: {id}',
			[
				'dataTypeName' => $this->dataTypeName,
				'senderUserId' => $senderUserId,
				'id' => $id,
			],
		);

		$senderPrivilegeCheckResult = $this->checkPrivilegeToWrite(
			id: $id,
			repo: $this->targetRepo,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		return $this->targetRepo->selectOne(
			id: $id,
		);
	}

	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function getPage(
		string $senderUserId,
		UuidInterface $parentId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'getPage{dataTypeName} senderUserId: {senderUserId}, parentId: {parentId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'dataTypeName' => $this->dataTypeName,
				'senderUserId' => $senderUserId,
				'parentId' => $parentId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$senderPrivilegeCheckResult = $this->checkPrivilegeToRead(
			id: $parentId,
			repo: $this->parentRepo,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		return $this->targetRepo->selectPage(
			parentId: $parentId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);
	}

	/**
	 * @return RetValueOrError<T>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $stationsId,
		/** @param T $data */
		object $data,
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

		$senderPrivilegeCheckResult = $this->checkPrivilegeToWrite(
			id: $stationsId,
			repo: $this->targetRepo,
			senderUserId: $senderUserId,
		);
		if ($senderPrivilegeCheckResult->isError) {
			return $senderPrivilegeCheckResult;
		}

		$kvpArray = Utils::getArrayForUpdateSource(
			$this->keys,
			$requestBody,
			$data->getData(),
		);
		$updateResult = $this->targetRepo->update(
			id: $stationsId,
			props: $kvpArray,
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

		return $this->targetRepo->selectOne(
			id: $stationsId,
		);
	}

}
