<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * TRViS_json_Train — a Train as emitted by the Dump aggregator
 * (TrainsRepo::dump sets these keys via setData). Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (required/properties/order/nullable/minimum/
 * example preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute; OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model. TRViSJsonTrainDriftTest asserts the
 * two stay in sync. Schema name MUST be `TRViS_json_Train` (the Work $ref
 * depends on it). Legacy `title:`/`description:` dropped per Work.php.
 */
#[OA\Schema(
	schema: 'TRViS_json_Train',
	type: 'object',
	required: ['Direction', 'TimetableRows', 'TrainNumber'],
	properties: [
		new OA\Property(
			property: 'TrainNumber',
			type: 'string',
			example: '試1234M',
		),
		new OA\Property(
			property: 'MaxSpeed',
			type: 'string',
			nullable: true,
			example: '110',
		),
		new OA\Property(
			property: 'SpeedType',
			type: 'string',
			nullable: true,
			example: '通電A20',
		),
		new OA\Property(
			property: 'NominalTractiveCapacity',
			type: 'string',
			nullable: true,
			example: 'XXX系 1M9T',
		),
		new OA\Property(
			property: 'CarCount',
			type: 'integer',
			minimum: 0,
			nullable: true,
			example: 10,
		),
		new OA\Property(
			property: 'Destination',
			type: 'string',
			nullable: true,
			example: '東  京',
		),
		new OA\Property(
			property: 'BeginRemarks',
			type: 'string',
			nullable: true,
			example: '(乗継)',
		),
		new OA\Property(
			property: 'AfterRemarks',
			type: 'string',
			nullable: true,
			example: '(乗継)',
		),
		new OA\Property(
			property: 'Remarks',
			type: 'string',
			nullable: true,
			example: "XXXX ~ YYYY 徐行 30km/h\nAAAA ~ BBBB 車掌省略\n",
		),
		new OA\Property(
			property: 'BeforeDeparture',
			type: 'string',
			nullable: true,
			example: '転線 5分          転線',
		),
		new OA\Property(
			property: 'TrainInfo',
			type: 'string',
			nullable: true,
			example: '<div style="color: red">車掌省略</div>',
		),
		new OA\Property(
			property: 'Direction',
			type: 'integer',
			example: 1,
		),
		new OA\Property(
			property: 'AfterArrive',
			type: 'string',
			nullable: true,
			example: '転線 5分          転線',
		),
		new OA\Property(
			property: 'DayCount',
			type: 'integer',
			minimum: 0,
			example: 1,
		),
		new OA\Property(
			property: 'IsRideOnMoving',
			type: 'boolean',
			example: false,
		),
		new OA\Property(
			property: 'TimetableRows',
			type: 'array',
			items: new OA\Items(ref: '#/components/schemas/TRViS_json_TimetableRow'),
		),
	],
)]
class TRViSJsonTrain extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'TrainNumber',
		'MaxSpeed',
		'SpeedType',
		'NominalTractiveCapacity',
		'CarCount',
		'Destination',
		'BeginRemarks',
		'AfterRemarks',
		'Remarks',
		'BeforeDeparture',
		'TrainInfo',
		'Direction',
		'AfterArrive',
		'DayCount',
		'IsRideOnMoving',
		'TimetableRows',
	];
	protected const OAS_REQUIRED = ['Direction', 'TimetableRows', 'TrainNumber'];
}
