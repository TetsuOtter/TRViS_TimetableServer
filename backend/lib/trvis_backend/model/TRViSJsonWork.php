<?php
/**
 * TRViSJsonWork
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TRViSJsonWork
 */
class TRViSJsonWork {

    /** @var string $name Workの名前*/
    public $name = "";

    /** @var \dev_t0r\trvis_backend\model\TRViSJsonTrain[] $trains このWorkに属するTrainの配列*/
    public $trains = [];

    /** @var \DateTime|null $affectDate この仕業の施行日*/
    public $affectDate = null;

    /** @var int|null $affixContentType AffixContentのContent-Type*/
    public $affixContentType = null;

    /** @var string|null $affixContent 行路添付に表示する内容*/
    public $affixContent = null;

    /** @var string|null $remarks 仕業の注意事項に表示する内容*/
    public $remarks = null;

    /** @var bool $hasETrainTimetable この行路にE電時刻表が存在するかどうか*/
    public $hasETrainTimetable = false;

    /** @var int|null $eTrainTimetableContentType E電時刻表のContent-Type*/
    public $eTrainTimetableContentType = null;

    /** @var string|null $eTrainTimetableContent E電時刻表に表示する内容*/
    public $eTrainTimetableContent = null;

}
