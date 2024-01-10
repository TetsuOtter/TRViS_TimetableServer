<?php

namespace dev_t0r\trvis_backend\repo;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\RetValueOrError;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T
 */
interface IMyRepoBase
{
	/**
	 * @return RetValueOrError<T>
	 */
	public function selectOne(
		UuidInterface $id,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectWorkGroupsId(
		UuidInterface $id,
	): RetValueOrError;

	/**
	 * (親テーブルが存在しない場合はこのメソッドを使用できないので注意)
	 * @return RetValueOrError<array<T>>
	 */
	public function selectPage(
		UuidInterface $parentId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $idList */
		array $idList,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<int>
	 */
	public function insertList(
		UuidInterface $parentId,
		string $ownerUserId,
		/** @param array<UuidInterface> $idList */
		array $idList,
		/** @param array<T> $valueList */
		array $valueList,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<int>
	 */
	public function update(
		UuidInterface $id,
		array $props,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<int>
	 */
	public function deleteOne(
		UuidInterface $id,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<int>
	 */
	public function deleteByWorkGroupsId(
		UuidInterface $workGroupsId,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError;
}
