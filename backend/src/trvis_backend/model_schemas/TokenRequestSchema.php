<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TokenRequest',
    title: 'トークン発行リクエスト',
    type: 'object',
    description: 'トークンの発行をリクエストする際に使用するオブジェクト',
    required: ['api_key']
)]
class TokenRequestSchema
{
    #[OA\Property(
        property: 'api_key',
        type: 'string',
        description: 'APIキー'
    )]
    public string $apiKey;

    #[OA\Property(
        property: 'client_id',
        type: 'string',
        description: 'クライアントID',
        nullable: true
    )]
    public ?string $clientId;
}