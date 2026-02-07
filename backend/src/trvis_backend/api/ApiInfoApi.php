<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractApiInfoApi;
use dev_t0r\trvis_backend\model\ApiInfo;
use dev_t0r\trvis_backend\Utils;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'TRViS用 時刻表管理用API'
)]
#[OA\Server(url: 'http://localhost:8080/api/v1', description: 'ローカル開発環境(Docker)')]
#[OA\Server(url: 'http://localhost:8888/api/v1', description: 'ローカル開発環境(ホストマシン)')]
#[OA\Server(url: 'https://trvis.t0r.dev/api/v1', description: '本番環境')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'api_info', description: 'APIの情報を取得する')]
#[OA\Tag(name: 'auth', description: '認証/認可関連のAPI (トークン発行など)')]
#[OA\Tag(name: 'invite_key', description: '招待キー関係の操作を行う')]
#[OA\Tag(name: 'work_group', description: 'Work Groupの操作を行う')]
#[OA\Tag(name: 'station', description: 'Stationの操作を行う')]
#[OA\Tag(name: 'station_track', description: 'Station Track (各駅の番線) の操作を行う')]
#[OA\Tag(name: 'work', description: 'Work (仕業) の操作を行う')]
#[OA\Tag(name: 'train', description: 'Train (列車) の操作を行う')]
#[OA\Tag(name: 'timetable_row', description: 'TimetableRow (運転時刻表の1行) の操作を行う')]
#[OA\Tag(name: 'color', description: '色情報の操作を行う')]
#[OA\Tag(name: 'dump', description: '複数のデータをまとめて出力する')]
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

	#[OA\Get(
		path: '/',
		operationId: 'getApiInfo',
		summary: 'APIの情報を取得する',
		description: 'APIのバージョン等、APIの情報を取得する',
		tags: ['api_info']
	)]
	#[OA\Response(
		response: 200,
		description: 'APIの情報',
		content: new OA\JsonContent(ref: '#/components/schemas/ApiInfo')
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
