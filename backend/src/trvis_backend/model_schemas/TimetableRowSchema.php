<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TimetableRow',
    title: 'TimetableRow',
    type: 'object',
    description: 'TimetableRow (運転時刻表の1行) のデータ構造'
)]
class TimetableRowSchema
{
    #[OA\Property(
        property: 'timetable_rows_id',
        type: 'string',
        format: 'uuid',
        description: 'TimetableRowのID (UUID)',
        readOnly: true
    )]
    public string $timetableRowsId;

    #[OA\Property(
        property: 'trains_id',
        type: 'string',
        format: 'uuid',
        description: 'このデータが紐づいているTrainのID (UUID)',
        readOnly: true
    )]
    public string $trainsId;

    #[OA\Property(
        property: 'stations_id',
        type: 'string',
        format: 'uuid',
        description: 'この行の駅のID (UUID)'
    )]
    public string $stationsId;

    #[OA\Property(
        property: 'station_tracks_id',
        type: 'string',
        format: 'uuid',
        description: '駅の番線情報のID (UUID)',
        nullable: true
    )]
    public ?string $stationTracksId;

    #[OA\Property(
        property: 'colors_id_marker',
        type: 'string',
        format: 'uuid',
        description: 'マーカーの色情報のID (UUID)',
        nullable: true
    )]
    public ?string $colorsIdMarker;

    #[OA\Property(
        property: 'description',
        type: 'string',
        description: 'このTimetableRowの説明',
        example: 'このTimetableRowの説明が入ります',
        nullable: true
    )]
    public ?string $description;

    #[OA\Property(
        property: 'created_at',
        type: 'string',
        format: 'date-time',
        description: '作成日時',
        readOnly: true
    )]
    public string $createdAt;

    #[OA\Property(
        property: 'updated_at',
        type: 'string',
        format: 'date-time',
        description: '更新日時',
        readOnly: true
    )]
    public string $updatedAt;

    #[OA\Property(
        property: 'drive_time_mm',
        type: 'integer',
        minimum: 0,
        maximum: 99,
        description: '駅間運転時間 (分)',
        example: 3,
        nullable: true
    )]
    public ?int $driveTimeMm;

    #[OA\Property(
        property: 'drive_time_ss',
        type: 'integer',
        minimum: 0,
        maximum: 59,
        description: '駅間運転時間 (秒)',
        example: 15,
        nullable: true
    )]
    public ?int $driveTimeSs;

    #[OA\Property(
        property: 'is_operation_only_stop',
        type: 'boolean',
        description: '運転停車かどうか',
        example: false,
        nullable: true
    )]
    public ?bool $isOperationOnlyStop;

    #[OA\Property(
        property: 'is_pass',
        type: 'boolean',
        description: '通過駅かどうか',
        example: false,
        nullable: true
    )]
    public ?bool $isPass;

    #[OA\Property(
        property: 'has_bracket',
        type: 'boolean',
        description: '到着時刻に括弧を付けるかどうか',
        example: false,
        nullable: true
    )]
    public ?bool $hasBracket;

    #[OA\Property(
        property: 'is_last_stop',
        type: 'boolean',
        description: '終着駅かどうか',
        example: false,
        nullable: true
    )]
    public ?bool $isLastStop;

    #[OA\Property(
        property: 'arrive_time_hh',
        type: 'integer',
        minimum: 0,
        maximum: 23,
        description: '到着時刻 (時)',
        example: 15,
        nullable: true
    )]
    public ?int $arriveTimeHh;

    #[OA\Property(
        property: 'arrive_time_mm',
        type: 'integer',
        minimum: 0,
        maximum: 59,
        description: '到着時刻 (分)',
        example: 20,
        nullable: true
    )]
    public ?int $arriveTimeMm;

    #[OA\Property(
        property: 'arrive_time_ss',
        type: 'integer',
        minimum: 0,
        maximum: 59,
        description: '到着時刻 (秒)',
        example: 25,
        nullable: true
    )]
    public ?int $arriveTimeSs;

    #[OA\Property(
        property: 'departure_time_hh',
        type: 'integer',
        minimum: 0,
        maximum: 23,
        description: '出発時刻 (時)',
        example: 15,
        nullable: true
    )]
    public ?int $departureTimeHh;

    #[OA\Property(
        property: 'departure_time_mm',
        type: 'integer',
        minimum: 0,
        maximum: 59,
        description: '出発時刻 (分)',
        example: 20,
        nullable: true
    )]
    public ?int $departureTimeMm;

    #[OA\Property(
        property: 'departure_time_ss',
        type: 'integer',
        minimum: 0,
        maximum: 59,
        description: '出発時刻 (秒)',
        example: 25,
        nullable: true
    )]
    public ?int $departureTimeSs;

    #[OA\Property(
        property: 'run_in_limit',
        type: 'integer',
        minimum: 0,
        description: '進入制限 (km/h)',
        example: 15,
        nullable: true
    )]
    public ?int $runInLimit;

    #[OA\Property(
        property: 'run_out_limit',
        type: 'integer',
        minimum: 0,
        description: '進出制限 (km/h)',
        example: 15,
        nullable: true
    )]
    public ?int $runOutLimit;

    #[OA\Property(
        property: 'remarks',
        type: 'string',
        description: '注意事項',
        example: '通過設定',
        nullable: true
    )]
    public ?string $remarks;

    #[OA\Property(
        property: 'arrive_str',
        type: 'string',
        description: '到着時刻欄に表示する文字列',
        example: '停車',
        nullable: true
    )]
    public ?string $arriveStr;

    #[OA\Property(
        property: 'departure_str',
        type: 'string',
        description: '出発時刻欄に表示する文字列',
        example: '???',
        nullable: true
    )]
    public ?string $departureStr;

    #[OA\Property(
        property: 'marker_text',
        type: 'string',
        description: 'マーカー部分に表示する文字列',
        example: '合図',
        nullable: true
    )]
    public ?string $markerText;

    #[OA\Property(
        property: 'work_type',
        type: 'string',
        description: '作業種別 (実装準備中)',
        nullable: true
    )]
    public ?string $workType;
}