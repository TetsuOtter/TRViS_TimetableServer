<?php

namespace dev_t0r\trvis_backend\model;

use JsonSerializable;
use Ramsey\Uuid\UuidInterface;

/**
 * @template T of JsonSerializable
 */
final class DataWithId implements JsonSerializable
{
	public function __construct(
		public readonly UuidInterface $id,
		/** @param T $data */
		public readonly mixed $data,
	) {
	}

	public function jsonSerialize(): mixed
	{
		if ($this->data instanceof JsonSerializable) {
			return $this->data->jsonSerialize();
		} else {
			return $this->data;
		}
	}
}
