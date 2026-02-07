<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Train',
    title: 'Train',
    type: 'object',
    description: 'Train (列車) のデータ構造',
    required: ['description', 'train_number', 'direction', 'day_count']
)]
class TrainSchema
{
    #[OA\Property(
        property: 'trains_id',
        type: 'string',
        format: 'uuid',
        description: 'TrainのID (UUID)',
        readOnly: true
    )]
    public string $trainsId;

    #[OA\Property(
        property: 'works_id',
        type: 'string',
        format: 'uuid',
        description: 'WorkのID (UUID)',
        readOnly: true
    )]
    public string $worksId;

    #[OA\Property(
        property: 'created_at',
        type: 'string',
        format: 'date-time',
        description: '作成日時',
        readOnly: true
    )]
    public string $createdAt;

    #[OA\Property(
        property: 'description',
        type: 'string',
        description: 'Train (列車) の説明',
        example: 'Train (列車) の説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'train_number',
        type: 'string',
        description: '列車番号',
        example: '試9999M'
    )]
    public string $trainNumber;

    #[OA\Property(
        property: 'max_speed',
        type: 'string',
        description: '最高速度 (km/h)',
        example: '130\nシク〜 60',
        nullable: true
    )]
    public ?string $maxSpeed;

    #[OA\Property(
        property: 'speed_type',
        type: 'string',
        description: '速度種別',
        example: '停電A9\nシク〜 特定',
        nullable: true
    )]
    public ?string $speedType;

    #[OA\Property(
        property: 'nominal_tractive_capacity',
        type: 'string',
        description: 'けん引定数',
        example: '999系\n9M1T',
        nullable: true
    )]
    public ?string $nominalTractiveCapacity;

    #[OA\Property(
        property: 'car_count',
        type: 'integer',
        description: '編成両数 (0以下で非表示)',
        example: 10,
        nullable: true
    )]
    public ?int $carCount;

    #[OA\Property(
        property: 'destination',
        type: 'string',
        description: '行先',
        example: '東  京',
        nullable: true
    )]
    public ?string $destination;

    #[OA\Property(
        property: 'begin_remarks',
        type: 'string',
        description: '乗車前の注意事項 (「乗継」など)',
        example: '(乗継)',
        nullable: true
    )]
    public ?string $beginRemarks;

    #[OA\Property(
        property: 'after_remarks',
        type: 'string',
        description: '降車後の注意事項 (「乗継」など)',
        example: '(乗継)',
        nullable: true
    )]
    public ?string $afterRemarks;

    #[OA\Property(
        property: 'remarks',
        type: 'string',
        description: '注意事項',
        example: 'XXXX ~ YYYY 徐行 30km/h\nAAAA ~ BBBB 車掌省略',
        nullable: true
    )]
    public ?string $remarks;

    #[OA\Property(
        property: 'before_departure',
        type: 'string',
        description: '発前',
        example: '転線 5分          転線',
        nullable: true
    )]
    public ?string $beforeDeparture;

    #[OA\Property(
        property: 'after_arrive',
        type: 'string',
        description: '着後',
        example: '転線 5分          転線',
        nullable: true
    )]
    public ?string $afterArrive;

    #[OA\Property(
        property: 'train_info',
        type: 'string',
        description: '列車に関する情報',
        example: '<div style="color: red">車掌省略</div>',
        nullable: true
    )]
    public ?string $trainInfo;

    #[OA\Property(
        property: 'direction',
        type: 'integer',
        description: '進行方向 (0~1: 下り, -1: 上り)',
        example: 1
    )]
    public int $direction;

    #[OA\Property(
        property: 'day_count',
        type: 'integer',
        minimum: 0,
        description: '仕業の初日からの経過日数 (0で初日/日勤、1で明け)',
        example: 1
    )]
    public int $dayCount;

    #[OA\Property(
        property: 'is_ride_on_moving',
        type: 'boolean',
        description: '添乗での移動かどうか',
        example: false,
        nullable: true
    )]
    public ?bool $isRideOnMoving;
}