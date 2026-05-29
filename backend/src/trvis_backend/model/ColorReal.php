<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * ColorReal — RGB color (real 0.0–1.0 per channel) component schema.
 * Faithful port of the legacy OpenAPI-Generator MODEL_SCHEMA / api_defs
 * `#/components/schemas/ColorReal` (title/required/properties/format/min/
 * max/example preserved 1:1). Instantiated by ColorRealValidationRule.
 *
 * Note the legacy required-list quirk: it lists `red_real/green_real/
 * blue_real` while the properties are `red/green/blue` — reproduced
 * verbatim for byte-parity with the legacy spec.
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties)
 * so they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES
 * / OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * ColorRealDriftTest asserts the two stay in sync (names + required only).
 */
#[OA\Schema(
	schema: 'ColorReal',
	title: 'ColorReal',
	type: 'object',
	required: ['red_real', 'green_real', 'blue_real'],
	properties: [
		new OA\Property(
			property: 'red',
			type: 'number',
			format: 'double',
			minimum: 0,
			maximum: 1,
			description: '色の赤色成分 (小数)',
			example: 0.5,
		),
		new OA\Property(
			property: 'green',
			type: 'number',
			format: 'double',
			minimum: 0,
			maximum: 1,
			description: '色の緑色成分 (小数)',
			example: 0.5,
		),
		new OA\Property(
			property: 'blue',
			type: 'number',
			format: 'double',
			minimum: 0,
			maximum: 1,
			description: '色の青色成分 (小数)',
			example: 0.5,
		),
	],
)]
class ColorReal extends BaseModel
{
	protected const OAS_PROPERTIES = ['red', 'green', 'blue'];
	protected const OAS_REQUIRED = ['red_real', 'green_real', 'blue_real'];
}
