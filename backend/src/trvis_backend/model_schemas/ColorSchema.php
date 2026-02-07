<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Color',
    title: 'Color',
    type: 'object',
    description: 'Color (色) のデータ構造',
    required: ['description', 'name', 'color_8bit']
)]
class ColorSchema
{
    #[OA\Property(
        property: 'colors_id',
        type: 'string',
        format: 'uuid',
        description: 'ColorのID (UUID)',
        readOnly: true
    )]
    public string $colorsId;

    #[OA\Property(
        property: 'work_groups_id',
        type: 'string',
        format: 'uuid',
        description: 'この色が属するWorkGroupのID (UUID)',
        readOnly: true
    )]
    public string $workGroupsId;

    #[OA\Property(
        property: 'created_at',
        type: 'string',
        format: 'date-time',
        description: '作成日時',
        readOnly: true
    )]
    public string $createdAt;

    #[OA\Property(
        property: 'updated_at',
        type: 'string',
        format: 'date-time',
        description: '更新日時',
        readOnly: true
    )]
    public string $updatedAt;

    #[OA\Property(
        property: 'description',
        type: 'string',
        description: 'Colorの説明',
        example: 'Colorの説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'name',
        type: 'string',
        description: 'Colorの名前 (詳細な説明はdescriptionに書く)',
        example: '赤'
    )]
    public string $name;

    #[OA\Property(
        property: 'color_8bit',
        ref: '#/components/schemas/Color8bit',
        description: '色の8bit表現'
    )]
    public object $color8bit;

    #[OA\Property(
        property: 'color_real',
        ref: '#/components/schemas/ColorReal',
        description: '色の小数表現',
        nullable: true
    )]
    public ?object $colorReal;
}

#[OA\Schema(
    schema: 'Color8bit',
    title: 'Color8bit',
    type: 'object',
    description: '色の8bit表現',
    required: ['red', 'green', 'blue']
)]
class Color8bitSchema
{
    #[OA\Property(
        property: 'red',
        type: 'integer',
        minimum: 0,
        maximum: 255,
        description: '色の赤色成分 (8bit)',
        example: 127
    )]
    public int $red;

    #[OA\Property(
        property: 'green',
        type: 'integer',
        minimum: 0,
        maximum: 255,
        description: '色の緑色成分 (8bit)',
        example: 127
    )]
    public int $green;

    #[OA\Property(
        property: 'blue',
        type: 'integer',
        minimum: 0,
        maximum: 255,
        description: '色の青色成分 (8bit)',
        example: 127
    )]
    public int $blue;
}

#[OA\Schema(
    schema: 'ColorReal',
    title: 'ColorReal',
    type: 'object',
    description: '色の小数表現',
    required: ['red', 'green', 'blue']
)]
class ColorRealSchema
{
    #[OA\Property(
        property: 'red',
        type: 'number',
        format: 'double',
        minimum: 0.0,
        maximum: 1.0,
        description: '色の赤色成分 (小数)',
        example: 0.5
    )]
    public float $red;

    #[OA\Property(
        property: 'green',
        type: 'number',
        format: 'double',
        minimum: 0.0,
        maximum: 1.0,
        description: '色の緑色成分 (小数)',
        example: 0.5
    )]
    public float $green;

    #[OA\Property(
        property: 'blue',
        type: 'number',
        format: 'double',
        minimum: 0.0,
        maximum: 1.0,
        description: '色の青色成分 (小数)',
        example: 0.5
    )]
    public float $blue;
}