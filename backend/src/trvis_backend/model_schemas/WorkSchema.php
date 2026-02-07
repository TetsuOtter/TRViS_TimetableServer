<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Work',
    title: 'Work',
    type: 'object',
    description: 'Work (仕業) のデータ構造',
    required: ['description', 'name']
)]
class WorkSchema
{
    #[OA\Property(
        property: 'works_id',
        type: 'string',
        format: 'uuid',
        description: 'WorkのID (UUID)',
        readOnly: true
    )]
    public string $worksId;

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
        description: 'Workの説明',
        example: 'Workの説明が入ります'
    )]
    public string $description;

    #[OA\Property(
        property: 'name',
        type: 'string',
        description: 'Workの名前',
        example: '第NNN仕業'
    )]
    public string $name;
}