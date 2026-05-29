<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Project — privilege root entity. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * ProjectDriftTest asserts the two stay in sync.
 *
 * `privilege_type` enum is the hardcoded string set ["read","write","admin"]
 * — NOT InviteKeyPrivilegeType::class (swagger-php would emit backing ints
 * [0,1,2,3] incl. `none`) nor ::cases() (PHP-fatal: method call in an
 * attribute). The runtime still uses the real enum (repos/service); only the
 * generation-time attribute is hardcoded. Drift tests check names+required
 * only, never enum values, so this is conflict-free.
 */
#[OA\Schema(
	schema: 'Project',
	type: 'object',
	required: ['description', 'name'],
	properties: [
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
			description: 'Projectの説明',
			example: 'Projectの説明が入ります',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: 'Projectの名前',
			example: 'AAA鉄道',
		),
		new OA\Property(
			property: 'privilege_type',
			type: 'string',
			description: '権限の種類',
			readOnly: true,
			example: 'admin',
			enum: ['read', 'write', 'admin'],
		),
	],
)]
class Project extends BaseModel
{
	protected const OAS_PROPERTIES = ['projects_id', 'created_at', 'description', 'name', 'privilege_type'];
	protected const OAS_REQUIRED = ['description', 'name'];
}
