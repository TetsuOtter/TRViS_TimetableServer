<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * WorkGroup — a乗務員区 belonging to a Project. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * WorkGroupDriftTest asserts the two stay in sync.
 *
 * `privilege_type` enum is the hardcoded string set ["read","write","admin"]
 * (same rationale as Project — see its docblock). It is readOnly here (it is
 * a computed projection of the caller's resolved privilege, never written via
 * the WorkGroup payload).
 *
 * WorkGroup is NOT a privilege root: privilege resolves via
 * WorkGroupsPrivilegesRepo (WG -> projects_id -> projects_privileges, with a
 * legacy work_groups_privileges fallback for NULL-projects_id rows).
 */
#[OA\Schema(
	schema: 'WorkGroup',
	type: 'object',
	required: ['description', 'name'],
	properties: [
		new OA\Property(
			property: 'work_groups_id',
			type: 'string',
			format: 'uuid',
			description: 'WorkGroupのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'projects_id',
			type: 'string',
			format: 'uuid',
			description: '所属するProjectのID (UUID)',
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
			description: 'WorkGroupの説明',
			example: 'WorkGroupの説明が入ります',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: 'WorkGroupの名前',
			example: 'AAA乗務員区',
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
class WorkGroup extends BaseModel
{
	protected const OAS_PROPERTIES = ['work_groups_id', 'projects_id', 'created_at', 'description', 'name', 'privilege_type'];
	protected const OAS_REQUIRED = ['description', 'name'];
}
