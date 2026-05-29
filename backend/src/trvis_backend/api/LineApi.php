<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Line CRUD endpoints. Standalone (no generated Abstract* parent): the
 * #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Line belongs to a Project — privilege root is direct via projects_id.
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file. (PDO, LoggerInterface) are autowired by
 * PHP-DI when Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` invariant
 * (name === operationId — RouteSmokeTest contract).
 */
class LineApi
{
	private readonly LineService $lineService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->lineService = new LineService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			RequestValidator::getNameValidationRule(),
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
				'path' => '/projects/{projectId}/lines',
				'handler' => [self::class, 'getLineList'],
				'name' => 'getLineList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/lines',
				'handler' => [self::class, 'createLine'],
				'name' => 'createLine',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/lines/{lineId}',
				'handler' => [self::class, 'getLine'],
				'name' => 'getLine',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/lines/{lineId}',
				'handler' => [self::class, 'updateLine'],
				'name' => 'updateLine',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/lines/{lineId}',
				'handler' => [self::class, 'deleteLine'],
				'name' => 'deleteLine',
			],
		];
	}

	#[OA\Get(
		path: '/projects/{projectId}/lines',
		operationId: 'getLineList',
		tags: ['line'],
		summary: '複数件取得する',
		description: "指定のProjectに属する Line の情報を複数件取得する\n\nこのProjectへのREAD権限が必要です。",
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
					items: new OA\Items(ref: '#/components/schemas/Line'),
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
	public function getLineList(
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

		return $this->lineService->getPage(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/{projectId}/lines',
		operationId: 'createLine',
		tags: ['line'],
		summary: '作成する',
		description: "指定のProjectに属する Line を新しく作成する\n\nこのProjectへのWRITE権限が必要です。",
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
			description: '作成するLineの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Line'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '作成成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Line'),
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
	public function createLine(
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

		$r = $this->lineService->create(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			name: Utils::getValueOrNull($body, 'name'),
			description: Utils::getValueOrNull($body, 'description'),
		);
		if (!$r->isError) {
			$r = RetValueOrError::withValue($r->value);
		}
		return $r->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/lines/{lineId}',
		operationId: 'getLine',
		tags: ['line'],
		summary: '1件取得する',
		description: "Line の情報を1件取得する\n\nこのLineが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'lineId',
				description: 'LineのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Line'),
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
	public function getLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($lineId)) {
			$this->logger->warning("Invalid UUID format ({lineId})", ['lineId' => $lineId]);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($lineId);
		$this->logger->debug("lineId parsed: {lineId}", ['lineId' => $uuid]);
		return $this->lineService->getOne(
			userId: $userId,
			lineId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/lines/{lineId}',
		operationId: 'updateLine',
		tags: ['line'],
		summary: '更新する',
		description: "既存の Line を更新する\n\nこのLineが属するProjectへのWRITE権限が必要です。",
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
			description: '更新後のLineの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Line'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Line'),
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
	public function updateLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($lineId)) {
			$this->logger->warning("Invalid UUID format ({lineId})", ['lineId' => $lineId]);
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

		$keys = is_array($body) ? array_keys($body) : [];
		return $this->lineService->update(
			userId: $userId,
			lineId: Uuid::fromString($lineId),
			patch: (object)($body ?? []),
			keys: $keys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/lines/{lineId}',
		operationId: 'deleteLine',
		tags: ['line'],
		summary: '削除する',
		description: "既存の Line を削除する\n\nこのLineが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'lineId',
				description: 'LineのID',
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
	public function deleteLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($lineId)) {
			$this->logger->warning("Invalid UUID format ({lineId})", ['lineId' => $lineId]);
			return Utils::withUuidError($response);
		}
		// getUserIdOrAnonymous() returns a non-null string in the new backend
		// (anonymous => Constants::UID_ANONYMOUS), so no `?? UID_ANONYMOUS`
		// fallback is needed here — unlike the legacy nullable-uid signature.
		return $this->lineService->delete(
			userId: $userId,
			lineId: Uuid::fromString($lineId),
		)->getResponseWithJson($response);
	}
}
