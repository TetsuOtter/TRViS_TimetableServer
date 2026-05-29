<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationsOnLineService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * StationOnLine CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Privilege root is Line (indirect via project_lines_id → projects_privileges).
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file.
 *
 * operationId + route `name` are normalized to `<verb><Entity><Action>`
 * per CONTRIBUTING-P3.md §4 naming invariant (name === operationId).
 */
class StationOnLineApi
{
	private readonly StationsOnLineService $stationsOnLineService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsOnLineService = new StationsOnLineService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			new UuidValidationRule(
				key: 'project_stations_id',
				isNullable: false,
				isRequired: true,
			),
			new FloatValidationRule(
				key: 'location_m',
				isNullable: false,
				isRequired: true,
			),
			new LonLatValidationRule(
				key: 'location_lonlat',
				isNullable: true,
				isRequired: false,
			),
			new BoolValidationRule(
				key: 'track_hidden_by_default',
				isNullable: false,
				isRequired: false,
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
				'path' => '/lines/{lineId}/stations_on_line',
				'handler' => [self::class, 'getStationOnLineList'],
				'name' => 'getStationOnLineList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/lines/{lineId}/stations_on_line',
				'handler' => [self::class, 'createStationOnLine'],
				'name' => 'createStationOnLine',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/stations_on_line/{stationOnLineId}',
				'handler' => [self::class, 'getStationOnLine'],
				'name' => 'getStationOnLine',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/stations_on_line/{stationOnLineId}',
				'handler' => [self::class, 'updateStationOnLine'],
				'name' => 'updateStationOnLine',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/stations_on_line/{stationOnLineId}',
				'handler' => [self::class, 'deleteStationOnLine'],
				'name' => 'deleteStationOnLine',
			],
		];
	}

	#[OA\Get(
		path: '/lines/{lineId}/stations_on_line',
		operationId: 'getStationOnLineList',
		tags: ['station_on_line'],
		summary: '複数件取得する',
		description: "指定のLineに属する StationOnLine (路線上の駅) の情報を複数件取得する\n\nこのデータが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'lineId',
				description: 'LineのID',
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
					items: new OA\Items(ref: '#/components/schemas/StationOnLine'),
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
	public function getStationOnLineList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($lineId)) {
			$this->logger->warning("Invalid UUID format ({lineId})", ['lineId' => $lineId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->stationsOnLineService->getPage(
			userId: $userId,
			linesId: Uuid::fromString($lineId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/lines/{lineId}/stations_on_line',
		operationId: 'createStationOnLine',
		tags: ['station_on_line'],
		summary: '作成する',
		description: "指定のLineに属する StationOnLine を新しく作成する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'lineId',
				description: 'LineのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '作成するStationOnLineの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/StationOnLine'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/StationOnLine')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/StationOnLine'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/StationOnLine'),
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
	public function createStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($lineId)) {
			$this->logger->warning("Invalid UUID format ({lineId})", ['lineId' => $lineId]);
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

		$models = array_map(
			fn ($item) => $this->parseBodyToModel($item),
			$bodyList,
		);

		$createResult = $this->stationsOnLineService->create(
			linesId: Uuid::fromString($lineId),
			userId: $userId,
			dataList: $models,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/stations_on_line/{stationOnLineId}',
		operationId: 'getStationOnLine',
		tags: ['station_on_line'],
		summary: '1件取得する',
		description: "StationOnLine (路線上の駅) の情報を1件取得する\n\nこのデータが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationOnLineId',
				description: 'StationOnLineのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StationOnLine'),
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
	public function getStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationOnLineId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationOnLineId]);
			return Utils::withUuidError($response);
		}

		return $this->stationsOnLineService->getOne(
			userId: $userId,
			stationOnLineId: Uuid::fromString($stationOnLineId),
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/stations_on_line/{stationOnLineId}',
		operationId: 'updateStationOnLine',
		tags: ['station_on_line'],
		summary: '更新する',
		description: "既存の StationOnLine の情報を更新する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationOnLineId',
				description: 'StationOnLineのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新するStationOnLineの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/StationOnLine'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StationOnLine'),
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
	public function updateStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationOnLineId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationOnLineId]);
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

		$requestedKeys = array_keys(is_array($body) ? $body : (array)($body ?? []));

		return $this->stationsOnLineService->update(
			userId: $userId,
			stationOnLineId: Uuid::fromString($stationOnLineId),
			body: (object)$this->parseBodyToModel($body),
			requestedKeys: $requestedKeys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/stations_on_line/{stationOnLineId}',
		operationId: 'deleteStationOnLine',
		tags: ['station_on_line'],
		summary: '削除する',
		description: "既存の StationOnLine を削除する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationOnLineId',
				description: 'StationOnLineのID',
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
	public function deleteStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationOnLineId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationOnLineId]);
			return Utils::withUuidError($response);
		}

		return $this->stationsOnLineService->delete(
			userId: $userId,
			stationOnLineId: Uuid::fromString($stationOnLineId),
		)->getResponseWithJson($response);
	}

	/**
	 * Parse a raw body array/object into a StationOnLine-compatible object.
	 * Resolves location_lonlat from nested array/object to StationOnLineLocationLonlat.
	 */
	private function parseBodyToModel(mixed $body): object
	{
		$raw = Utils::getValueOrNull($body, 'location_lonlat');
		$lonlat = null;
		if (!is_null($raw)) {
			$lonlat = new StationOnLineLocationLonlat();
			$lonlat->setData([
				'longitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'longitude')),
				'latitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'latitude')),
			]);
		}

		$psId = Utils::getValueOrNull($body, 'project_stations_id');
		$locationM = Utils::getValueOrNull($body, 'location_m');
		$trackHidden = Utils::getValueOrNull($body, 'track_hidden_by_default');

		$obj = new \stdClass();
		$obj->project_stations_id = is_string($psId) && Uuid::isValid($psId)
			? Uuid::fromString($psId)
			: $psId;
		$obj->location_m = is_null($locationM) ? null : floatval($locationM);
		$obj->location_lonlat = $lonlat;
		$obj->track_hidden_by_default = is_null($trackHidden) ? null : boolval($trackHidden);
		return $obj;
	}
}
