<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * TRViS_json_TimetableRow — a TimetableRow as emitted by the Dump aggregator
 * (TimetableRowsRepo::dump sets these keys via setData). Faithful port of the
 * legacy OpenAPI-Generator MODEL_SCHEMA (required/properties/order/format/
 * nullable/min/max/pattern/exclusiveMaximum/example preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute; OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model. TRViSJsonTimetableRowDriftTest
 * asserts the two stay in sync. Schema name MUST be `TRViS_json_TimetableRow`
 * (the Train $ref depends on it). Legacy `title:`/`description:` dropped per
 * Work.php. `WorkType` deliberately carries no example (legacy MODEL_SCHEMA
 * had none — 作業種別 実装準備中).
 */
#[OA\Schema(
	schema: 'TRViS_json_TimetableRow',
	type: 'object',
	required: ['Location_m', 'StationName'],
	properties: [
		new OA\Property(
			property: 'StationName',
			type: 'string',
			maxLength: 4,
			minLength: 1,
			example: '東京',
		),
		new OA\Property(
			property: 'Location_m',
			type: 'number',
			format: 'double',
			example: 0,
		),
		new OA\Property(
			property: 'Longitude_deg',
			type: 'number',
			format: 'double',
			maximum: 180,
			minimum: -180,
			nullable: true,
			example: 139.766944,
		),
		new OA\Property(
			property: 'Latitude_deg',
			type: 'number',
			format: 'double',
			maximum: 90,
			minimum: -90,
			nullable: true,
			example: 35.680833,
		),
		new OA\Property(
			property: 'OnStationDetectRadius_m',
			type: 'number',
			format: 'double',
			nullable: true,
			example: 123.45,
		),
		new OA\Property(
			property: 'FullName',
			type: 'string',
			nullable: true,
			example: '東京駅',
		),
		new OA\Property(
			property: 'RecordType',
			type: 'integer',
			example: 0,
		),
		new OA\Property(
			property: 'TrackName',
			type: 'string',
			nullable: true,
			example: '上1',
		),
		new OA\Property(
			property: 'DriveTime_MM',
			type: 'integer',
			maximum: 99,
			minimum: 0,
			example: 3,
		),
		new OA\Property(
			property: 'DriveTime_SS',
			type: 'integer',
			maximum: 59,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'IsOperationOnlyStop',
			type: 'boolean',
			example: false,
		),
		new OA\Property(
			property: 'IsPass',
			type: 'boolean',
			example: false,
		),
		new OA\Property(
			property: 'HasBracket',
			type: 'boolean',
			example: false,
		),
		new OA\Property(
			property: 'IsLastStop',
			type: 'boolean',
			example: false,
		),
		new OA\Property(
			property: 'Arrive',
			type: 'string',
			pattern: '^(.*|[0-9]{0,2}:[0-9]{0,2}:[0-9]{0,2})$',
			example: '12:34:56',
		),
		new OA\Property(
			property: 'Departure',
			type: 'string',
			pattern: '^(.*|[0-9]{0,2}:[0-9]{0,2}:[0-9]{0,2})$',
			example: '::56',
		),
		new OA\Property(
			property: 'RunInLimit',
			type: 'integer',
			maximum: 1000,
			exclusiveMaximum: true,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'RunOutLimit',
			type: 'integer',
			maximum: 1000,
			exclusiveMaximum: true,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'Remarks',
			type: 'string',
			example: '通過設定',
		),
		new OA\Property(
			property: 'MarkerColor',
			type: 'string',
			pattern: '^[0-9a-fA-F]{6}$',
			example: 'ff0000',
		),
		new OA\Property(
			property: 'MarkerText',
			type: 'string',
			example: '合図',
		),
		new OA\Property(
			property: 'WorkType',
			type: 'integer',
		),
	],
)]
class TRViSJsonTimetableRow extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'StationName',
		'Location_m',
		'Longitude_deg',
		'Latitude_deg',
		'OnStationDetectRadius_m',
		'FullName',
		'RecordType',
		'TrackName',
		'DriveTime_MM',
		'DriveTime_SS',
		'IsOperationOnlyStop',
		'IsPass',
		'HasBracket',
		'IsLastStop',
		'Arrive',
		'Departure',
		'RunInLimit',
		'RunOutLimit',
		'Remarks',
		'MarkerColor',
		'MarkerText',
		'WorkType',
	];
	protected const OAS_REQUIRED = ['Location_m', 'StationName'];
}
