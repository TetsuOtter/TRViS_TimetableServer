<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;

final class LonLatValidationRule extends ValidationRuleBase
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
					"Invalid value for property: '{$this->key}' @[$index] (expected: object with 'longitude' and 'latitude' properties, actual: null)",
				);
			}
			return RetValueOrError::withValue(null);
		}

		$lon = Utils::getValue($value, 'longitude');
		$lat = Utils::getValue($value, 'latitude');
		if (is_null($lon) || is_null($lat)) {
			return RetValueOrError::withBadReq(
				"Invalid value for property: '{$this->key}' @[$index] (expected: object with 'longitude' and 'latitude' properties)",
			);
		}
		if (!is_float($lon) && !is_int($lon)) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.longitude' @[$index] (not a float/int)",
			);
		}
		if (is_nan($lon)) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.longitude' @[$index] (NaN)",
			);
		}
		if ($lon < -180.0 || 180.0 < $lon) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.longitude' @[$index] (out of range)",
			);
		}

		if (!is_float($lat) && !is_int($lat)) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.latitude' @[$index] (not a float/int)",
			);
		}
		if (is_nan($lat)) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.latitude' @[$index] (NaN)",
			);
		}
		if ($lat < -90.0 || 90.0 < $lat) {
			return RetValueOrError::withBadReq(
				"Invalid value for '{$this->key}.latitude' @[$index] (out of range)",
			);
		}

		$lonlatObj = new StationLocationLonlat;
		$lonlatObj->setData([
			'longitude' => $lon,
			'latitude' => $lat,
		]);
		self::setValue($d, $isKvpArray, $this->key, $lonlatObj);

		return RetValueOrError::withValue(null);
	}
}
