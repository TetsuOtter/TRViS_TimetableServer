<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;

final class StringValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly ?int $minLength = null,
		private readonly ?int $maxLength = null,
		private readonly ?string $pattern = null,
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

		$hasMinLenLimit = !is_null($this->minLength);
		$hasMaxLenLimit = !is_null($this->maxLength);
		if ($hasMinLenLimit || $hasMaxLenLimit) {
			$strLen = mb_strlen($value);
			if ($hasMinLenLimit && $strLen < $this->minLength) {
				return RetValueOrError::withBadReq(
					"Invalid length for property: '{$this->key}' @[$index] (expected: >= {$this->minLength}, actual: $strLen)",
				);
			}

			if ($hasMaxLenLimit && $this->maxLength < $strLen) {
				return RetValueOrError::withBadReq(
					"Invalid length for property: '{$this->key}' @[$index] (expected: <= {$this->maxLength}, actual: $strLen)",
				);
			}
		}

		if (!is_null($this->pattern) && !preg_match($this->pattern, $value)) {
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (pattern: {$this->pattern}, actual: $value)",
			);
		}

		return RetValueOrError::withValue(null);
	}
}
