<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Line — belongs directly to a Project (privilege root via projects_id).
 * Faithful port of the legacy OpenAPI-Generator MODEL_SCHEMA:
 * title/required/properties/order/format/example all preserved 1:1.
 *
 * DB table is `project_lines` (reserved-word avoidance); PK is
 * `project_lines_id`. The OpenAPI schema and the PHP model use `lines_id` /
 * `Line` — the Repo layer aliases the column on SELECT.
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * LineDriftTest asserts the two stay in sync.
 */
#[OA\Schema(
	schema: 'Line',
	type: 'object',
	required: ['description', 'name'],
	properties: [
		new OA\Property(
			property: 'lines_id',
			type: 'string',
			format: 'uuid',
			description: 'LineのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'projects_id',
			type: 'string',
			format: 'uuid',
			description: 'ProjectのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			format: 'date-time',
			description: '作成日時',
			readOnly: true,
		),
		new OA\Property(
			property: 'description',
			type: 'string',
			description: 'Lineの説明',
			example: 'Lineの説明が入ります',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: '路線名',
			example: '東海道本線',
		),
	],
)]
class Line extends BaseModel
{
	protected const OAS_PROPERTIES = ['lines_id', 'projects_id', 'created_at', 'description', 'name'];
	protected const OAS_REQUIRED = ['description', 'name'];
}
