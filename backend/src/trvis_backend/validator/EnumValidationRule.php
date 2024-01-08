<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;
use Enum;

final class EnumValidationRule extends ValidationRuleBase
{
	private readonly bool $isFromStringExists;
	public function __construct(
		private readonly string $key,
		private readonly string $className,
		private readonly bool $isRequired = false,
		private readonly bool $isNullable = false,
	) {
		if (!class_exists($className)) {
			throw new \InvalidArgumentException(
				"Invalid class name: '$className'",
			);
		}
		if (!is_subclass_of($className, \UnitEnum::class)) {
			throw new \InvalidArgumentException(
				"Invalid class name: '$className' (expected: subclass of Enum)",
			);
		}
		$this->isFromStringExists = method_exists($className, 'fromString');
	}

	public function validate(
		array|object &$d,
		int|string $index,
		bool $isKvpArray,
		bool $checkRequired = true,
	): RetValueOrError
	{
		RetValueOrError::class;
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
					"Invalid value for property: '{$this->key}' @[$index] (expected: int/string, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}
		if (!is_int($value) && !is_string($value)) {
			return RetValueOrError::withBadReq(
				"Invalid type for property: '{$this->key}' @[$index] (expected: int/string)",
			);
		}

		try
		{
			if (is_string($value) && $this->isFromStringExists) {
				$value = $this->className::fromString($value);
			} else {
				$value = $this->className::from($value);
			}

			self::setValue($d, $isKvpArray, $this->key, $value);
			return RetValueOrError::withValue(null);
		}
		catch (\Throwable $e)
		{
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (expected: int/string, actual: string) - {$e->getMessage()}",
			);
		}
	}
}
