<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StationOnLine — Line-scoped station entity. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StationOnLineDriftTest asserts the two stay in sync.
 *
 * Note: `description` column exists in the DB but is NOT in the API schema
 * (legacy design decision; INSERT omits it, DB default is '').
 */
#[OA\Schema(
	schema: 'StationOnLine',
	title: 'StationOnLine',
	type: 'object',
	required: ['lines_id', 'location_m', 'project_stations_id'],
	properties: [
		new OA\Property(
			property: 'stations_on_line_id',
			type: 'string',
			format: 'uuid',
			description: 'StationOnLineのID (UUID)',
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
			property: 'project_stations_id',
			type: 'string',
			format: 'uuid',
			description: '紐づくProject StationのID (UUID)',
		),
		new OA\Property(
			property: 'project_stations_name',
			type: 'string',
			description: 'project_stations_id が指す駅の名前 (読み取り専用・表示用)。'
				. '駅が論理削除済みの場合も名前を返す (project_stations_is_deleted で判別)。',
			readOnly: true,
			nullable: true,
		),
		new OA\Property(
			property: 'project_stations_is_deleted',
			type: 'boolean',
			description: 'project_stations_id が指す駅が論理削除済みかどうか (tombstone表示用)',
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
			property: 'location_m',
			type: 'number',
			format: 'double',
			description: 'その路線上での駅の位置 (m)',
			example: 12345.6,
		),
		new OA\Property(
			property: 'location_lonlat',
			ref: '#/components/schemas/StationOnLine_location_lonlat',
		),
		new OA\Property(
			property: 'track_hidden_by_default',
			type: 'boolean',
			description: 'デフォルトで番線を非表示にするかどうか',
			example: false,
		),
	],
)]
class StationOnLine extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'stations_on_line_id',
		'projects_id',
		'lines_id',
		'project_stations_id',
		'project_stations_name',
		'project_stations_is_deleted',
		'created_at',
		'location_m',
		'location_lonlat',
		'track_hidden_by_default',
	];
	protected const OAS_REQUIRED = ['lines_id', 'location_m', 'project_stations_id'];
}
