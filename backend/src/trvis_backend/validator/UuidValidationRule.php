<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use Ramsey\Uuid\Uuid;

/**
 * Validates a UUID string property in the request body and converts it to a
 * UuidInterface on success. Ported from the legacy UuidValidationRule.
 */
final class UuidValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly bool $isRequired = false,
		private readonly bool $isNullable = false,
	) {
	}

	public function validate(
		array|object &$d,
		int|string $index,
		bool $isKvpArray,
		bool $checkRequired = true,
	): RetValueOrError {
		if (!self::isPropExists($d, $isKvpArray, $this->key)) {
			if ($checkRequired && $this->isRequired) {
				return RetValueOrError::withBadReq(
					"Missing required property: '{$this->key}' @[$index]",
				);
			}
			return RetValueOrError::withValue(null);
		}

		$value = self::getValue($d, $isKvpArray, $this->key);
		if (is_null($value)) {
			if (!$this->isNullable) {
				return RetValueOrError::withBadReq(
					"Invalid value for property: '{$this->key}' @[$index] (expected: uuid, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}
		// L8: use strict validator — canonical lowercase, non-NIL, no brace/urn/uppercase.
		if (!Utils::isStrictUuid($value)) {
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (expected: Uuid)",
			);
		}

		self::setValue($d, $isKvpArray, $this->key, Uuid::fromString($value));

		return RetValueOrError::withValue(null);
	}
}
