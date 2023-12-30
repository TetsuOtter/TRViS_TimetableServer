<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractApiInfoApi;
use dev_t0r\trvis_backend\model\ApiInfo;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiInfoApi extends AbstractApiInfoApi
{
	private string $serverName = 'trvis-backend';
	private string $appVersion = '0.0.0';
	public function __construct(Container $container)
	{
		if ($container->has('app.name')) {
			$this->serverName = $container->get('app.name') ?? $this->serverName;
		}
		if ($container->has('app.version')) {
			$this->appVersion = $container->get('app.version') ?? $this->appVersion;
		}
	}

	public function getApiInfo(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$apiInfo = ApiInfo::createFromData([
			'server_name' => $this->serverName,
			'version' => $this->appVersion,
		]);

		$response->getBody()->write(json_encode($apiInfo->jsonSerialize()));
		return $response;
	}
}
