<?php
/**
 * Work
 */
namespace dev_t0r\trvis_backend\model;

/**
 * Work
 */
class Work {

    /** @var string $description Workの説明*/
    public $description = "";

    /** @var string $name Workの名前*/
    public $name = "";

    /** @var string $worksId WorkのID (UUID)*/
    public $worksId = "";

    /** @var string $workGroupsId WorkGroupのID (UUID)*/
    public $workGroupsId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var \DateTime $affectDate 発効日*/
    public $affectDate;

    /** @var string $affixContentType affix_content のファイル形式*/
    public $affixContentType = "";

    /** @var OneOf|null $affixContent 「行路添付」に表示させる内容*/
    public $affixContent = null;

    /** @var string $remarks 「注意事項」に表示させる内容*/
    public $remarks = "";

    /** @var bool $hasETrainTimetable 「E電時刻表」を表示させるかどうか*/
    public $hasETrainTimetable = false;

    /** @var string $eTrainTimetableContentType e_train_timetable_content のファイル形式*/
    public $eTrainTimetableContentType = "";

    /** @var OneOf|null $eTrainTimetableContent 「E電時刻表」に表示させる内容*/
    public $eTrainTimetableContent = null;

}
