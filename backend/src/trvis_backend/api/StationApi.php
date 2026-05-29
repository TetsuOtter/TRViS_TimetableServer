<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Station CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Privilege root is Project (direct via projects_id); no WG bridge. This is
 * the consolidated station model — the former work-group-rooted `stations`
 * was abolished and the project-rooted `project_stations` renamed to
 * `stations`.
 *
 * `location_km` / `record_type` are optional on create/update (defaulting to
 * 0 / normal); they are carried for the TRViS-JSON dump (Location_m /
 * RecordType).
 *
 * operationId + route `name` are normalized to `<verb><Entity><Action>`
 * per CONTRIBUTING-P3.md §4 naming invariant (name === operationId).
 */
class StationApi
{
	private readonly StationsService $stationsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsService = new StationsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			new StringValidationRule(
				key: 'full_name',
				isNullable: true,
				isRequired: false,
				maxLength: Constants::NAME_MAX_LENGTH,
			),
			new FloatValidationRule(
				key: 'location_km',
				isNullable: false,
				isRequired: false,
			),
			new LonLatValidationRule(
				key: 'location_lonlat',
				isNullable: true,
				isRequired: false,
			),
			new FloatValidationRule(
				key: 'on_station_detect_radius_m',
				minValue: 0.0,
				isNullable: true,
				isRequired: false,
			),
			new EnumValidationRule(
				key: 'record_type',
				isNullable: false,
				isRequired: false,
				className: StationRecordType::class,
			),
			new BoolValidationRule(
				key: 'always_show_hh',
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
				'path' => '/projects/{projectId}/stations',
				'handler' => [self::class, 'getStationList'],
				'name' => 'getStationList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/stations',
				'handler' => [self::class, 'createStation'],
				'name' => 'createStation',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/stations/{stationId}',
				'handler' => [self::class, 'getStation'],
				'name' => 'getStation',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/stations/{stationId}',
				'handler' => [self::class, 'updateStation'],
				'name' => 'updateStation',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/stations/{stationId}',
				'handler' => [self::class, 'deleteStation'],
				'name' => 'deleteStation',
			],
		];
	}

	#[OA\Get(
		path: '/projects/{projectId}/stations',
		operationId: 'getStationList',
		tags: ['station'],
		summary: '複数件取得する',
		description: '指定のProjectに属する Station (Project内共通の駅) の情報を複数件取得する  このProjectへのREAD権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
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
					items: new OA\Items(ref: '#/components/schemas/Station'),
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
	public function getStationList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->stationsService->selectStationPage(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/{projectId}/stations',
		operationId: 'createStation',
		tags: ['station'],
		summary: '作成する',
		description: '指定のProjectに属する Station を新しく作成する  このProjectへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '作成するStationの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Station'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '作成成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Station'),
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
	public function createStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: false,
		);
		if ($validateResult->isError) {
			$this->logger->warning(
				"Invalid request body: {msg}",
				['msg' => $validateResult->errorMsg],
			);
			return $validateResult->getResponseWithJson($response);
		}

		$lonlat = $this->parseLonlat($body);
		$locationKm = Utils::floatvalOrNull(Utils::getValueOrNull($body, 'location_km')) ?? 0.0;
		$recordType = StationRecordType::fromOrNull(Utils::getValueOrNull($body, 'record_type'))
			?? StationRecordType::normal;

		$r = $this->stationsService->createStation(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			name: Utils::getValueOrNull($body, 'name'),
			fullName: Utils::getValueOrNull($body, 'full_name'),
			locationKm: $locationKm,
			locationLonlat: $lonlat,
			onStationDetectRadiusM: Utils::floatvalOrNull(Utils::getValueOrNull($body, 'on_station_detect_radius_m')),
			recordType: $recordType,
			alwaysShowHh: boolval(Utils::getValueOrNull($body, 'always_show_hh')),
		);
		if (!$r->isError) {
			$r = RetValueOrError::withValue($r->value);
		}
		return $r->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/stations/{stationId}',
		operationId: 'getStation',
		tags: ['station'],
		summary: '1件取得する',
		description: 'Station の情報を1件取得する  このデータが属するProjectへのREAD権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationId',
				description: 'StationのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Station'),
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
	public function getStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationId]);
			return Utils::withUuidError($response);
		}

		return $this->stationsService->selectStationOne(
			stationsId: Uuid::fromString($stationId),
			userId: $userId,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/stations/{stationId}',
		operationId: 'updateStation',
		tags: ['station'],
		summary: '更新する',
		description: '既存の Station の情報を更新する  このデータが属するProjectへのWRITE権限が必要です。',
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
			description: '更新するStationの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Station'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Station'),
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
	public function updateStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationId]);
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
			? fn(string $key): bool => array_key_exists($key, $body)
			: fn(string $key): bool => property_exists($body ?? (object)[], $key);

		$hasLocationKm = $checkPropExists('location_km');
		$locationKm = $hasLocationKm
			? Utils::floatvalOrNull(Utils::getValueOrNull($body, 'location_km'))
			: null;

		$hasLonlat = $checkPropExists('location_lonlat');
		$lonlat = $hasLonlat ? $this->parseLonlat($body) : null;

		$hasRadius = $checkPropExists('on_station_detect_radius_m');
		$radius = $hasRadius
			? Utils::floatvalOrNull(Utils::getValueOrNull($body, 'on_station_detect_radius_m'))
			: null;

		$hasRecordType = $checkPropExists('record_type');
		$recordType = $hasRecordType
			? StationRecordType::fromOrNull(Utils::getValueOrNull($body, 'record_type'))
			: null;

		$hasAlwaysShowHh = $checkPropExists('always_show_hh');
		$alwaysShowHh = $hasAlwaysShowHh
			? boolval(Utils::getValueOrNull($body, 'always_show_hh'))
			: null;

		return $this->stationsService->updateStation(
			stationsId: Uuid::fromString($stationId),
			userId: $userId,
			name: Utils::getValueOrNull($body, 'name'),
			fullName: Utils::getValueOrNull($body, 'full_name'),
			locationKm: $locationKm,
			hasLocationKm: $hasLocationKm,
			locationLonlat: $lonlat,
			hasLocationLonlat: $hasLonlat,
			onStationDetectRadiusM: $radius,
			hasOnStationDetectRadiusM: $hasRadius,
			recordType: $recordType,
			alwaysShowHh: $alwaysShowHh,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/stations/{stationId}',
		operationId: 'deleteStation',
		tags: ['station'],
		summary: '削除する',
		description: '既存の Station を削除する  このデータが属するProjectへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stationId',
				description: 'StationのID',
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
	public function deleteStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stationId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stationId]);
			return Utils::withUuidError($response);
		}

		return $this->stationsService->deleteStation(
			stationsId: Uuid::fromString($stationId),
			userId: $userId,
		)->getResponseWithJson($response);
	}

	/**
	 * Parse location_lonlat from request body, returning null if absent or null.
	 */
	private function parseLonlat(mixed $body): ?StationLocationLonlat
	{
		$raw = Utils::getValueOrNull($body, 'location_lonlat');
		if (is_null($raw)) {
			return null;
		}
		$ll = new StationLocationLonlat();
		$ll->setData([
			'longitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'longitude')),
			'latitude' => Utils::floatvalOrNull(Utils::getValueOrNull($raw, 'latitude')),
		]);
		return $ll;
	}
}
