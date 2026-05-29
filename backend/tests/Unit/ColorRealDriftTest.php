<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\ColorReal;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for ColorReal (component schema, cloned from
 * ProjectStationLocationLonlatDriftTest — P3.5 shared-infra pre-land).
 * Per §3 of CONTRIBUTING-P3.md: one drift test per #[OA\Schema]-bearing
 * model. Asserts property NAMES + required only (never format/example),
 * so the deliberate legacy required-list quirk (red_real/green_real/
 * blue_real vs properties red/green/blue) is locked in by OAS_REQUIRED.
 */
class ColorRealDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(ColorReal::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'ColorReal must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(ColorReal::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal ColorReal::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(ColorReal::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'ColorReal must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(ColorReal::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal ColorReal::OAS_REQUIRED',
		);
	}
}
