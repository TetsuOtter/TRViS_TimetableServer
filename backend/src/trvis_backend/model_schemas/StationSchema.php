<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Station',
    title: 'Station',
    type: 'object',
    description: 'Station (駅) のデータ構造',
    required: ['description', 'name', 'location_km', 'record_type']
)]
class StationSchema
{
    #[OA\Property(
        property: 'stations_id',
        type: 'string',
        format: 'uuid',
        description: 'StationのID (UUID)',
        readOnly: true
    )]
    public string $stationsId;

    #[OA\Property(
        property: 'work_groups_id',
        type: 'string',
        format: 'uuid',
        description: 'WorkGroupのID (UUID)',
        readOnly: true
    )]
    public string $workGroupsId;

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
        description: 'Stationの説明',
        example: 'Stationの説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'name',
        type: 'string',
        description: '駅名',
        example: '東京'
    )]
    public string $name;

    #[OA\Property(
        property: 'location_km',
        type: 'number',
        format: 'double',
        description: '駅の位置 (km)',
        example: 12.345
    )]
    public float $locationKm;

    #[OA\Property(
        property: 'location_lonlat',
        description: '駅の位置 (緯度経度)',
        ref: '#/components/schemas/StationLocationLonlat',
        nullable: true
    )]
    public ?object $locationLonlat;

    #[OA\Property(
        property: 'on_station_detect_radius_m',
        type: 'number',
        format: 'double',
        description: 'その駅にいるかどうかを判定する円の半径 (m)',
        example: 123.45,
        nullable: true
    )]
    public ?float $onStationDetectRadiusM;

    #[OA\Property(
        property: 'record_type',
        type: 'number',
        description: '駅の種類',
        example: 0
    )]
    public float $recordType;
}

#[OA\Schema(
    schema: 'StationLocationLonlat',
    title: 'StationLocationLonlat',
    type: 'object',
    description: '駅の位置情報 (緯度経度)',
    required: ['longitude', 'latitude']
)]
class StationLocationLonlatSchema
{
    #[OA\Property(
        property: 'longitude',
        type: 'number',
        format: 'double',
        description: '経度',
        example: 123.45
    )]
    public float $longitude;

    #[OA\Property(
        property: 'latitude',
        type: 'number',
        format: 'double',
        description: '緯度',
        example: 12.345
    )]
    public float $latitude;
}