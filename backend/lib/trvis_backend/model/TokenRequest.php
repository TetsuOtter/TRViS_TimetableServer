<?php
/**
 * TokenRequest
 */
namespace dev_t0r\trvis_backend\model;

/**
 * TokenRequest
 * @description トークンの発行をリクエストする際に使用するオブジェクト
 */
class TokenRequest {

    /** @var string $apiKey APIキー*/
    public $apiKey = "";

    /** @var string $clientId APIキー*/
    public $clientId = "";

}
