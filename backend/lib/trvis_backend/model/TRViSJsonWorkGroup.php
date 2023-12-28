<?php
/**
 * TRViSJsonWorkGroup
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TRViSJsonWorkGroup
 */
class TRViSJsonWorkGroup {

    /** @var string $name WorkGroupの名前*/
    public $name = "";

    /** @var \dev_t0r\trvis_backend\model\TRViSJsonWork[] $works このWorkGroupに属するWorkの配列*/
    public $works = [];

    /** @var int $dBVersion このDB構造のバージョン情報*/
    public $dBVersion = 0;

}
