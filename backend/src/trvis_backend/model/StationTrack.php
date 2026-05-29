<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StationTrack — Station-scoped track entity. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StationTrackDriftTest asserts the two stay in sync.
 */
#[OA\Schema(
	schema: 'StationTrack',
	type: 'object',
	required: ['description', 'name'],
	properties: [
		new OA\Property(
			property: 'station_tracks_id',
			type: 'string',
			format: 'uuid',
			description: 'Station TrackのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'stations_id',
			type: 'string',
			format: 'uuid',
			description: 'StationのID (UUID)',
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
			description: 'Station Trackの説明',
			example: 'Station Trackの説明が入ります',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: 'その番線の名前',
			example: '上2',
		),
		new OA\Property(
			property: 'run_in_limit',
			type: 'integer',
			minimum: 0,
			description: '進入制限のデフォルト値 (km/h)',
			example: 15,
		),
		new OA\Property(
			property: 'run_out_limit',
			type: 'integer',
			minimum: 0,
			description: '進出制限のデフォルト値 (km/h)',
			example: 15,
		),
	],
)]
class StationTrack extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'station_tracks_id',
		'stations_id',
		'created_at',
		'description',
		'name',
		'run_in_limit',
		'run_out_limit',
	];
	protected const OAS_REQUIRED = ['description', 'name'];
}
