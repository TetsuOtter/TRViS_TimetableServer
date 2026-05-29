<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Train CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Train is Work-rooted (works_id FK). Privilege checks use WorksRepo
 * (parent) for create/getPage, TrainsRepo (target) for getOne/update/delete.
 *
 * Parameters are INLINE (not $ref'd components) to keep the P3 fan-out
 * agent-independent. (PDO, LoggerInterface) are autowired by PHP-DI when
 * Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` pattern-lock.
 * No `?? UID_ANONYMOUS` after getUserIdOrAnonymous() — dead in the new backend
 * (see CONTRIBUTING-P3.md §7).
 */
class TrainApi
{
	private readonly TrainsService $trainsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->trainsService = new TrainsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			new StringValidationRule(
				key: 'train_number',
				minLength: Constants::NAME_MIN_LENGTH,
				maxLength: Constants::NAME_MAX_LENGTH,
				isRequired: true,
				isNullable: false,
			),
			new StringValidationRule(
				key: 'max_speed',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'speed_type',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'nominal_tractive_capacity',
				maxLength: 255,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'car_count',
				isNullable: true,
			),
			new StringValidationRule(
				key: 'destination',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'begin_remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'after_remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'before_departure',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'after_arrive',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'train_info',
				maxLength: 255,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'direction',
				isRequired: true,
				isNullable: false,
			),
			new IntValidationRule(
				key: 'day_count',
				minValue: 0,
				isRequired: true,
				isNullable: false,
			),
			new BoolValidationRule(
				key: 'is_ride_on_moving',
				isNullable: true,
			),
		);
	}

	/**
	 * Operation descriptors for RegisterRoutes. `basePath` + `path` must
	 * agree byte-for-byte with the #[OA\*] `path:` (server base is /api/v1).
	 * `name` must equal the operationId (RouteSmokeTest invariant).
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
				'path' => '/works/{workId}/trains',
				'handler' => [self::class, 'getTrainList'],
				'name' => 'getTrainList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/works/{workId}/trains',
				'handler' => [self::class, 'createTrain'],
				'name' => 'createTrain',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/trains/{trainId}',
				'handler' => [self::class, 'getTrain'],
				'name' => 'getTrain',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/trains/{trainId}',
				'handler' => [self::class, 'updateTrain'],
				'name' => 'updateTrain',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/trains/{trainId}',
				'handler' => [self::class, 'deleteTrain'],
				'name' => 'deleteTrain',
			],
		];
	}

	#[OA\Get(
		path: '/works/{workId}/trains',
		operationId: 'getTrainList',
		tags: ['train'],
		summary: '複数件取得する',
		description: "指定のWorkに属する Train (列車) の情報を複数件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workId',
				description: 'WorkのID',
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
					items: new OA\Items(ref: '#/components/schemas/Train'),
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
	public function getTrainList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workId)) {
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->trainsService->getPage(
			userId: $userId,
			worksId: Uuid::fromString($workId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/works/{workId}/trains',
		operationId: 'createTrain',
		tags: ['train'],
		summary: '作成する',
		description: "指定のWorkに属する Train (列車) を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workId',
				description: 'WorkのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '作成するTrainの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/Train'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/Train')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/Train'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/Train'),
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
	public function createTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($workId)) {
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		// Body may be a single object or an array of objects
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

		$modelList = array_map(function ($item) {
			$m = new Train();
			$m->setData((array)$item);
			return $m;
		}, $bodyList);

		$createResult = $this->trainsService->create(
			worksId: Uuid::fromString($workId),
			userId: $userId,
			dataList: $modelList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/trains/{trainId}',
		operationId: 'getTrain',
		tags: ['train'],
		summary: '1件取得する',
		description: "Train (列車) の情報を1件取得する\n\nこのデータが属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'trainId',
				description: 'TrainのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Train'),
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
	public function getTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($trainId)) {
			$this->logger->warning(
				"Invalid UUID format ({trainId})",
				['trainId' => $trainId],
			);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($trainId);
		$this->logger->debug("trainId parsed: {trainId}", ['trainId' => $uuid]);
		return $this->trainsService->getOne(
			userId: $userId,
			trainsId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/trains/{trainId}',
		operationId: 'updateTrain',
		tags: ['train'],
		summary: '更新する',
		description: "既存のTrainの情報を更新する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'trainId',
				description: 'TrainのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後のTrainの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Train'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Train'),
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
	public function updateTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($trainId)) {
			$this->logger->warning(
				"Invalid UUID format ({trainId})",
				['trainId' => $trainId],
			);
			return Utils::withUuidError($response);
		}

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

		// Build list of keys that were present in the request body
		$requestedKeys = is_array($body)
			? array_keys($body)
			: array_keys(get_object_vars($body));

		$bodyObj = is_array($body) ? (object)$body : $body;

		return $this->trainsService->update(
			userId: $userId,
			trainsId: Uuid::fromString($trainId),
			body: $bodyObj,
			requestedKeys: $requestedKeys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/trains/{trainId}',
		operationId: 'deleteTrain',
		tags: ['train'],
		summary: '削除する',
		description: "既存のTrainを削除する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'trainId',
				description: 'TrainのID',
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
	public function deleteTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($trainId)) {
			$this->logger->warning(
				"Invalid UUID format ({trainId})",
				['trainId' => $trainId],
			);
			return Utils::withUuidError($response);
		}
		return $this->trainsService->delete(
			userId: $userId,
			trainsId: Uuid::fromString($trainId),
		)->getResponseWithJson($response);
	}
}
