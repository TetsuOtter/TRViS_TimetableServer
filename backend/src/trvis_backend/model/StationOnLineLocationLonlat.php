<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StationOnLineLocationLonlat — nested coordinates schema for StationOnLine.
 * Faithful port of the legacy OpenAPI-Generator MODEL_SCHEMA (no title,
 * required/properties/order/format/example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StationOnLineLocationLonlatDriftTest asserts the two stay in sync.
 *
 * Note: legacy schema uses `$ref: #/components/schemas/StationOnLine_location_lonlat`
 * which swagger-php auto-maps from the schema name 'StationOnLine_location_lonlat'.
 * Description: その路線上での駅の位置 (緯度経度) ※未指定時はProject Stationの値を使用
 */
#[OA\Schema(
	schema: 'StationOnLine_location_lonlat',
	type: 'object',
	required: ['longitude', 'latitude'],
	description: 'その路線上での駅の位置 (緯度経度) ※未指定時はProject Stationの値を使用',
	properties: [
		new OA\Property(
			property: 'longitude',
			type: 'number',
			format: 'double',
			description: '経度',
			example: 139.766944,
		),
		new OA\Property(
			property: 'latitude',
			type: 'number',
			format: 'double',
			description: '緯度',
			example: 35.681111,
		),
	],
)]
class StationOnLineLocationLonlat extends BaseModel
{
	protected const OAS_PROPERTIES = ['longitude', 'latitude'];
	protected const OAS_REQUIRED = ['longitude', 'latitude'];
}
