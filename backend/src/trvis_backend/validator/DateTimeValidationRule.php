<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;

final class DateTimeValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly bool $isDateOnly = false,
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
					"Invalid value for property: '{$this->key}' @[$index] (expected: string, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}
		if (!is_string($value)) {
			return RetValueOrError::withBadReq(
				"Invalid type for property: '{$this->key}' @[$index] (expected: string)",
			);
		}

		if ($this->isDateOnly) {
			$value = Utils::fromJsonDateOnlyStrToDateTime($value);
		} else {
			$value = Utils::fromJsonDateStrToDateTime($value);
		}

		if (is_null($value)) {
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (cannot parse string to DateTime)",
			);
		}

		self::setValue($d, $isKvpArray, $this->key, $value);

		return RetValueOrError::withValue(null);
	}
}
