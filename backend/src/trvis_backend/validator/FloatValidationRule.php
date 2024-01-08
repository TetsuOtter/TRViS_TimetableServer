<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;

final class FloatValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly ?float $minValue = null,
		private readonly ?float $maxValue = null,
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
					"Invalid value for property: '{$this->key}' @[$index] (expected: int/float, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}
		$isFloat = is_float($value);
		if (!is_int($value) && !$isFloat) {
			return RetValueOrError::withBadReq(
				"Invalid type for property: '{$this->key}' @[$index] (expected: int/float)",
			);
		}

		if ($isFloat) {
			if (is_nan($value)) {
				return RetValueOrError::withBadReq(
					"Invalid value for property: '{$this->key}' @[$index] (NaN)",
				);
			}
			if (is_infinite($value)) {
				return RetValueOrError::withBadReq(
					"Invalid value for property: '{$this->key}' @[$index] (infinite)",
				);
			}
		}

		$hasMinValueLimit = !is_null($this->minValue);
		$hasMaxValueLimit = !is_null($this->maxValue);
		if ($hasMinValueLimit || $hasMaxValueLimit) {
			if ($hasMinValueLimit && $value < $this->minValue) {
				return RetValueOrError::withBadReq(
					"Invalid value for property: '{$this->key}' @[$index] (expected: >= {$this->minValue}, actual: $value)",
				);
			}

			if ($hasMaxValueLimit && $this->maxValue < $value) {
				return RetValueOrError::withBadReq(
					"Invalid value for property: '{$this->key}' @[$index] (expected: <= {$this->maxValue}, actual: $value)",
				);
			}
		}

		return RetValueOrError::withValue(null);
	}
}
