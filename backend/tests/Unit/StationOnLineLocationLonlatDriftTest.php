<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for StationOnLineLocationLonlat (per-entity, cloned from
 * ProjectStationLocationLonlatDriftTest — P3.4 fan-out template). Asserts the
 * #[OA\Schema] attribute that drives swagger-php stays in lock-step with the
 * OAS_PROPERTIES / OAS_REQUIRED consts that drive the runtime model. Only
 * property NAMES + required are checked (never format or example values).
 */
class StationOnLineLocationLonlatDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(StationOnLineLocationLonlat::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'StationOnLineLocationLonlat must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(StationOnLineLocationLonlat::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal StationOnLineLocationLonlat::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(StationOnLineLocationLonlat::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(
			1,
			$schemaAttrs,
			'StationOnLineLocationLonlat must carry exactly one #[OA\\Schema]',
		);
		$schema = $schemaAttrs[0]->newInstance();
		// swagger-php leaves omitted args as a non-array UNDEFINED sentinel.
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(StationOnLineLocationLonlat::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal StationOnLineLocationLonlat::OAS_REQUIRED',
		);
	}
}
