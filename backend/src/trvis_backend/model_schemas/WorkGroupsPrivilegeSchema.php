<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'WorkGroupsPrivilege',
    title: 'WorkGroupsPrivilege',
    type: 'object',
    description: 'WorkGroupsPrivilege (WorkGroupへのアクセス権限) のデータ構造',
    required: ['privilege_type']
)]
class WorkGroupsPrivilegeSchema
{
    #[OA\Property(
        property: 'uid',
        type: 'string',
        description: 'UserID',
        readOnly: true
    )]
    public string $uid;

    #[OA\Property(
        property: 'work_groups_id',
        type: 'string',
        format: 'uuid',
        description: 'WorkGroupのID (UUID)',
        readOnly: true
    )]
    public string $workGroupsId;

    #[OA\Property(
        property: 'invite_keys_id',
        type: 'string',
        format: 'uuid',
        description: 'InviteKeyのID (UUID)',
        readOnly: true
    )]
    public string $inviteKeysId;

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
        property: 'privilege_type',
        type: 'string',
        enum: ['read', 'write', 'admin'],
        description: '権限の種類',
        example: 'admin'
    )]
    public string $privilegeType;
}