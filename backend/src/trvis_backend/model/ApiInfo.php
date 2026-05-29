<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * ApiInfo — server name + API version. No required props (both readOnly).
 *
 * Properties are declared on the #[OA\Schema] attribute (not as PHP
 * properties) so they don't collide with BaseModel's __get/__set magic.
 * OAS_PROPERTIES / OAS_REQUIRED drive the runtime model; the attribute
 * drives swagger-php. ApiInfoDriftTest asserts the two stay in sync.
 */
#[OA\Schema(
	schema: 'ApiInfo',
	type: 'object',
	properties: [
		new OA\Property(
			property: 'server_name',
			type: 'string',
			description: 'サーバの名前',
			readOnly: true,
		),
		new OA\Property(
			property: 'version',
			type: 'string',
			description: 'APIのバージョン',
			readOnly: true,
		),
	],
)]
class ApiInfo extends BaseModel
{
	protected const OAS_PROPERTIES = ['server_name', 'version'];
	protected const OAS_REQUIRED = [];
}
