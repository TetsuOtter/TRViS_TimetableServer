<?php
/**
 * Train
 */
namespace dev_t0r\trvis_backend\model;

/**
 * Train
 */
class Train {

    /** @var string $description Train (列車) の説明*/
    public $description = "";

    /** @var string $trainNumber 列車番号*/
    public $trainNumber = "";

    /** @var int $direction 進行方向 (0~1: 下り, -1: 上り)*/
    public $direction = 0;

    /** @var int $dayCount 仕業の初日からの経過日数 (0で初日/日勤、1で明け)*/
    public $dayCount = 0;

    /** @var string $trainId TrainのID (UUID)*/
    public $trainId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var string $maxSpeed 最高速度 (km/h)*/
    public $maxSpeed = "";

    /** @var string $speedType 速度種別*/
    public $speedType = "";

    /** @var string $nominalTractiveCapacity けん引定数*/
    public $nominalTractiveCapacity = "";

    /** @var int $carCount 編成両数 (0以下で非表示)*/
    public $carCount = 0;

    /** @var string $destination 行先*/
    public $destination = "";

    /** @var string $beginRemarks 乗車前の注意事項 (「乗継」など)*/
    public $beginRemarks = "";

    /** @var string $afterRemarks 降車後の注意事項 (「乗継」など)*/
    public $afterRemarks = "";

    /** @var string $remarks 注意事項*/
    public $remarks = "";

    /** @var string $beforeDeparture 発前*/
    public $beforeDeparture = "";

    /** @var string $afterArrive 着後*/
    public $afterArrive = "";

    /** @var string $trainInfo 列車に関する情報*/
    public $trainInfo = "";

    /** @var bool $isRideOnMoving 添乗での移動かどうか*/
    public $isRideOnMoving = false;

}
