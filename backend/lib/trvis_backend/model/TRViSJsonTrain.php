<?php
/**
 * TRViSJsonTrain
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TRViSJsonTrain
 */
class TRViSJsonTrain {

    /** @var string $trainNumber 列車番号*/
    public $trainNumber = "";

    /** @var int $direction 進行方向 (0~1: 下り, -1: 上り)*/
    public $direction = 0;

    /** @var \dev_t0r\trvis_backend\model\TRViSJsonTimetableRow[] $timetableRows このTrainに属するTimetableRowの配列*/
    public $timetableRows = [];

    /** @var string|null $maxSpeed 最高速度*/
    public $maxSpeed = null;

    /** @var string|null $speedType 速度種別*/
    public $speedType = null;

    /** @var string|null $nominalTractiveCapacity けん引定数*/
    public $nominalTractiveCapacity = null;

    /** @var int|null $carCount 車両数*/
    public $carCount = null;

    /** @var string|null $destination 行先*/
    public $destination = null;

    /** @var string|null $begionRemarks 乗車前の注意事項 (「乗継」など)*/
    public $begionRemarks = null;

    /** @var string|null $afterRemarks 降車後の注意事項 (「乗継」など)*/
    public $afterRemarks = null;

    /** @var string|null $remarks 注意事項*/
    public $remarks = null;

    /** @var string|null $beforeDeparture 発前*/
    public $beforeDeparture = null;

    /** @var string|null $trainInfo 列車に関する情報*/
    public $trainInfo = null;

    /** @var string|null $afterArrive 発前*/
    public $afterArrive = null;

    /** @var int $dayCount 仕業の初日からの経過日数 (0で初日/日勤、1で明け)*/
    public $dayCount = 0;

    /** @var bool $isRideOnMoving 添乗での移動かどうか*/
    public $isRideOnMoving = false;

}
