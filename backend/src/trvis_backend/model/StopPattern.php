<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StopPattern — Project-rooted entity (direct via projects_id).
 * Faithful port of the legacy OpenAPI-Generator MODEL_SCHEMA
 * (title/required/properties/order/format/example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StopPatternDriftTest asserts the two stay in sync.
 *
 * DB note: `project_lines_id` (DB) is aliased to `lines_id` (API) in the
 * SELECT and the UPDATE SET clause. `description` column (NOT NULL, DEFAULT '')
 * is intentionally absent from the API schema — INSERT relies on the default.
 *
 * `direction` enum is the hardcoded int array [1, -1] — NOT a PHP enum class
 * (drift tests check names+required only, never enum values, so safe).
 */
#[OA\Schema(
	schema: 'StopPattern',
	type: 'object',
	required: ['lines_id', 'name'],
	properties: [
		new OA\Property(
			property: 'stop_patterns_id',
			type: 'string',
			format: 'uuid',
			description: 'StopPatternのID (UUID)',
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
			property: 'lines_id',
			type: 'string',
			format: 'uuid',
			description: '紐づくLineのID (UUID)',
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			format: 'date-time',
			description: '作成日時',
			readOnly: true,
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: '停車パターンの名前',
			example: '各駅停車',
		),
		new OA\Property(
			property: 'from_project_stations_id',
			type: 'string',
			format: 'uuid',
			description: '始点のProject StationのID (UUID)',
		),
		new OA\Property(
			property: 'to_project_stations_id',
			type: 'string',
			format: 'uuid',
			description: '終点のProject StationのID (UUID)',
		),
		new OA\Property(
			property: 'direction',
			type: 'integer',
			description: '進行方向 (1=下り, -1=上り)',
			example: 1,
			enum: [1, -1],
		),
	],
)]
class StopPattern extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'stop_patterns_id',
		'projects_id',
		'lines_id',
		'created_at',
		'name',
		'from_project_stations_id',
		'to_project_stations_id',
		'direction',
	];
	protected const OAS_REQUIRED = ['lines_id', 'name'];
}
