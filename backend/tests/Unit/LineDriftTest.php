<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\Line;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard for Line (per-entity, cloned from ProjectDriftTest — the
 * Phase-3 fan-out template). Asserts the #[OA\Schema] attribute that
 * drives swagger-php stays in lock-step with the OAS_PROPERTIES /
 * OAS_REQUIRED consts that drive the runtime model. Only property NAMES +
 * required are checked (never enum values).
 *
 * Line has no privilege bridge model, so there is exactly ONE drift test
 * for this entity.
 */
class LineDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(Line::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'Line must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(Line::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal Line::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(Line::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'Line must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		// swagger-php leaves omitted args as a non-array UNDEFINED sentinel.
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(Line::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal Line::OAS_REQUIRED',
		);
	}
}
