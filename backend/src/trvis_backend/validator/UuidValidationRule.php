<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;
use Ramsey\Uuid\Uuid;

final class UuidValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly bool $isRequired = false,
		private readonly bool $isNullable = false,
	) {}

	public function validate(
		array|object &$d,
		int|string $index,
		bool $isKvpArray,
		bool $checkRequired = true,
	): RetValueOrError
	{
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
					"Invalid value for property: '{$this->key}' @[$index] (expected: bool, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}
		if (!Uuid::isValid($value)) {
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (expected: Uuid)",
			);
		}

		return RetValueOrError::withValue(null);
	}
}
