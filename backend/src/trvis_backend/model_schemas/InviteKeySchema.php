<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InviteKey',
    title: 'InviteKey',
    type: 'object',
    description: 'InviteKey (招待キー) のデータ構造',
    required: ['description']
)]
class InviteKeySchema
{
    #[OA\Property(
        property: 'invite_keys_id',
        type: 'string',
        format: 'uuid',
        description: 'Invite Key (UUID)',
        readOnly: true
    )]
    public string $inviteKeysId;

    #[OA\Property(
        property: 'work_groups_id',
        type: 'string',
        format: 'uuid',
        description: '対応するWorkGroupのID (UUID)',
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
        description: '招待キーの説明',
        example: 'グループAの招待キー'
    )]
    public string $description;

    #[OA\Property(
        property: 'valid_from',
        type: 'string',
        format: 'date-time',
        description: 'キーの有効期限 (開始)',
        nullable: true
    )]
    public ?string $validFrom;

    #[OA\Property(
        property: 'expires_at',
        type: 'string',
        format: 'date-time',
        description: 'キーの有効期限 (終了)',
        nullable: true
    )]
    public ?string $expiresAt;

    #[OA\Property(
        property: 'use_limit',
        type: 'integer',
        minimum: 1,
        description: 'キーの使用回数の上限',
        example: 15,
        nullable: true
    )]
    public ?int $useLimit;

    #[OA\Property(
        property: 'disabled_at',
        type: 'string',
        format: 'date-time',
        description: 'キーが無効になった日時 (Expireした場合はexpires_atと同じ値)',
        readOnly: true,
        nullable: true
    )]
    public ?string $disabledAt;

    #[OA\Property(
        property: 'privilege_type',
        type: 'string',
        enum: ['read', 'write', 'admin'],
        description: '招待キーで付与される権限',
        example: 'read',
        nullable: true
    )]
    public ?string $privilegeType;
}