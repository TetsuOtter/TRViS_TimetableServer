<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\TRViSJsonWorkGroup;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for TRViSJsonWorkGroup (per-entity, cloned from WorkDriftTest —
 * the Phase-3 fan-out template). Asserts the #[OA\Schema] attribute that
 * drives swagger-php stays in lock-step with the OAS_PROPERTIES / OAS_REQUIRED
 * consts that drive the runtime model. Only property NAMES + required are
 * checked (never enum/example values).
 */
class TRViSJsonWorkGroupDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(TRViSJsonWorkGroup::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'TRViSJsonWorkGroup must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(TRViSJsonWorkGroup::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal TRViSJsonWorkGroup::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(TRViSJsonWorkGroup::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'TRViSJsonWorkGroup must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		// swagger-php leaves omitted args as a non-array UNDEFINED sentinel.
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(TRViSJsonWorkGroup::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal TRViSJsonWorkGroup::OAS_REQUIRED',
		);
	}
}
