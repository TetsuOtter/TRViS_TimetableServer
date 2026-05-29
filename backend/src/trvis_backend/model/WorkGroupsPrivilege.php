<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * WorkGroupsPrivilege — a user's privilege row on a WorkGroup. Faithful port
 * of the legacy OpenAPI-Generator MODEL_SCHEMA (title/required/properties/
 * order/format/example preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * WorkGroupsPrivilegeDriftTest asserts the two stay in sync.
 *
 * `privilege_type` enum is the hardcoded string set ["read","write","admin"]
 * (same rationale as Project — see its docblock). Unlike WorkGroup's, this
 * one is NOT readOnly (it is the request payload of updateWorkGroupPrivilege).
 */
#[OA\Schema(
	schema: 'WorkGroupsPrivilege',
	type: 'object',
	required: ['privilege_type'],
	properties: [
		new OA\Property(
			property: 'uid',
			type: 'string',
			description: 'UserID',
			readOnly: true,
		),
		new OA\Property(
			property: 'work_groups_id',
			type: 'string',
			format: 'uuid',
			description: 'WorkGroupのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'invite_keys_id',
			type: 'string',
			format: 'uuid',
			description: 'InviteKeyのID (UUID)',
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
			property: 'updated_at',
			type: 'string',
			format: 'date-time',
			description: '更新日時',
			readOnly: true,
		),
		new OA\Property(
			property: 'privilege_type',
			type: 'string',
			description: '権限の種類',
			example: 'admin',
			enum: ['read', 'write', 'admin'],
		),
	],
)]
class WorkGroupsPrivilege extends BaseModel
{
	protected const OAS_PROPERTIES = ['uid', 'work_groups_id', 'invite_keys_id', 'created_at', 'updated_at', 'privilege_type'];
	protected const OAS_REQUIRED = ['privilege_type'];
}
