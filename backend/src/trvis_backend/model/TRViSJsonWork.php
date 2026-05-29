<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * TRViS_json_Work — a Work as emitted by the Dump aggregator (WorksRepo::dump
 * sets these keys via setData). Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (required/properties/order/format/nullable/
 * example preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute; OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model. TRViSJsonWorkDriftTest asserts the
 * two stay in sync. Schema name MUST be `TRViS_json_Work` (the WorkGroup
 * $ref depends on it). Legacy `title:`/`description:` dropped per Work.php.
 */
#[OA\Schema(
	schema: 'TRViS_json_Work',
	type: 'object',
	required: ['Name', 'Trains'],
	properties: [
		new OA\Property(
			property: 'Name',
			type: 'string',
			example: '123行路',
		),
		new OA\Property(
			property: 'AffectDate',
			type: 'string',
			format: 'date',
			nullable: true,
			example: '2020-01-01',
		),
		new OA\Property(
			property: 'AffixContentType',
			type: 'integer',
			nullable: true,
			example: 0,
		),
		new OA\Property(
			property: 'AffixContent',
			type: 'string',
			nullable: true,
			example: '',
		),
		new OA\Property(
			property: 'Remarks',
			type: 'string',
			nullable: true,
			example: '2023年1月1日 12時34分56秒作成',
		),
		new OA\Property(
			property: 'HasETrainTimetable',
			type: 'boolean',
			example: true,
		),
		new OA\Property(
			property: 'ETrainTimetableContentType',
			type: 'integer',
			nullable: true,
			example: 0,
		),
		new OA\Property(
			property: 'ETrainTimetableContent',
			type: 'string',
			nullable: true,
			example: '',
		),
		new OA\Property(
			property: 'Trains',
			type: 'array',
			items: new OA\Items(ref: '#/components/schemas/TRViS_json_Train'),
		),
	],
)]
class TRViSJsonWork extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'Name',
		'AffectDate',
		'AffixContentType',
		'AffixContent',
		'Remarks',
		'HasETrainTimetable',
		'ETrainTimetableContentType',
		'ETrainTimetableContent',
		'Trains',
	];
	protected const OAS_REQUIRED = ['Name', 'Trains'];
}
