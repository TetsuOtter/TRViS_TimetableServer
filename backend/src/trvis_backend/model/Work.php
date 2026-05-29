<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Work — a 仕業 belonging to a WorkGroup. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * WorkDriftTest asserts the two stay in sync.
 *
 * affix_content and e_train_timetable_content are schema-present but never
 * persisted — the DB stores _file_name variants instead. They always read as
 * null. See WorksRepo::selectWorkOne for TODO comments.
 *
 * affix_content_type / e_train_timetable_content_type use a hardcoded
 * string enum ['text','URI','PNG','PDF','JPG'] — copied 1:1 from the legacy
 * MODEL_SCHEMA. (Using ::cases() would cause a PHP fatal at attribute parse
 * time; see CONTRIBUTING-P3.md §3.)
 */
#[OA\Schema(
	schema: 'Work',
	type: 'object',
	required: ['description', 'name'],
	properties: [
		new OA\Property(
			property: 'works_id',
			type: 'string',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'work_groups_id',
			type: 'string',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			format: 'date-time',
			readOnly: true,
		),
		new OA\Property(
			property: 'description',
			type: 'string',
			example: 'Workの説明が入ります',
		),
		new OA\Property(
			property: 'name',
			type: 'string',
			example: '第NNN仕業',
		),
		new OA\Property(
			property: 'affect_date',
			type: 'string',
			format: 'date',
		),
		new OA\Property(
			property: 'affix_content_type',
			type: 'string',
			enum: ['text', 'URI', 'PNG', 'PDF', 'JPG'],
			example: 'text',
		),
		new OA\Property(
			property: 'affix_content',
			type: 'string',
			example: '行路添付の内容が入ります',
		),
		new OA\Property(
			property: 'remarks',
			type: 'string',
			example: '注意事項が入ります',
		),
		new OA\Property(
			property: 'has_e_train_timetable',
			type: 'boolean',
			example: true,
		),
		new OA\Property(
			property: 'e_train_timetable_content_type',
			type: 'string',
			enum: ['text', 'URI', 'PNG', 'PDF', 'JPG'],
			example: 'text',
		),
		new OA\Property(
			property: 'e_train_timetable_content',
			type: 'string',
			example: 'E電時刻表の内容が入ります',
		),
	],
)]
class Work extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'works_id',
		'work_groups_id',
		'created_at',
		'description',
		'name',
		'affect_date',
		'affix_content_type',
		'affix_content',
		'remarks',
		'has_e_train_timetable',
		'e_train_timetable_content_type',
		'e_train_timetable_content',
	];
	protected const OAS_REQUIRED = ['description', 'name'];
}
