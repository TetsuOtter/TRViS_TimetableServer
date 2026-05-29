<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * Train — a 列車 belonging to a Work. Faithful port of the legacy
 * OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * TrainDriftTest asserts the two stay in sync.
 *
 * No string-enum properties — all types are string/integer/boolean.
 */
#[OA\Schema(
	schema: 'Train',
	type: 'object',
	required: ['day_count', 'description', 'direction', 'train_number'],
	properties: [
		new OA\Property(
			property: 'trains_id',
			type: 'string',
			description: 'TrainのID (UUID)',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'works_id',
			type: 'string',
			description: 'WorkのID (UUID)',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			description: '作成日時',
			format: 'date-time',
			readOnly: true,
		),
		new OA\Property(
			property: 'description',
			type: 'string',
			description: 'Train (列車) の説明',
			example: 'Train (列車) の説明が入ります',
		),
		new OA\Property(
			property: 'train_number',
			type: 'string',
			description: '列車番号',
			example: '試9999M',
		),
		new OA\Property(
			property: 'max_speed',
			type: 'string',
			description: '最高速度 (km/h)',
			example: "130\nシク〜 60\n",
		),
		new OA\Property(
			property: 'speed_type',
			type: 'string',
			description: '速度種別',
			example: "停電A9\nシク〜 特定\n",
		),
		new OA\Property(
			property: 'nominal_tractive_capacity',
			type: 'string',
			description: 'けん引定数',
			example: "999系\n9M1T\n",
		),
		new OA\Property(
			property: 'car_count',
			type: 'integer',
			description: '編成両数 (0以下で非表示)',
			example: 10,
		),
		new OA\Property(
			property: 'destination',
			type: 'string',
			description: '行先',
			example: '東  京',
		),
		new OA\Property(
			property: 'begin_remarks',
			type: 'string',
			description: '乗車前の注意事項 (「乗継」など)',
			example: '(乗継)',
		),
		new OA\Property(
			property: 'after_remarks',
			type: 'string',
			description: '降車後の注意事項 (「乗継」など)',
			example: '(乗継)',
		),
		new OA\Property(
			property: 'remarks',
			type: 'string',
			description: '注意事項',
			example: "XXXX ~ YYYY 徐行 30km/h\nAAAA ~ BBBB 車掌省略\n",
		),
		new OA\Property(
			property: 'before_departure',
			type: 'string',
			description: '発前',
			example: '転線 5分          転線',
		),
		new OA\Property(
			property: 'after_arrive',
			type: 'string',
			description: '着後',
			example: '転線 5分          転線',
		),
		new OA\Property(
			property: 'train_info',
			type: 'string',
			description: '列車に関する情報',
			example: '<div style="color: red">車掌省略</div>',
		),
		new OA\Property(
			property: 'direction',
			type: 'integer',
			description: '進行方向 (0~1: 下り, -1: 上り)',
			example: 1,
		),
		new OA\Property(
			property: 'day_count',
			type: 'integer',
			description: '仕業の初日からの経過日数 (0で初日/日勤、1で明け)',
			minimum: 0,
			example: 1,
		),
		new OA\Property(
			property: 'is_ride_on_moving',
			type: 'boolean',
			description: '添乗での移動かどうか',
			example: false,
		),
	],
)]
class Train extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'trains_id',
		'works_id',
		'created_at',
		'description',
		'train_number',
		'max_speed',
		'speed_type',
		'nominal_tractive_capacity',
		'car_count',
		'destination',
		'begin_remarks',
		'after_remarks',
		'remarks',
		'before_departure',
		'after_arrive',
		'train_info',
		'direction',
		'day_count',
		'is_ride_on_moving',
	];
	protected const OAS_REQUIRED = ['day_count', 'description', 'direction', 'train_number'];
}
