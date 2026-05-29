<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\WorkGroupsPrivilege;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for WorkGroupsPrivilege (per-entity, cloned from ApiInfoDriftTest
 * — the Phase-3 fan-out template). Asserts the #[OA\Schema] attribute stays in
 * lock-step with OAS_PROPERTIES / OAS_REQUIRED.
 */
class WorkGroupsPrivilegeDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(WorkGroupsPrivilege::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'WorkGroupsPrivilege must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(WorkGroupsPrivilege::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal WorkGroupsPrivilege::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(WorkGroupsPrivilege::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'WorkGroupsPrivilege must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		// swagger-php leaves omitted args as a non-array UNDEFINED sentinel.
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(WorkGroupsPrivilege::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal WorkGroupsPrivilege::OAS_REQUIRED',
		);
	}
}
