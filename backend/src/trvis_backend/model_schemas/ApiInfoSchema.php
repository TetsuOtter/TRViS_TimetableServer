<?php

namespace dev_t0r\trvis_backend\model_schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiInfo',
    title: 'ApiInfo',
    type: 'object',
    description: 'API情報'
)]
class ApiInfoSchema
{
    #[OA\Property(
        property: 'server_name',
        type: 'string',
        description: 'サーバの名前',
        readOnly: true
    )]
    public string $serverName;

    #[OA\Property(
        property: 'version',
        type: 'string',
        description: 'APIのバージョン',
        readOnly: true
    )]
    public string $version;
}