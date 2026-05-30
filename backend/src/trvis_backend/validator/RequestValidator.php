<?php

namespace dev_t0r\trvis_backend\validator;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;

final class RequestValidator
{
	/** @property array<ValidationRuleBase> $ruleList */
	private readonly array $ruleList;
	public function __construct(
		/** @param array<ValidationRuleBase> $rules */
		ValidationRuleBase ...$rules,
	) {
		$this->ruleList = $rules;
	}

	public function validate(
		array|object|null &$d,
		bool $checkRequired = true,
		bool $allowNestedArray = true,
		string|int|null $index = null,
	): RetValueOrError {
		if (is_null($d)) {
			return RetValueOrError::withBadReq('Request body is empty');
		}

		if (is_array($d) && array_is_list($d)) {
			// M5: an empty list passes the C1 max-count gate and the zero-iteration
			// foreach, yielding a misleading 201 that created nothing. Reject it.
			if (count($d) < 1) {
				return RetValueOrError::withBadReq('Request must contain at least one item');
			}

			if (!$allowNestedArray) {
				return RetValueOrError::withBadReq('Nested array is not allowed');
			}

			// C1: reject oversized bulk payloads BEFORE decoding/validating every
			// element. The per-element foreach below (and downstream array_map model
			// construction) is O(n); without this gate an attacker can force ~1e5
			// validations within post_max_size and OOM the worker. The service-layer
			// BULK_INSERT_MAX_COUNT check is kept as defence-in-depth.
			if (count($d) > Constants::BULK_INSERT_MAX_COUNT) {
				return RetValueOrError::withBadReq(
					'Too many items in bulk request (max ' . Constants::BULK_INSERT_MAX_COUNT . ')',
				);
			}

			foreach ($d as $i => $item) {
				$validateResult = $this->validate(
					d: $item,
					checkRequired: $checkRequired,
					allowNestedArray: false,
					index: $i,
				);
				if ($validateResult->isError) {
					return $validateResult;
				}
				// $item is a foreach copy; the rules above mutate it by reference
				// (e.g. normalizing color_8bit / lonlat arrays into value objects).
				// Without writing the copy back, those conversions are lost and the
				// downstream model carries the raw array — surfacing as a NOT NULL /
				// type error at insert time. Propagate the normalized element.
				$d[$i] = $item;
			}
			return RetValueOrError::withValue(null);
		}

		$index ??= 0;
		// object or kvp array
		$isKvpArray = is_array($d);
		foreach ($this->ruleList as $rule) {
			$validateResult = $rule->validate(
				d: $d,
				index: $index,
				isKvpArray: $isKvpArray,
				checkRequired: $checkRequired,
			);

			if ($validateResult->isError) {
				return $validateResult;
			}
		}

		return RetValueOrError::withValue(null);
	}

	public static function getNameValidationRule(): StringValidationRule
	{
		return new StringValidationRule(
			key: 'name',
			minLength: Constants::NAME_MIN_LENGTH,
			maxLength: Constants::NAME_MAX_LENGTH,
			isRequired: true,
			isNullable: false,
		);
	}
	public static function getDescriptionValidationRule(): StringValidationRule
	{
		return new StringValidationRule(
			key: 'description',
			minLength: Constants::DESCRIPTION_MIN_LENGTH,
			maxLength: Constants::DESCRIPTION_MAX_LENGTH,
			isRequired: true,
			isNullable: false,
		);
	}
}
