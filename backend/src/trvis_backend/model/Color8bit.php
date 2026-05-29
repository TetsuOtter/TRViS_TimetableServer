<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Color8bit — RGB color (8-bit per channel) component schema.
 * Faithful port of the legacy OpenAPI-Generator MODEL_SCHEMA / api_defs
 * `#/components/schemas/Color8bit` (title/required/properties/min/max/
 * example preserved 1:1). Instantiated by Color8bitValidationRule.
 *
 * Note the legacy required-list quirk: it lists `red_8bit/green_8bit/
 * blue_8bit` while the properties are `red/green/blue` — reproduced
 * verbatim for byte-parity with the legacy spec.
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties)
 * so they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES
 * / OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * Color8bitDriftTest asserts the two stay in sync (names + required only).
 */
#[OA\Schema(
	schema: 'Color8bit',
	title: 'Color8bit',
	type: 'object',
	required: ['red_8bit', 'green_8bit', 'blue_8bit'],
	properties: [
		new OA\Property(
			property: 'red',
			type: 'integer',
			minimum: 0,
			maximum: 255,
			description: '色の赤色成分 (8bit)',
			example: 127,
		),
		new OA\Property(
			property: 'green',
			type: 'integer',
			minimum: 0,
			maximum: 255,
			description: '色の緑色成分 (8bit)',
			example: 127,
		),
		new OA\Property(
			property: 'blue',
			type: 'integer',
			minimum: 0,
			maximum: 255,
			description: '色の青色成分 (8bit)',
			example: 127,
		),
	],
)]
class Color8bit extends BaseModel
{
	protected const OAS_PROPERTIES = ['red', 'green', 'blue'];
	protected const OAS_REQUIRED = ['red_8bit', 'green_8bit', 'blue_8bit'];
}
