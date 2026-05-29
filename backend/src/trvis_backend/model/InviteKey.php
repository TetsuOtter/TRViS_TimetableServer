<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * InviteKey — bearer-capability invite key scoped to a WorkGroup. Faithful
 * port of the legacy OpenAPI-Generator MODEL_SCHEMA (title/required/
 * properties/order/format/example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * InviteKeyDriftTest asserts the two stay in sync.
 *
 * `privilege_type` enum is the hardcoded string set ["read","write","admin"]
 * (same rationale as Project — see its docblock). The `none` value is NOT
 * exposed in the API schema (it is a sentinel, not a valid invite grant).
 * The runtime still uses the real enum (repos/service); only the
 * generation-time attribute is hardcoded. Drift tests check names+required
 * only, never enum values, so this is conflict-free.
 */
#[OA\Schema(
	schema: 'InviteKey',
	type: 'object',
	required: ['description'],
	properties: [
		new OA\Property(
			property: 'invite_keys_id',
			type: 'string',
			format: 'uuid',
			description: 'Invite Key (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'work_groups_id',
			type: 'string',
			format: 'uuid',
			description: '対応するWorkGroupのID (UUID)',
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
			description: '招待キーの説明',
			example: 'グループAの招待キー',
		),
		new OA\Property(
			property: 'valid_from',
			type: 'string',
			format: 'date-time',
			description: 'キーの有効期限 (開始)',
		),
		new OA\Property(
			property: 'expires_at',
			type: 'string',
			format: 'date-time',
			description: 'キーの有効期限 (終了)',
		),
		new OA\Property(
			property: 'use_limit',
			type: 'integer',
			minimum: 1,
			description: 'キーの使用回数の上限',
			example: 15,
		),
		new OA\Property(
			property: 'disabled_at',
			type: 'string',
			format: 'date-time',
			description: 'キーが無効になった日時 (Expireした場合はexpires_atと同じ値)',
			readOnly: true,
		),
		new OA\Property(
			property: 'privilege_type',
			type: 'string',
			description: '招待キーで付与される権限',
			example: 'read',
			enum: ['read', 'write', 'admin'],
		),
	],
)]
class InviteKey extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'invite_keys_id',
		'work_groups_id',
		'created_at',
		'description',
		'valid_from',
		'expires_at',
		'use_limit',
		'disabled_at',
		'privilege_type',
	];
	protected const OAS_REQUIRED = ['description'];
}
