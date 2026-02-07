<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StationTrack',
    title: 'StationTrack',
    type: 'object',
    description: 'StationTrack (駅の番線情報) のデータ構造',
    required: ['name', 'description']
)]
class StationTrackSchema
{
    #[OA\Property(
        property: 'station_tracks_id',
        type: 'string',
        format: 'uuid',
        description: 'Station TrackのID (UUID)',
        readOnly: true
    )]
    public string $stationTracksId;

    #[OA\Property(
        property: 'stations_id',
        type: 'string',
        format: 'uuid',
        description: 'StationのID (UUID)',
        readOnly: true
    )]
    public string $stationsId;

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
        description: 'Station Trackの説明',
        example: 'Station Trackの説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'name',
        type: 'string',
        description: 'その番線の名前',
        example: '上2'
    )]
    public string $name;

    #[OA\Property(
        property: 'run_in_limit',
        type: 'integer',
        minimum: 0,
        description: '進入制限のデフォルト値 (km/h)',
        example: 15,
        nullable: true
    )]
    public ?int $runInLimit;

    #[OA\Property(
        property: 'run_out_limit',
        type: 'integer',
        minimum: 0,
        description: '進出制限のデフォルト値 (km/h)',
        example: 15,
        nullable: true
    )]
    public ?int $runOutLimit;
}