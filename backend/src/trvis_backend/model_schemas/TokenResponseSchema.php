<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TokenResponse',
    title: 'TokenResponse',
    type: 'object',
    description: 'トークン発行のレスポンス'
)]
class TokenResponseSchema
{
    #[OA\Property(
        property: 'client_id',
        type: 'string',
        description: 'クライアントの識別子'
    )]
    public string $clientId;

    #[OA\Property(
        property: 'token',
        type: 'string',
        description: '発行された認証トークン'
    )]
    public string $token;

    #[OA\Property(
        property: 'expires',
        type: 'string',
        description: 'トークンの有効期限'
    )]
    public string $expires;
}