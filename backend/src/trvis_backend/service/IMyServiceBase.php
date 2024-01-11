<?php

namespace dev_t0r\trvis_backend\service;
use dev_t0r\trvis_backend\RetValueOrError;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T
 */
interface IMyServiceBase
{
	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function create(
		UuidInterface $parentId,
		string $senderUserId,
		/** @param array<T> $dataList */
		array $dataList,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<int>
	 */
	public function delete(
		string $senderUserId,
		UuidInterface $id,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<T>
	 */
	public function getOne(
		string $senderUserId,
		UuidInterface $id,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<array<T>>
	 */
	public function getPage(
		string $senderUserId,
		UuidInterface $parentId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError;

	/**
	 * @return RetValueOrError<T>
	 */
	public function update(
		string $senderUserId,
		UuidInterface $id,
		/** @param T $data */
		object $data,
		object|array $requestBody,
	): RetValueOrError;
}
