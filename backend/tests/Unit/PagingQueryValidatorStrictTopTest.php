<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * L8 regression: PagingQueryValidator must reject non-canonical UUID forms
 * when sent as the `?top=` query parameter, just as UuidValidationRule does
 * for body properties.
 *
 * The NIL UUID (00000000-0000-0000-0000-000000000000) is the load-bearing
 * case: Constants::getUuidNull() is a sentinel; no inbound cursor value
 * should be allowed to spoof it.
 */
class PagingQueryValidatorStrictTopTest extends TestCase
{
	private function logger(): AbstractLogger
	{
		return new class extends AbstractLogger {
			public function log($level, $message, array $context = []): void
            {
            }
		};
	}

	private function validate(string $top): PagingQueryValidator
	{
		$request = (new ServerRequestFactory())
			->createServerRequest('GET', '/api/v1/work_groups')
			->withQueryParams(['top' => $top]);

		return PagingQueryValidator::withRequest($request, $this->logger());
	}

	// ── Positive (accepted) ──────────────────────────────────────────────────

	public function testCanonicalLowercaseUuidV7IsAccepted(): void
	{
		$result = $this->validate('01960000-0000-7000-8000-000000000001');
		$this->assertFalse($result->isError, 'canonical lowercase uuid7 must be accepted');
	}

	public function testCanonicalLowercaseUuidV4IsAccepted(): void
	{
		$result = $this->validate('f47ac10b-58cc-4372-a567-0e02b2c3d479');
		$this->assertFalse($result->isError, 'canonical lowercase uuid4 must be accepted');
	}

	// ── Negative (rejected → 400) ────────────────────────────────────────────

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function rejectedForms(): array
	{
		return [
			// Security-critical: NIL is Constants::getUuidNull() — a sentinel
			// that must never be accepted as an inbound caller-supplied cursor.
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
	public function testRejectedTopFormReturns400(string $top): void
	{
		$result = $this->validate($top);

		$this->assertTrue($result->isError, "top=[{$top}] must be rejected");
		$this->assertSame(
			Constants::HTTP_BAD_REQUEST,
			$result->reqError->statusCode,
			"rejected top=[{$top}] must produce 400",
		);
	}
}
