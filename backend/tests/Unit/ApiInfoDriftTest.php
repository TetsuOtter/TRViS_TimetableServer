<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\model\ApiInfo;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard: the #[OA\*] attributes that drive swagger-php must stay in
 * lock-step with the OAS_PROPERTIES / OAS_REQUIRED consts that drive the
 * runtime model (getOpenApiSchema/setData/getData). If they diverge, the
 * generated OpenAPI no longer describes what the server actually accepts.
 *
 * Phase 1 covers ApiInfo; this becomes the per-model template fanned out
 * in Phase 3 (one drift assertion per entity).
 */
class ApiInfoDriftTest extends TestCase
{
	private static function constArray(string $cls, string $name): array
	{
		return (array)(new ReflectionClass($cls))->getConstant($name);
	}

	public function testSchemaPropertyAttributesMatchOasPropertiesConst(): void
	{
		$rc = new ReflectionClass(ApiInfo::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'ApiInfo must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		$attrNames = array_map(
			static fn ($p) => $p->property,
			is_array($schema->properties) ? $schema->properties : [],
		);
		$this->assertSame(
			self::constArray(ApiInfo::class, 'OAS_PROPERTIES'),
			$attrNames,
			'#[OA\\Schema(properties:)] set/order must equal ApiInfo::OAS_PROPERTIES',
		);
	}

	public function testSchemaRequiredMatchesOasRequiredConst(): void
	{
		$rc = new ReflectionClass(ApiInfo::class);
		$schemaAttrs = $rc->getAttributes(Schema::class);
		$this->assertCount(1, $schemaAttrs, 'ApiInfo must carry exactly one #[OA\\Schema]');
		$schema = $schemaAttrs[0]->newInstance();
		// swagger-php leaves omitted args as a non-array UNDEFINED sentinel.
		$required = is_array($schema->required) ? $schema->required : [];
		$this->assertSame(
			self::constArray(ApiInfo::class, 'OAS_REQUIRED'),
			$required,
			'#[OA\\Schema(required:)] must equal ApiInfo::OAS_REQUIRED',
		);
	}
}
