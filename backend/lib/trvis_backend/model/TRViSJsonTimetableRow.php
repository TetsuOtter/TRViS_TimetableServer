<?php
/**
 * TRViSJsonTimetableRow
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TRViSJsonTimetableRow
 */
class TRViSJsonTimetableRow {

    /** @var string $stationName 駅名 (4文字まで)*/
    public $stationName = "";

    /** @var float $locationM 駅の位置 (m)*/
    public $locationM = 0;

    /** @var float|null $longitudeDeg 駅の経度 (度)*/
    public $longitudeDeg = null;

    /** @var float|null $latitudeDeg 駅の緯度 (度)*/
    public $latitudeDeg = null;

    /** @var float|null $onStationDetectRadiusM その駅にいるかどうかを判定する円の半径 (m)*/
    public $onStationDetectRadiusM = null;

    /** @var string|null $fullName 駅のフルネーム*/
    public $fullName = null;

    /** @var int $recordType 駅の種類*/
    public $recordType = 0;

    /** @var string|null $trackName 駅の番線名*/
    public $trackName = null;

    /** @var int $driveTimeMM 駅間運転時間 (分)*/
    public $driveTimeMM = 0;

    /** @var int $driveTimeSS 駅間運転時間 (秒)*/
    public $driveTimeSS = 0;

    /** @var bool $isOperationOnlyStop 運転停車かどうか*/
    public $isOperationOnlyStop = false;

    /** @var bool $isPass 通過駅かどうか*/
    public $isPass = false;

    /** @var bool $hasBracket 到着時刻に括弧を付けるかどうか*/
    public $hasBracket = false;

    /** @var bool $isLastStop 終着駅かどうか*/
    public $isLastStop = false;

    /** @var string $arrive 到着時刻*/
    public $arrive = "";

    /** @var string $departure 出発時刻*/
    public $departure = "";

    /** @var int $runInLimit 進入制限 (km/h)*/
    public $runInLimit = 0;

    /** @var int $runOutLimit 進出制限 (km/h)*/
    public $runOutLimit = 0;

    /** @var string $remarks 注意事項*/
    public $remarks = "";

    /** @var string $markerColor マーカーの色*/
    public $markerColor = "";

    /** @var string $markerText マーカー部分に表示する文字列*/
    public $markerText = "";

    /** @var int $workType 作業種別 (実装準備中)*/
    public $workType = 0;

}
