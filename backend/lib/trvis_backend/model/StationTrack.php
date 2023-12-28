<?php
/**
 * StationTrack
 */
namespace dev_t0r\trvis_backend\model;

/**
 * StationTrack
 */
class StationTrack {

    /** @var string $description Station Trackの説明*/
    public $description = "";

    /** @var string $name その番線の名前*/
    public $name = "";

    /** @var string $stationTracksId Station TrackのID (UUID)*/
    public $stationTracksId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var int $runInLimit 進入制限のデフォルト値 (km/h)*/
    public $runInLimit = 0;

    /** @var int $runOutLimit 進出制限のデフォルト値 (km/h)*/
    public $runOutLimit = 0;

}
