<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\Color8bit;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for Color8bit (component schema, cloned from
 * ProjectStationLocationLonlatDriftTest — P3.5 shared-infra pre-land).
 * Per §3 of CONTRIBUTING-P3.md: one drift test per #[OA\Schema]-bearing
 * model. Asserts property NAMES + required only (never format/example),
 * so the deliberate legacy required-list quirk (red_8bit/green_8bit/
 * blue_8bit vs properties red/green/blue) is locked in by OAS_REQUIRED.
 */
class Color8bitDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(Color8bit::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'Color8bit must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(Color8bit::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal Color8bit::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(Color8bit::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'Color8bit must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(Color8bit::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal Color8bit::OAS_REQUIRED',
		);
	}
}
