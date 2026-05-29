<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use JsonSerializable;
use Ramsey\Uuid\UuidInterface;

/**
 * Id-tagged wrapper used only by the Dump aggregator (WorksRepo /
 * TrainsRepo emit `DataWithId<TRViSJson*>` so DumpService can re-key the
 * flat result rows by parent). Faithful 1:1 port of the legacy model
 * (declare(strict_types=1) added). Not an OAS schema — carries no
 * #[OA\Schema] and has no drift test.
 *
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
