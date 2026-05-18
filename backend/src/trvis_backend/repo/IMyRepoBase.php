<?php

namespace dev_t0r\trvis_backend\repo;
use dev_t0r\trvis_backend\RetValueOrError;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T
 */
interface IMyRepoBase extends IMyRepoSelectPrivilegeType
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
	 * (親テーブルが存在しない場合はこのメソッドを使用できないので注意)
	 * @return RetValueOrError<number>
	 */
	public function selectPageTotalCount(
		UuidInterface $parentId,
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
	 * 指定のIDから、Tableに存在しない/指定のWorkGroupに属さない (または消去された) IDを取得する
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function nonExistIdCheck(
		/** @param array<UuidInterface> $idList */
		array $idList,
		?UuidInterface $workGroupsId,
	): RetValueOrError;
}
