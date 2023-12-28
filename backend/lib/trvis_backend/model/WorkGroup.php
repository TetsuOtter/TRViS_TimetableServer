<?php
/**
 * WorkGroup
 */
namespace dev_t0r\trvis_backend\model;

/**
 * WorkGroup
 */
class WorkGroup {

    /** @var string $description WorkGroupの説明*/
    public $description = "";

    /** @var string $name WorkGroupの名前*/
    public $name = "";

    /** @var string $workGroupsId WorkGroupのID (UUID)*/
    public $workGroupsId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

}
