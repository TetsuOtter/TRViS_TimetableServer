<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\IntValidationRule;
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
 * StopPattern CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * StopPattern is Project-rooted directly (projects_id FK). Privilege checks
 * use ProjectsPrivilegesRepo via StopPatternsService.
 *
 * Parameters are INLINE (not $ref'd components) to keep the P3 fan-out
 * agent-independent. (PDO, LoggerInterface) are autowired by PHP-DI when
 * Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` pattern-lock.
 * No `?? UID_ANONYMOUS` after getUserIdOrAnonymous() — that null-coalesce is
 * dead in the new backend (see CONTRIBUTING-P3.md §7).
 */
class StopPatternApi
{
	private readonly StopPatternsService $stopPatternsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stopPatternsService = new StopPatternsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			new UuidValidationRule(
				key: 'lines_id',
				isNullable: false,
				isRequired: true,
			),
			new UuidValidationRule(
				key: 'from_project_stations_id',
				isNullable: true,
				isRequired: false,
			),
			new UuidValidationRule(
				key: 'to_project_stations_id',
				isNullable: true,
				isRequired: false,
			),
			new IntValidationRule(
				key: 'direction',
				minValue: -1,
				maxValue: 1,
				isNullable: false,
				isRequired: false,
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
				'path' => '/projects/{projectId}/stop_patterns',
				'handler' => [self::class, 'getStopPatternList'],
				'name' => 'getStopPatternList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/stop_patterns',
				'handler' => [self::class, 'createStopPattern'],
				'name' => 'createStopPattern',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/stop_patterns/{stopPatternId}',
				'handler' => [self::class, 'getStopPattern'],
				'name' => 'getStopPattern',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/stop_patterns/{stopPatternId}',
				'handler' => [self::class, 'updateStopPattern'],
				'name' => 'updateStopPattern',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/stop_patterns/{stopPatternId}',
				'handler' => [self::class, 'deleteStopPattern'],
				'name' => 'deleteStopPattern',
			],
		];
	}

	#[OA\Get(
		path: '/projects/{projectId}/stop_patterns',
		operationId: 'getStopPatternList',
		tags: ['stop_pattern'],
		summary: '複数件取得する',
		description: "指定のProjectに属する StopPattern の情報を複数件取得する\n\nこのProjectへのREAD権限が必要です。",
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
					items: new OA\Items(ref: '#/components/schemas/StopPattern'),
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
	public function getStopPatternList(
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

		return $this->stopPatternsService->getPage(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/{projectId}/stop_patterns',
		operationId: 'createStopPattern',
		tags: ['stop_pattern'],
		summary: '作成する',
		description: "指定のProjectに属する StopPattern を新しく作成する\n\nこのProjectへのWRITE権限が必要です。",
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
			description: '作成するStopPatternの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/StopPattern'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/StopPattern')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPattern'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/StopPattern'),
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
	public function createStopPattern(
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
			$m = new StopPattern();
			$m->setData((array)$item);
			return $m;
		}, $bodyList);

		$createResult = $this->stopPatternsService->create(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			dataList: $modelList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/stop_patterns/{stopPatternId}',
		operationId: 'getStopPattern',
		tags: ['stop_pattern'],
		summary: '1件取得する',
		description: "StopPattern の情報を1件取得する\n\nこのデータが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternId',
				description: 'StopPatternのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPattern'),
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
	public function getStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stopPatternId)) {
			$this->logger->warning(
				"Invalid UUID format ({stopPatternId})",
				['stopPatternId' => $stopPatternId],
			);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($stopPatternId);
		$this->logger->debug("stopPatternId parsed: {stopPatternId}", ['stopPatternId' => $uuid]);
		return $this->stopPatternsService->getOne(
			userId: $userId,
			stopPatternId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/stop_patterns/{stopPatternId}',
		operationId: 'updateStopPattern',
		tags: ['stop_pattern'],
		summary: '更新する',
		description: "既存の StopPattern を更新する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternId',
				description: 'StopPatternのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後のStopPatternの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/StopPattern'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPattern'),
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
	public function updateStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($stopPatternId)) {
			$this->logger->warning(
				"Invalid UUID format ({stopPatternId})",
				['stopPatternId' => $stopPatternId],
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

		return $this->stopPatternsService->update(
			userId: $userId,
			stopPatternId: Uuid::fromString($stopPatternId),
			body: $bodyObj,
			requestedKeys: $requestedKeys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/stop_patterns/{stopPatternId}',
		operationId: 'deleteStopPattern',
		tags: ['stop_pattern'],
		summary: '削除する',
		description: "既存の StopPattern を削除する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternId',
				description: 'StopPatternのID',
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
	public function deleteStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stopPatternId)) {
			$this->logger->warning(
				"Invalid UUID format ({stopPatternId})",
				['stopPatternId' => $stopPatternId],
			);
			return Utils::withUuidError($response);
		}
		// getUserIdOrAnonymous() returns a non-null string in the new backend
		// (anonymous => Constants::UID_ANONYMOUS), so no `?? UID_ANONYMOUS`
		// fallback is needed here — unlike the legacy nullable-uid signature.
		return $this->stopPatternsService->delete(
			userId: $userId,
			stopPatternId: Uuid::fromString($stopPatternId),
		)->getResponseWithJson($response);
	}
}
