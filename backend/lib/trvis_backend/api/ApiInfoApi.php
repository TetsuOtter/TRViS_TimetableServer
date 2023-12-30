<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\api\AbstractApiInfoApi;
use dev_t0r\trvis_backend\model\ApiInfo;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiInfoApi extends AbstractApiInfoApi
{
	public function getApiInfo(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$apiInfo = ApiInfo::createFromData([
			'server_name' => Constants::SERVER_NAME,
			'version' => Constants::API_VERSION,
		]);

		$response->getBody()->write(json_encode($apiInfo->jsonSerialize()));
		return $response;
	}
}
