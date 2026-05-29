<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * TimetableRow — one 時刻表 row belonging to a Train. Faithful port of the
 * legacy OpenAPI-Generator MODEL_SCHEMA (title/properties/order/format/
 * example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * TimetableRowDriftTest asserts the two stay in sync.
 *
 * The legacy MODEL_SCHEMA has NO `required` array (→ OAS_REQUIRED = []), so
 * `required:` is omitted from the attribute. `work_type` is a BARE STRING
 * with no enum in the legacy schema (the validator uses an enum, but
 * MODEL_SCHEMA wins) — kept as a plain string here.
 */
#[OA\Schema(
	schema: 'TimetableRow',
	type: 'object',
	properties: [
		new OA\Property(
			property: 'timetable_rows_id',
			type: 'string',
			description: 'TimetableRowのID (UUID)',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'trains_id',
			type: 'string',
			description: 'このデータが紐づいているTrainのID (UUID)',
			format: 'uuid',
			readOnly: true,
		),
		new OA\Property(
			property: 'stations_id',
			type: 'string',
			description: 'この行の駅のID (UUID)',
			format: 'uuid',
		),
		new OA\Property(
			property: 'station_tracks_id',
			type: 'string',
			description: '駅の番線情報のID (UUID)',
			format: 'uuid',
		),
		new OA\Property(
			property: 'colors_id_marker',
			type: 'string',
			description: 'マーカーの色情報のID (UUID)',
			format: 'uuid',
		),
		new OA\Property(
			property: 'stations_name',
			type: 'string',
			description: 'stations_id が指す駅の名前 (読み取り専用・表示用)。'
				. '駅が論理削除済みの場合も名前を返す (stations_is_deleted で判別)。',
			readOnly: true,
			nullable: true,
		),
		new OA\Property(
			property: 'stations_is_deleted',
			type: 'boolean',
			description: 'stations_id が指す駅が論理削除済みかどうか (tombstone表示用)',
			readOnly: true,
		),
		new OA\Property(
			property: 'station_tracks_name',
			type: 'string',
			description: 'station_tracks_id が指す番線の名前 (読み取り専用・表示用)。'
				. '番線が論理削除済みの場合も名前を返す (station_tracks_is_deleted で判別)。',
			readOnly: true,
			nullable: true,
		),
		new OA\Property(
			property: 'station_tracks_is_deleted',
			type: 'boolean',
			description: 'station_tracks_id が指す番線が論理削除済みかどうか (tombstone表示用)',
			readOnly: true,
		),
		new OA\Property(
			property: 'colors_name',
			type: 'string',
			description: 'colors_id_marker が指す色の名前 (読み取り専用・表示用)。'
				. '色が論理削除済みの場合も名前を返す (colors_is_deleted で判別)。',
			readOnly: true,
			nullable: true,
		),
		new OA\Property(
			property: 'colors_is_deleted',
			type: 'boolean',
			description: 'colors_id_marker が指す色が論理削除済みかどうか (tombstone表示用)',
			readOnly: true,
		),
		new OA\Property(
			property: 'description',
			type: 'string',
			description: 'このTimetableRowの説明',
			example: 'このTimetableRowの説明が入ります',
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			description: '作成日時',
			format: 'date-time',
			readOnly: true,
		),
		new OA\Property(
			property: 'updated_at',
			type: 'string',
			description: '更新日時',
			format: 'date-time',
			readOnly: true,
		),
		new OA\Property(
			property: 'drive_time_mm',
			type: 'integer',
			description: '駅間運転時間 (分)',
			maximum: 99,
			minimum: 0,
			example: 3,
		),
		new OA\Property(
			property: 'drive_time_ss',
			type: 'integer',
			description: '駅間運転時間 (秒)',
			maximum: 59,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'is_operation_only_stop',
			type: 'boolean',
			description: '運転停車かどうか',
			example: false,
		),
		new OA\Property(
			property: 'is_pass',
			type: 'boolean',
			description: '通過駅かどうか',
			example: false,
		),
		new OA\Property(
			property: 'has_bracket',
			type: 'boolean',
			description: '到着時刻に括弧を付けるかどうか',
			example: false,
		),
		new OA\Property(
			property: 'is_last_stop',
			type: 'boolean',
			description: '終着駅かどうか',
			example: false,
		),
		new OA\Property(
			property: 'arrive_time_hh',
			type: 'integer',
			description: '到着時刻 (時)',
			maximum: 23,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'arrive_time_mm',
			type: 'integer',
			description: '到着時刻 (分)',
			maximum: 59,
			minimum: 0,
			example: 20,
		),
		new OA\Property(
			property: 'arrive_time_ss',
			type: 'integer',
			description: '到着時刻 (秒)',
			maximum: 59,
			minimum: 0,
			example: 25,
		),
		new OA\Property(
			property: 'departure_time_hh',
			type: 'integer',
			description: '出発時刻 (時)',
			maximum: 23,
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'departure_time_mm',
			type: 'integer',
			description: '出発時刻 (分)',
			maximum: 59,
			minimum: 0,
			example: 20,
		),
		new OA\Property(
			property: 'departure_time_ss',
			type: 'integer',
			description: '出発時刻 (秒)',
			maximum: 59,
			minimum: 0,
			example: 25,
		),
		new OA\Property(
			property: 'run_in_limit',
			type: 'integer',
			description: '進入制限 (km/h)',
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'run_out_limit',
			type: 'integer',
			description: '進出制限 (km/h)',
			minimum: 0,
			example: 15,
		),
		new OA\Property(
			property: 'remarks',
			type: 'string',
			description: '注意事項',
			example: '通過設定',
		),
		new OA\Property(
			property: 'arrive_str',
			type: 'string',
			description: '到着時刻欄に表示する文字列',
			example: '停車',
		),
		new OA\Property(
			property: 'departure_str',
			type: 'string',
			description: '出発時刻欄に表示する文字列',
			example: '???',
		),
		new OA\Property(
			property: 'marker_text',
			type: 'string',
			description: 'マーカー部分に表示する文字列',
			example: '合図',
		),
		new OA\Property(
			property: 'work_type',
			type: 'string',
			description: '作業種別 (実装準備中)',
		),
	],
)]
class TimetableRow extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'timetable_rows_id',
		'trains_id',
		'stations_id',
		'station_tracks_id',
		'colors_id_marker',
		'stations_name',
		'stations_is_deleted',
		'station_tracks_name',
		'station_tracks_is_deleted',
		'colors_name',
		'colors_is_deleted',
		'description',
		'created_at',
		'updated_at',
		'drive_time_mm',
		'drive_time_ss',
		'is_operation_only_stop',
		'is_pass',
		'has_bracket',
		'is_last_stop',
		'arrive_time_hh',
		'arrive_time_mm',
		'arrive_time_ss',
		'departure_time_hh',
		'departure_time_mm',
		'departure_time_ss',
		'run_in_limit',
		'run_out_limit',
		'remarks',
		'arrive_str',
		'departure_str',
		'marker_text',
		'work_type',
	];
	protected const OAS_REQUIRED = [];
}
