<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StationLocationLonlat — nested coordinates schema for Station.
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StationLocationLonlatDriftTest asserts the two stay in sync.
 *
 * Note: schema name 'Station_location_lonlat' is referenced from Station's
 * `location_lonlat` property ($ref: #/components/schemas/Station_location_lonlat).
 */
#[OA\Schema(
	schema: 'Station_location_lonlat',
	type: 'object',
	required: ['longitude', 'latitude'],
	description: '駅の位置 (緯度経度)',
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
class StationLocationLonlat extends BaseModel
{
	protected const OAS_PROPERTIES = ['longitude', 'latitude'];
	protected const OAS_REQUIRED = ['longitude', 'latitude'];
}
