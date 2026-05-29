<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * L1 regression: SQLSTATE 23000 is the integrity-constraint *class*; the
 * pre-fix code blanket-mapped any 23000 to 409 "${Entity} already exists",
 * so an FK-pointing-at-missing-parent (driver 1452) or a NOT-NULL violation
 * (1048/1364) was reported to clients as a duplicate-key conflict. Each
 * dataset below proves that Utils::mapPdoIntegrityError dispatches on the
 * MySQL driver code rather than on SQLSTATE alone — if the dispatch were
 * removed, every "non-1062 with sqlState 23000" row would fail at once.
 */
class UtilsMapPdoIntegrityErrorTest extends TestCase
{
	/**
	 * @return array<string,array{0:?string,1:?int,2:int,3:string}>
	 *   [sqlState, driverCode, expectedStatus, expectedMessageNeedle]
	 */
	public static function cases(): array
	{
		return [
			// Caller-correctable 23000 sub-causes -> precise 4xx.
			'1062 duplicate UNIQUE'         => ['23000', 1062, Constants::HTTP_CONFLICT,    'Project already exists'],
			'1586 duplicate partitioned'    => ['23000', 1586, Constants::HTTP_CONFLICT,    'Project already exists'],
			'1452 FK parent missing'        => ['23000', 1452, Constants::HTTP_BAD_REQUEST, 'referenced parent'],
			'1216 FK add fail'              => ['23000', 1216, Constants::HTTP_BAD_REQUEST, 'referenced parent'],
			'1217 FK drop fail'             => ['23000', 1217, Constants::HTTP_BAD_REQUEST, 'referenced parent'],
			'1048 NOT NULL violation'       => ['23000', 1048, Constants::HTTP_BAD_REQUEST, 'required field'],
			'1364 strict-mode no default'   => ['23000', 1364, Constants::HTTP_BAD_REQUEST, 'required field'],

			// 23000 with a driver code we have not classified -> NOT a caller error.
			// This is the critical anti-regression: do NOT silently 409 just because sqlState matches.
			'23000 unknown driver code'     => ['23000', 9999, Constants::HTTP_INTERNAL_SERVER_ERROR, 'Failed to execute SQL'],
			'23000 with no driver code'     => ['23000', null, Constants::HTTP_INTERNAL_SERVER_ERROR, 'Failed to execute SQL'],

			// Non-integrity SQLSTATE -> 500, regardless of driver code.
			'42S22 column not found'        => ['42S22', 1054, Constants::HTTP_INTERNAL_SERVER_ERROR, 'Failed to execute SQL'],
			'HY000 generic'                 => ['HY000', 2006, Constants::HTTP_INTERNAL_SERVER_ERROR, 'Failed to execute SQL'],
			'null sqlState'                 => [null,     null, Constants::HTTP_INTERNAL_SERVER_ERROR, 'Failed to execute SQL'],
		];
	}

	#[DataProvider('cases')]
	public function testMapping(?string $sqlState, ?int $driverCode, int $expectedStatus, string $expectedNeedle): void
	{
		$result = Utils::mapPdoIntegrityError($sqlState, $driverCode, 'Project');

		$this->assertTrue($result->isError, 'helper must always return an error RetValueOrError');
		$this->assertSame($expectedStatus, $result->statusCode);
		$this->assertStringContainsString($expectedNeedle, $result->errorMsg);
	}

	/**
	 * Entity label is interpolated into the 409 message (and only there);
	 * if a future refactor inlined the label string, this test still pins
	 * that each caller's label reaches the wire untouched.
	 */
	public function testEntityLabelInterpolatedInto409Only(): void
	{
		$conflict = Utils::mapPdoIntegrityError('23000', 1062, 'TimetableRow');
		$this->assertSame(Constants::HTTP_CONFLICT, $conflict->statusCode);
		$this->assertSame('TimetableRow already exists', $conflict->errorMsg);

		// Label must NOT leak into the 400 / 500 message bodies (those are
		// cause-oriented, not entity-oriented).
		$badParent = Utils::mapPdoIntegrityError('23000', 1452, 'TimetableRow');
		$this->assertStringNotContainsString('TimetableRow', $badParent->errorMsg);

		$unknown = Utils::mapPdoIntegrityError('23000', 9999, 'TimetableRow');
		$this->assertStringNotContainsString('TimetableRow', $unknown->errorMsg);
	}
}
