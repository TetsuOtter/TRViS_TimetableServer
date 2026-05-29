<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StopPatternRowsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * StopPatternRow CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * StopPatternRow is StopPattern-child. Privilege root is project-rooted via
 * stop_pattern_rows.projects_id (populated at INSERT via subquery from
 * stop_patterns). Privilege checks do NOT go via StopPatternsRepo.
 *
 * Parameters are INLINE (not $ref'd components) to keep the P3 fan-out
 * agent-independent. (PDO, LoggerInterface) are autowired by PHP-DI when
 * Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` pattern-lock.
 * No `?? UID_ANONYMOUS` after getUserIdOrAnonymous() — that null-coalesce is
 * dead in the new backend (see CONTRIBUTING-P3.md §7).
 */
class StopPatternRowApi
{
	private readonly StopPatternRowsService $stopPatternRowsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stopPatternRowsService = new StopPatternRowsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			new UuidValidationRule(
				key: 'project_stations_id',
				isNullable: false,
				isRequired: true,
			),
			new IntValidationRule(
				key: 'sort_key',
				minValue: 0,
				isNullable: false,
				isRequired: false,
			),
			new StringValidationRule(
				key: 'track_name',
				isNullable: true,
				isRequired: false,
				maxLength: Constants::NAME_MAX_LENGTH,
			),
			new BoolValidationRule(key: 'track_hidden', isNullable: false, isRequired: false),
			new BoolValidationRule(key: 'is_operation_only_stop', isNullable: false, isRequired: false),
			new BoolValidationRule(key: 'is_pass', isNullable: false, isRequired: false),
			new IntValidationRule(key: 'drive_time_mm', minValue: 0, maxValue: 99, isNullable: true, isRequired: false),
			new IntValidationRule(key: 'drive_time_ss', minValue: 0, maxValue: 59, isNullable: true, isRequired: false),
			new IntValidationRule(key: 'dwell_time_mm', minValue: 0, maxValue: 99, isNullable: true, isRequired: false),
			new IntValidationRule(key: 'dwell_time_ss', minValue: 0, maxValue: 59, isNullable: true, isRequired: false),
			new BoolValidationRule(key: 'show_arrive', isNullable: false, isRequired: false),
			new BoolValidationRule(key: 'show_departure', isNullable: false, isRequired: false),
			new StringValidationRule(key: 'arrive_str', isNullable: true, isRequired: false, maxLength: Constants::NAME_MAX_LENGTH),
			new StringValidationRule(key: 'departure_str', isNullable: true, isRequired: false, maxLength: Constants::NAME_MAX_LENGTH),
			new IntValidationRule(key: 'run_in_limit', minValue: 0, isNullable: true, isRequired: false),
			new IntValidationRule(key: 'run_out_limit', minValue: 0, isNullable: true, isRequired: false),
			new StringValidationRule(key: 'remarks', isNullable: true, isRequired: false, maxLength: Constants::TEXT_COLUMN_MAX_LENGTH),
			new BoolValidationRule(key: 'always_show_hh', isNullable: false, isRequired: false),
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
				'path' => '/stop_patterns/{stopPatternId}/rows',
				'handler' => [self::class, 'getStopPatternRowList'],
				'name' => 'getStopPatternRowList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/stop_patterns/{stopPatternId}/rows',
				'handler' => [self::class, 'createStopPatternRow'],
				'name' => 'createStopPatternRow',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/stop_pattern_rows/{stopPatternRowId}',
				'handler' => [self::class, 'getStopPatternRow'],
				'name' => 'getStopPatternRow',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/stop_pattern_rows/{stopPatternRowId}',
				'handler' => [self::class, 'updateStopPatternRow'],
				'name' => 'updateStopPatternRow',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/stop_pattern_rows/{stopPatternRowId}',
				'handler' => [self::class, 'deleteStopPatternRow'],
				'name' => 'deleteStopPatternRow',
			],
		];
	}

	#[OA\Get(
		path: '/stop_patterns/{stopPatternId}/rows',
		operationId: 'getStopPatternRowList',
		tags: ['stop_pattern_row'],
		summary: '複数件取得する',
		description: "指定のStopPatternに属する StopPatternRow (停車パターンの1行) の情報を複数件取得する\n\nこのデータが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternId',
				description: 'StopPatternのID',
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
					items: new OA\Items(ref: '#/components/schemas/StopPatternRow'),
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
	public function getStopPatternRowList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stopPatternId)) {
			$this->logger->warning("Invalid UUID format ({stopPatternId})", ['stopPatternId' => $stopPatternId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->stopPatternRowsService->getPage(
			userId: $userId,
			stopPatternsId: Uuid::fromString($stopPatternId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/stop_patterns/{stopPatternId}/rows',
		operationId: 'createStopPatternRow',
		tags: ['stop_pattern_row'],
		summary: '作成する',
		description: "指定のStopPatternに属する StopPatternRow を新しく作成する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
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
			description: '作成するStopPatternRowの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/StopPatternRow'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/StopPatternRow')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPatternRow'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/StopPatternRow'),
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
	public function createStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($stopPatternId)) {
			$this->logger->warning("Invalid UUID format ({stopPatternId})", ['stopPatternId' => $stopPatternId]);
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

		$modelList = array_map(function ($item) {
			$m = new StopPatternRow();
			$m->setData((array)$item);
			return $m;
		}, $bodyList);

		$createResult = $this->stopPatternRowsService->create(
			stopPatternsId: Uuid::fromString($stopPatternId),
			userId: $userId,
			dataList: $modelList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/stop_pattern_rows/{stopPatternRowId}',
		operationId: 'getStopPatternRow',
		tags: ['stop_pattern_row'],
		summary: '1件取得する',
		description: "StopPatternRow (停車パターンの1行) の情報を1件取得する\n\nこのデータが属するProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternRowId',
				description: 'StopPatternRowのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPatternRow'),
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
	public function getStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stopPatternRowId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stopPatternRowId]);
			return Utils::withUuidError($response);
		}

		return $this->stopPatternRowsService->getOne(
			userId: $userId,
			stopPatternRowId: Uuid::fromString($stopPatternRowId),
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/stop_pattern_rows/{stopPatternRowId}',
		operationId: 'updateStopPatternRow',
		tags: ['stop_pattern_row'],
		summary: '更新する',
		description: "既存の StopPatternRow の情報を更新する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternRowId',
				description: 'StopPatternRowのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後のStopPatternRowの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/StopPatternRow'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/StopPatternRow'),
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
	public function updateStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($stopPatternRowId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stopPatternRowId]);
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

		$requestedKeys = is_array($body)
			? array_keys($body)
			: array_keys(get_object_vars($body));

		$bodyObj = is_array($body) ? (object)$body : $body;

		return $this->stopPatternRowsService->update(
			userId: $userId,
			stopPatternRowId: Uuid::fromString($stopPatternRowId),
			body: $bodyObj,
			requestedKeys: $requestedKeys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/stop_pattern_rows/{stopPatternRowId}',
		operationId: 'deleteStopPatternRow',
		tags: ['stop_pattern_row'],
		summary: '削除する',
		description: "既存の StopPatternRow を削除する\n\nこのデータが属するProjectへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'stopPatternRowId',
				description: 'StopPatternRowのID',
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
	public function deleteStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($stopPatternRowId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $stopPatternRowId]);
			return Utils::withUuidError($response);
		}

		return $this->stopPatternRowsService->delete(
			userId: $userId,
			stopPatternRowId: Uuid::fromString($stopPatternRowId),
		)->getResponseWithJson($response);
	}
}
