<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;

final class Color8bitValidationRule extends ValidationRuleBase
{
	public function __construct(
		private readonly string $key,
		private readonly bool $isRequired = false,
		private readonly bool $isNullable = false,
	) {}

	const COLOR_MIN = 0;
	const COLOR_MAX = 255;

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
					"Invalid value for property: '{$this->key}' @[$index] (actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}

		$red = Utils::getValue($value, 'red');
		$green = Utils::getValue($value, 'green');
		$blue = Utils::getValue($value, 'blue');
		if (is_null($red) || is_null($green) || is_null($blue)
			|| !is_int($red) || !is_int($green) || !is_int($blue)) {
			return RetValueOrError::withBadReq(
				"Invalid type for property: '{$this->key}' @[$index] (each of 'red', 'green', 'blue' must be an integer)",
			);
		}
		if ($red < self::COLOR_MIN || self::COLOR_MAX < $red
			|| $green < self::COLOR_MIN || self::COLOR_MAX < $green
			|| $blue < self::COLOR_MIN || self::COLOR_MAX < $blue) {
			$min = self::COLOR_MIN;
			$max = self::COLOR_MAX;
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (each of 'red', 'green', 'blue' must be in range [$min, $max])",
			);
		}

		$color = new Color8bit();
		$color->setData([
			'red' => $red,
			'green' => $green,
			'blue' => $blue,
		]);
		self::setValue($d, $isKvpArray, $this->key, $color);

		return RetValueOrError::withValue(null);
	}
}
