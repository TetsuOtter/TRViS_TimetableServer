<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Color — Project-scoped color entity.
 * (Re-rooted from work-group to Project: `work_groups_id` -> `projects_id`.)
 *
 * Property order:
 * colors_id, projects_id, created_at, description, updated_at,
 * name, color_8bit, color_real
 *
 * required: ['color_8bit', 'description', 'name']
 */
#[OA\Schema(
	schema: 'Color',
	title: 'Color',
	type: 'object',
	required: ['color_8bit', 'description', 'name'],
	properties: [
		new OA\Property(
			property: 'colors_id',
			type: 'string',
			format: 'uuid',
			readOnly: true,
			description: 'ColorのID (UUID)',
		),
		new OA\Property(
			property: 'projects_id',
			type: 'string',
			format: 'uuid',
			readOnly: true,
			description: 'この色が属するProjectのID (UUID)',
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			format: 'date-time',
			readOnly: true,
			description: '作成日時',
		),
		new OA\Property(
			property: 'description',
			type: 'string',
			description: 'WorkGroupの説明',
			example: 'WorkGroupの説明が入ります',
		),
		new OA\Property(
			property: 'updated_at',
			type: 'string',
			format: 'date-time',
			readOnly: true,
			description: '更新日時',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			description: 'Colorの名前 (詳細な説明はdescriptionに書く)',
			example: '赤',
		),
		new OA\Property(
			property: 'color_8bit',
			ref: '#/components/schemas/Color8bit',
		),
		new OA\Property(
			property: 'color_real',
			ref: '#/components/schemas/ColorReal',
		),
	],
)]
class Color extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'colors_id',
		'projects_id',
		'created_at',
		'description',
		'updated_at',
		'name',
		'color_8bit',
		'color_real',
	];
	protected const OAS_REQUIRED = ['color_8bit', 'description', 'name'];
}
