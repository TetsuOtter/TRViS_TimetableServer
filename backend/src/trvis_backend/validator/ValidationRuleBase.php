<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\RetValueOrError;

abstract class ValidationRuleBase
{
	public abstract function validate(
		array|object &$d,
		int|string $index,
		bool $isKvpArray,
		bool $checkRequired = true,
	): RetValueOrError;

	protected static function isPropExists(
		array|object $d,
		bool $isKvpArray,
		string $key,
	): mixed {
		return $isKvpArray
			? array_key_exists($key, $d)
			: property_exists($d, $key);
	}

	protected static function getValue(
		array|object $d,
		bool $isKvpArray,
		string $key,
	): mixed {
		return $isKvpArray
			? $d[$key]
			: $d->{$key};
	}

	protected static function setValue(
		array|object &$d,
		bool $isKvpArray,
		string $key,
		mixed $value,
	): void {
		if ($isKvpArray) {
			$d[$key] = $value;
		} else {
			$d->{$key} = $value;
		}
	}
}
