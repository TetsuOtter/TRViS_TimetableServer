<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Station — Project-scoped station entity (the consolidated station model;
 * the former work-group-rooted `stations` table was abolished and the
 * project-rooted `project_stations` renamed to `stations`).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StationDriftTest asserts the two stay in sync.
 *
 * `location_km` / `record_type` are carried over from the old work-group model
 * because the TRViS-JSON dump consumes them (Location_m = location_km*1000,
 * RecordType). They are optional on create/update and default to 0 / normal.
 *
 * Note: `description` column exists in the DB but is NOT in the API schema
 * (legacy design decision; INSERT omits it, DB default is '').
 */
#[OA\Schema(
	schema: 'Station',
	type: 'object',
	required: ['name'],
	properties: [
		new OA\Property(
			property: 'stations_id',
			type: 'string',
			format: 'uuid',
			description: 'Station (Project内共通の駅) のID (UUID)',
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
			property: 'name',
			type: 'string',
			description: '駅名',
			example: '東京',
		),
		new OA\Property(
			property: 'full_name',
			type: 'string',
			description: '駅のフルネーム',
			example: '東京駅',
		),
		new OA\Property(
			property: 'location_km',
			type: 'number',
			format: 'double',
			description: '駅の位置 (起点からのキロ程, km)。dump の Location_m に使用される。',
			example: 12.3,
		),
		new OA\Property(
			property: 'location_lonlat',
			ref: '#/components/schemas/Station_location_lonlat',
		),
		new OA\Property(
			property: 'on_station_detect_radius_m',
			type: 'number',
			format: 'double',
			description: 'その駅にいるかどうかを判定する円の半径 (m)',
			example: 123.45,
		),
		new OA\Property(
			property: 'record_type',
			type: 'string',
			enum: ['normal', 'normal_no_ett', 'info', 'info_ex'],
			description: 'レコード種別 (TRViS-JSON dump の RecordType)',
			example: 'normal',
		),
		new OA\Property(
			property: 'always_show_hh',
			type: 'boolean',
			description: '時刻表示で常に「時」を表示するかどうか',
			example: false,
		),
	],
)]
class Station extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'stations_id',
		'projects_id',
		'created_at',
		'name',
		'full_name',
		'location_km',
		'location_lonlat',
		'on_station_detect_radius_m',
		'record_type',
		'always_show_hh',
	];
	protected const OAS_REQUIRED = ['name'];
}
