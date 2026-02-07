<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'WorkGroup',
    title: 'WorkGroup',
    type: 'object',
    description: 'WorkGroup (作業グループ) のデータ構造',
    required: ['description', 'name']
)]
class WorkGroupSchema
{
    #[OA\Property(
        property: 'work_groups_id',
        type: 'string',
        format: 'uuid',
        description: 'WorkGroupのID (UUID)',
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
        property: 'description',
        type: 'string',
        description: 'WorkGroupの説明',
        example: 'WorkGroupの説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'name',
        type: 'string',
        description: 'WorkGroupの名前',
        example: 'AAA乗務員区'
    )]
    public string $name;
}