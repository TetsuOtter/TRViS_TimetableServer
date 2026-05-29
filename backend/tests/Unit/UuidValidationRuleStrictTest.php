<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * L8 regression: UuidValidationRule must accept only canonical lowercase
 * RFC 4122 / RFC 9562 UUIDs and reject every non-canonical form that
 * Ramsey\Uuid\Uuid::isValid() used to accept.
 *
 * The NIL UUID (00000000-0000-0000-0000-000000000000) is the load-bearing
 * case: Constants::getUuidNull() is a sentinel value used internally; no
 * inbound payload should be allowed to spoof it.
 */
class UuidValidationRuleStrictTest extends TestCase
{
	private function validate(mixed $value): bool
	{
		$rule = new UuidValidationRule(key: 'id', isRequired: true, isNullable: false);
		$data = ['id' => $value];
		$result = $rule->validate($data, 0, isKvpArray: true);
		return !$result->isError;
	}

	// ── Positive (accepted) ──────────────────────────────────────────────────

	public function testCanonicalLowercaseUuidV7IsAccepted(): void
	{
		// uuid7 is what the system generates internally.
		$this->assertTrue(
			$this->validate('01960000-0000-7000-8000-000000000001'),
			'canonical lowercase uuid7 must be accepted',
		);
	}

	public function testCanonicalLowercaseUuidV4IsAccepted(): void
	{
		// v4 is the representative legacy / cross-system format.
		$this->assertTrue(
			$this->validate('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
			'canonical lowercase uuid4 must be accepted',
		);
	}

	// ── Negative (rejected → 400) ────────────────────────────────────────────

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function rejectedForms(): array
	{
		return [
			// Security-critical: NIL is Constants::getUuidNull() — a sentinel
			// that must never be accepted as an inbound caller-supplied value.
			'NIL UUID'              => ['00000000-0000-0000-0000-000000000000'],

			// Non-canonical text forms accepted by Ramsey::isValid() but not by us.
			'UPPERCASE'             => ['F47AC10B-58CC-4372-A567-0E02B2C3D479'],
			'brace form'            => ['{f47ac10b-58cc-4372-a567-0e02b2c3d479}'],
			'urn form'              => ['urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479'],

			// Structurally malformed.
			'32-char no-dash'       => ['f47ac10b58cc4372a5670e02b2c3d479'],
			'35-char one-dash-missing' => ['f47ac10b-58cc4372-a567-0e02b2c3d479'],
			'whitespace-padded'     => [' f47ac10b-58cc-4372-a567-0e02b2c3d479'],
		];
	}

	#[DataProvider('rejectedForms')]
	public function testRejectedFormReturns400(mixed $input): void
	{
		$rule = new UuidValidationRule(key: 'id', isRequired: true, isNullable: false);
		$data = ['id' => $input];
		$result = $rule->validate($data, 0, isKvpArray: true);

		$this->assertTrue($result->isError, "input [{$input}] must be rejected");
		$this->assertSame(
			Constants::HTTP_BAD_REQUEST,
			$result->statusCode,
			"rejected input [{$input}] must produce 400",
		);
	}
}
