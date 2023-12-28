<?php
/**
 * Color
 */
namespace dev_t0r\trvis_backend\model;

/**
 * Color
 */
class Color {

    /** @var string $description WorkGroupの説明*/
    public $description = "";

    /** @var string $name Colorの名前 (詳細な説明はdescriptionに書く)*/
    public $name = "";

    /** @var \dev_t0r\trvis_backend\model\Color8bit $color8bit */
    public $color8bit;

    /** @var string $colorsId ColorのID (UUID)*/
    public $colorsId = "";

    /** @var string $workGroupsId この色が属するWorkGroupのID (UUID)*/
    public $workGroupsId = "";

    /** @var \DateTime $createdAt 作成日時*/
    public $createdAt;

    /** @var \DateTime $updatedAt 更新日時*/
    public $updatedAt;

    /** @var \dev_t0r\trvis_backend\model\ColorReal $colorReal */
    public $colorReal;

}
