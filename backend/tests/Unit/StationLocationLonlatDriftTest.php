<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\StationLocationLonlat;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for StationLocationLonlat (nested schema, cloned from
 * ProjectStationLocationLonlatDriftTest — P3.5 shared-infra pre-land).
 * Per §3 of CONTRIBUTING-P3.md: one drift test per #[OA\Schema]-bearing
 * model, not per entity. Asserts property NAMES + required only (never
 * format/example values).
 */
class StationLocationLonlatDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(StationLocationLonlat::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'StationLocationLonlat must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(StationLocationLonlat::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal StationLocationLonlat::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(StationLocationLonlat::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'StationLocationLonlat must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(StationLocationLonlat::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal StationLocationLonlat::OAS_REQUIRED',
		);
	}
}
