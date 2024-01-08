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
			if (!$allowNestedArray) {
				return RetValueOrError::withBadReq('Nested array is not allowed');
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

	public static function getNameValidationRule(): StringValidationRule {
		return new StringValidationRule(
			key: 'name',
			minLength: Constants::NAME_MIN_LENGTH,
			maxLength: Constants::NAME_MAX_LENGTH,
			isRequired: true,
			isNullable: false,
		);
	}
	public static function getDescriptionValidationRule(): StringValidationRule {
		return new StringValidationRule(
			key: 'description',
			minLength: Constants::DESCRIPTION_MIN_LENGTH,
			maxLength: Constants::DESCRIPTION_MAX_LENGTH,
			isRequired: true,
			isNullable: false,
		);
	}
}
