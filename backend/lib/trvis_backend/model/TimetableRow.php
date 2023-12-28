<?php
/**
 * TimetableRow
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TimetableRow
 */
class TimetableRow {

    /** @var string $timetableRowsId TimetableRowのID (UUID)*/
    public $timetableRowsId = "";

    /** @var string $trainsId このデータが紐づいているTrainのID (UUID)*/
    public $trainsId = "";

    /** @var string $stationsId この行の駅のID (UUID)*/
    public $stationsId = "";

    /** @var string $stationTracksId 駅の番線情報のID (UUID)*/
    public $stationTracksId = "";

    /** @var string $colorsIdMarker マーカーの色情報のID (UUID)*/
    public $colorsIdMarker = "";

    /** @var string $description このTimetableRowの説明*/
    public $description = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var \DateTime $updatedAt 更新日時*/
    public $updatedAt;

    /** @var int $driveTimeMm 駅間運転時間 (分)*/
    public $driveTimeMm = 0;

    /** @var int $driveTimeSs 駅間運転時間 (秒)*/
    public $driveTimeSs = 0;

    /** @var bool $isOperationOnlyStop 運転停車かどうか*/
    public $isOperationOnlyStop = false;

    /** @var bool $isPass 通過駅かどうか*/
    public $isPass = false;

    /** @var bool $hasBracket 到着時刻に括弧を付けるかどうか*/
    public $hasBracket = false;

    /** @var bool $isLastStop 終着駅かどうか*/
    public $isLastStop = false;

    /** @var int $arriveTimeHh 到着時刻 (時)*/
    public $arriveTimeHh = 0;

    /** @var int $arriveTimeMm 到着時刻 (分)*/
    public $arriveTimeMm = 0;

    /** @var int $arriveTimeSs 到着時刻 (秒)*/
    public $arriveTimeSs = 0;

    /** @var int $departureTimeHh 出発時刻 (時)*/
    public $departureTimeHh = 0;

    /** @var int $departureTimeMm 出発時刻 (分)*/
    public $departureTimeMm = 0;

    /** @var int $departureTimeSs 出発時刻 (秒)*/
    public $departureTimeSs = 0;

    /** @var int $runInLimit 進入制限 (km/h)*/
    public $runInLimit = 0;

    /** @var int $runOutLimit 進出制限 (km/h)*/
    public $runOutLimit = 0;

    /** @var string $remarks 注意事項*/
    public $remarks = "";

    /** @var string $arriveStr 到着時刻欄に表示する文字列*/
    public $arriveStr = "";

    /** @var string $departureStr 出発時刻欄に表示する文字列*/
    public $departureStr = "";

    /** @var string $markerText マーカー部分に表示する文字列*/
    public $markerText = "";

    /** @var string $workType 作業種別 (実装準備中)*/
    public $workType = "";

}
