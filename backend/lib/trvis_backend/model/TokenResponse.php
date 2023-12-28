<?php
/**
 * TokenResponse
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TokenResponse
 */
class TokenResponse {

    /** @var string $clientId クライアントの識別子*/
    public $clientId = "";

    /** @var string $token 発行された認証トークン*/
    public $token = "";

    /** @var string $expires トークンの有効期限*/
    public $expires = "";

}
