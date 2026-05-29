<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * StationTrack CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Privilege root is Station (Station-rooted via stations_id).
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file.
 *
 * operationId + route `name` are normalized to `<verb><Entity><Action>`
 * per CONTRIBUTING-P3.md §4 naming invariant (name === operationId).
 */
class StationTrackApi
{
	private readonly StationTracksService $stationTracksService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationTracksService = new StationTracksService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			RequestValidator::getDescriptionValidationRule(),
			new IntValidationRule(
				key: 'run_in_limit',
				isNullable: true,
				isRequired: false,
				minValue: 1,
				maxValue: 999,
			),
			new IntValidationRule(
				key: 'run_out_limit',
				isNullable: true,
				isRequired: false,
				minValue: 1,
				maxValue: 999,
			),
		);
	}

	/**
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
				'path' => '/stations/{stationId}/tracks',
				'handler' => [self::class, 'getStationTrackList'],
				'name' => 'getStationTrackList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/stations/{stationId}/tracks',
				'handler' => [self::class, 'createStationTrack'],
				'name' => 'createStationTrack',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/tracks/{stationTrackId}',
				'handler' => [self::class, 'getStationTrack'],
				'name' => 'getStationTrack',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/tracks/{stationTrackId}',
				'handler' => [self::class, 'updateStationTrack'],
				'name' => 'updateStationTrack',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/tracks/{stationTrackId}',
				'handler' => [self::class, 'deleteStationTrack'],
				'name' => 'deleteStationTrack',
			],
		];
	}

	#[OA\Get(
		path: '/stations/{stationId}/tracks',
		operationId: 'getStationTrackList',
		tags: ['station_track'],
		summary: '複数件取得する',
		description: '指定のStationに属するStationTrackの情報を複数件取得する  属するWorkGroupへのREAD権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationId',
				description: 'StationのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
			new OA\QueryParameter(
				name: 'p',
				description: 'ページングを行う場合のページ番号',
				required: false,
				schema: new OA\Schema(type: 'integer', default: 1, minimum: 1, maximum: 1000000, example: 1),
			),
			new OA\QueryParameter(
				name: 'limit',
				description: 'ページングを行う場合の1ページあたりの件数',
				required: false,
				schema: new OA\Schema(type: 'integer', default: 10, maximum: 100, minimum: 5, example: 20),
			),
			new OA\QueryParameter(
				name: 'top',
				description: 'ページングを行う場合の一番上に表示するID',
				required: false,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				headers: [
					new OA\Header(
						header: 'X-Total-Count',
						description: '取得できるレコードの総件数',
						schema: new OA\Schema(type: 'integer', minimum: 0),
					),
				],
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/StationTrack'),
				),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 404,
				description: 'コンテンツが存在しない',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function getStationTrackList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationId)) {
			$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->stationTracksService->getPage(
			userId: $userId,
			stationsId: Uuid::fromString($stationId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/stations/{stationId}/tracks',
		operationId: 'createStationTrack',
		tags: ['station_track'],
		summary: '作成する',
		description: '指定のStationに属する StationTrack を新しく作成する  属するWorkGroupへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationId',
				description: 'StationのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '作成するStationTrackの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/StationTrack'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/StationTrack')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/StationTrack'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/StationTrack'),
				),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 403,
				description: '許可されていない操作を行おうとした',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 404,
				description: 'コンテンツが存在しない',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function createStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($stationId)) {
			$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$isSingleItemRequest = !is_array($body) || !array_is_list($body);
		$bodyList = $isSingleItemRequest ? [$body] : $body;

		$validateResult = $this->bodyValidator->validate(
			d: $bodyList,
			checkRequired: true,
			allowNestedArray: true,
		);
		if ($validateResult->isError) {
			$this->logger->warning(
				"Invalid request body: {msg}",
				['msg' => $validateResult->errorMsg],
			);
			return $validateResult->getResponseWithJson($response);
		}

		$dataList = array_map(function ($item) {
			$model = new StationTrack();
			$model->setData([
				'name' => Utils::getValueOrNull($item, 'name'),
				'description' => Utils::getValueOrNull($item, 'description') ?? '',
				'run_in_limit' => Utils::getValueOrNull($item, 'run_in_limit'),
				'run_out_limit' => Utils::getValueOrNull($item, 'run_out_limit'),
			]);
			return $model;
		}, $bodyList);

		$createResult = $this->stationTracksService->create(
			stationsId: Uuid::fromString($stationId),
			userId: $userId,
			dataList: $dataList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/tracks/{stationTrackId}',
		operationId: 'getStationTrack',
		tags: ['station_track'],
		summary: '1件取得する',
		description: 'StationTrack (駅の番線) の情報を1件取得する  このデータが属するWorkGroupへのREAD権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationTrackId',
				description: 'Station TrackのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StationTrack'),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 404,
				description: 'コンテンツが存在しない',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function getStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationTrackId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationTrackId]);
			return Utils::withUuidError($response);
		}

		return $this->stationTracksService->getOne(
			userId: $userId,
			stationTracksId: Uuid::fromString($stationTrackId),
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/tracks/{stationTrackId}',
		operationId: 'updateStationTrack',
		tags: ['station_track'],
		summary: '更新する',
		description: '既存のStationTrackの情報を更新する  このデータが属するWorkGroupへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationTrackId',
				description: 'Station TrackのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後のStationTrackの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/StationTrack'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StationTrack'),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 403,
				description: '許可されていない操作を行おうとした',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 404,
				description: 'コンテンツが存在しない',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function updateStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationTrackId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationTrackId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: false,
			allowNestedArray: false,
		);
		if ($validateResult->isError) {
			$this->logger->warning(
				"Invalid request body: {msg}",
				['msg' => $validateResult->errorMsg],
			);
			return $validateResult->getResponseWithJson($response);
		}

		$checkPropExists = is_array($body)
			? fn (string $key): bool => array_key_exists($key, $body)
			: fn (string $key): bool => property_exists($body ?? (object)[], $key);

		$modelData = [];
		foreach (['name', 'description', 'run_in_limit', 'run_out_limit'] as $key) {
			if ($checkPropExists($key)) {
				$modelData[$key] = Utils::getValueOrNull($body, $key);
			}
		}
		$model = new StationTrack();
		$model->setData($modelData);

		return $this->stationTracksService->update(
			userId: $userId,
			stationTracksId: Uuid::fromString($stationTrackId),
			data: $model,
			requestBody: $body,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/tracks/{stationTrackId}',
		operationId: 'deleteStationTrack',
		tags: ['station_track'],
		summary: '削除する',
		description: '既存のStationTrackを削除する  このデータが属するWorkGroupへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationTrackId',
				description: 'Station TrackのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '削除成功',
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 403,
				description: '許可されていない操作を行おうとした',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 404,
				description: 'コンテンツが存在しない',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function deleteStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationTrackId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationTrackId]);
			return Utils::withUuidError($response);
		}

		return $this->stationTracksService->delete(
			userId: $userId,
			stationTracksId: Uuid::fromString($stationTrackId),
		)->getResponseWithJson($response);
	}
}
