<?php
/**
 * Station
 */
namespace dev_t0r\trvis_backend\model;

/**
 * Station
 */
class Station {

    /** @var string $description Stationの説明*/
    public $description = "";

    /** @var string $name 駅名*/
    public $name = "";

    /** @var float $locationKm 駅の位置 (km)*/
    public $locationKm = 0;

    /** @var string $stationsId StationのID (UUID)*/
    public $stationsId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var \dev_t0r\trvis_backend\model\StationLocationLonlat $locationLonlat */
    public $locationLonlat;

    /** @var float $onStationDetectRadiusM その駅にいるかどうかを判定する円の半径 (m)*/
    public $onStationDetectRadiusM = 0;

    /** @var float $recordType 駅の種類*/
    public $recordType = 0;

}
