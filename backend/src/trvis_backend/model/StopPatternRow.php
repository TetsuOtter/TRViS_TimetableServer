<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * StopPatternRow — StopPattern-child station row entity. Faithful port of the
 * legacy OpenAPI-Generator MODEL_SCHEMA (title/required/properties/order/
 * format/example all preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * StopPatternRowDriftTest asserts the two stay in sync.
 *
 * Note: `description` column exists in the DB but is NOT in the API schema
 * (legacy design decision; INSERT omits it, DB default is '').
 * `projects_id` IS a real column in stop_pattern_rows (populated via subquery
 * from stop_patterns at INSERT time; used by selectPrivilegeType resolver).
 */
#[OA\Schema(
	schema: 'StopPatternRow',
	type: 'object',
	required: ['project_stations_id'],
	properties: [
		new OA\Property(
			property: 'stop_pattern_rows_id',
			type: 'string',
			format: 'uuid',
			description: 'StopPatternRowのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'stop_patterns_id',
			type: 'string',
			format: 'uuid',
			description: '紐づくStopPatternのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'projects_id',
			type: 'string',
			format: 'uuid',
			description: 'ProjectのID (UUID)',
			readOnly: true,
		),
		new OA\Property(
			property: 'project_stations_id',
			type: 'string',
			format: 'uuid',
			description: 'この行の駅 (Project Station) のID (UUID)',
		),
		new OA\Property(
			property: 'created_at',
			type: 'string',
			format: 'date-time',
			description: '作成日時',
			readOnly: true,
		),
		new OA\Property(
			property: 'sort_key',
			type: 'integer',
			minimum: 0,
			description: '並び順 (昇順)',
			example: 0,
		),
		new OA\Property(
			property: 'track_name',
			type: 'string',
			description: '番線名',
			example: '上2',
		),
		new OA\Property(
			property: 'track_hidden',
			type: 'boolean',
			description: '番線を非表示にするかどうか',
			example: false,
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
			property: 'drive_time_mm',
			type: 'integer',
			maximum: 99,
			minimum: 0,
			description: '駅間運転時間 (分)',
			example: 3,
		),
		new OA\Property(
			property: 'drive_time_ss',
			type: 'integer',
			maximum: 59,
			minimum: 0,
			description: '駅間運転時間 (秒)',
			example: 15,
		),
		new OA\Property(
			property: 'dwell_time_mm',
			type: 'integer',
			maximum: 99,
			minimum: 0,
			description: '停車時間 (分)',
			example: 1,
		),
		new OA\Property(
			property: 'dwell_time_ss',
			type: 'integer',
			maximum: 59,
			minimum: 0,
			description: '停車時間 (秒)',
			example: 0,
		),
		new OA\Property(
			property: 'show_arrive',
			type: 'boolean',
			description: '到着時刻を表示するかどうか',
			example: true,
		),
		new OA\Property(
			property: 'show_departure',
			type: 'boolean',
			description: '出発時刻を表示するかどうか',
			example: true,
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
			example: '↓',
		),
		new OA\Property(
			property: 'run_in_limit',
			type: 'integer',
			minimum: 0,
			description: '進入制限 (km/h)',
			example: 15,
		),
		new OA\Property(
			property: 'run_out_limit',
			type: 'integer',
			minimum: 0,
			description: '進出制限 (km/h)',
			example: 15,
		),
		new OA\Property(
			property: 'remarks',
			type: 'string',
			description: '注意事項',
			example: '通過設定',
		),
		new OA\Property(
			property: 'always_show_hh',
			type: 'boolean',
			description: '時刻表示で常に「時」を表示するかどうか',
			example: false,
		),
	],
)]
class StopPatternRow extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'stop_pattern_rows_id',
		'stop_patterns_id',
		'projects_id',
		'project_stations_id',
		'created_at',
		'sort_key',
		'track_name',
		'track_hidden',
		'is_operation_only_stop',
		'is_pass',
		'drive_time_mm',
		'drive_time_ss',
		'dwell_time_mm',
		'dwell_time_ss',
		'show_arrive',
		'show_departure',
		'arrive_str',
		'departure_str',
		'run_in_limit',
		'run_out_limit',
		'remarks',
		'always_show_hh',
	];
	protected const OAS_REQUIRED = ['project_stations_id'];
}
