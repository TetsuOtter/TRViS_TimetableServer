<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\ApiInfo;
use dev_t0r\trvis_backend\Utils;
use DI\Container;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * GET /api/v1/ — server name + API version. Pure: no DB, no privilege,
 * no auth required. Reflects app.name / app.version from the DI container.
 *
 * Standalone (no generated Abstract* parent): the #[OA\Get] attribute below
 * is the source of truth swagger-php reads; RegisterRoutes mounts it.
 */
class ApiInfoApi
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

	/**
	 * Operation descriptors for RegisterRoutes' FQCN registry. `basePath` +
	 * `path` must agree byte-for-byte with the #[OA\Get(path:)] below (server
	 * base is /api/v1, so this resolves to `/api/v1/`).
	 *
	 * @return array<array{
	 *   methods: string[],
	 *   basePath: string,
	 *   path: string,
	 *   handler: array{0:class-string,1:string},
	 *   name: string
	 * }>
	 */
	public static function routes(): array
	{
		return [
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/',
				'handler' => [self::class, 'getApiInfo'],
				'name' => 'getApiInfo',
			],
		];
	}

	#[OA\Get(
		path: '/',
		operationId: 'getApiInfo',
		tags: ['api_info'],
		summary: 'APIの情報を取得する',
		responses: [
			new OA\Response(
				response: 200,
				description: 'APIの情報',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiInfo'),
			),
		],
	)]
	public function getApiInfo(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$apiInfo = ApiInfo::createFromData([
			'server_name' => $this->serverName,
			'version' => $this->appVersion,
		]);

		return Utils::withJson($response, $apiInfo);
	}
}
