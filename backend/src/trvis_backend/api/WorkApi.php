<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
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
 * Work CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Work is WorkGroup-rooted (work_groups_id FK). Privilege checks use
 * WorkGroupsPrivilegesRepo via WorksService.
 *
 * Parameters are INLINE (not $ref'd components) to keep the P3 fan-out
 * agent-independent. (PDO, LoggerInterface) are autowired by PHP-DI when
 * Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` pattern-lock.
 * No `?? UID_ANONYMOUS` after getUserIdOrAnonymous() — that null-coalesce is
 * dead in the new backend (see CONTRIBUTING-P3.md §7).
 */
class WorkApi
{
	public const REMARKS_MAX_LENGTH = 255;

	private readonly WorksService $worksService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->worksService = new WorksService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			RequestValidator::getDescriptionValidationRule(),
			new DateTimeValidationRule(
				key: 'affect_date',
				isNullable: true,
				isRequired: false,
				isDateOnly: true,
			),
			new EnumValidationRule(
				key: 'affix_content_type',
				isNullable: true,
				isRequired: false,
				className: TrvisContentType::class,
			),
			new StringValidationRule(
				key: 'affix_content',
				isNullable: true,
				isRequired: false,
				maxLength: Constants::TEXT_COLUMN_MAX_LENGTH,
			),
			new StringValidationRule(
				key: 'remarks',
				isNullable: true,
				isRequired: false,
				maxLength: self::REMARKS_MAX_LENGTH,
			),
			new BoolValidationRule(
				key: 'has_e_train_timetable',
				isNullable: true,
				isRequired: false,
			),
			new EnumValidationRule(
				key: 'e_train_timetable_content_type',
				isNullable: true,
				isRequired: false,
				className: TrvisContentType::class,
			),
			new StringValidationRule(
				key: 'e_train_timetable_content',
				isNullable: true,
				isRequired: false,
				maxLength: Constants::TEXT_COLUMN_MAX_LENGTH,
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
				'path' => '/work_groups/{workGroupId}/works',
				'handler' => [self::class, 'getWorkList'],
				'name' => 'getWorkList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}/works',
				'handler' => [self::class, 'createWork'],
				'name' => 'createWork',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/works/{workId}',
				'handler' => [self::class, 'getWork'],
				'name' => 'getWork',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/works/{workId}',
				'handler' => [self::class, 'updateWork'],
				'name' => 'updateWork',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/works/{workId}',
				'handler' => [self::class, 'deleteWork'],
				'name' => 'deleteWork',
			],
		];
	}

	#[OA\Get(
		path: '/work_groups/{workGroupId}/works',
		operationId: 'getWorkList',
		tags: ['work'],
		summary: '複数件取得する',
		description: "指定のWorkGroupに属する Work の情報を複数件取得する\n\nこのWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
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
					items: new OA\Items(ref: '#/components/schemas/Work'),
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
	public function getWorkList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->worksService->getPage(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/work_groups/{workGroupId}/works',
		operationId: 'createWork',
		tags: ['work'],
		summary: '作成する',
		description: "指定のWorkGroupに属する Work を新しく作成する\n\nこのWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '作成するWorkの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/Work'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/Work')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/Work'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/Work'),
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
	public function createWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
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
			$m = new Work();
			$m->setData((array)$item);
			return $m;
		}, $bodyList);

		$createResult = $this->worksService->create(
			workGroupsId: Uuid::fromString($workGroupId),
			userId: $userId,
			dataList: $modelList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/works/{workId}',
		operationId: 'getWork',
		tags: ['work'],
		summary: '1件取得する',
		description: "Work の情報を1件取得する\n\nこのデータが属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workId',
				description: 'WorkのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Work'),
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
	public function getWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workId)) {
			$this->logger->warning(
				"Invalid UUID format ({workId})",
				['workId' => $workId],
			);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($workId);
		$this->logger->debug("workId parsed: {workId}", ['workId' => $uuid]);
		return $this->worksService->getOne(
			userId: $userId,
			worksId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/works/{workId}',
		operationId: 'updateWork',
		tags: ['work'],
		summary: '更新する',
		description: "既存の Work を更新する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
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
			description: '更新後のWorkの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Work'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Work'),
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
	public function updateWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($workId)) {
			$this->logger->warning(
				"Invalid UUID format ({workId})",
				['workId' => $workId],
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

		return $this->worksService->update(
			userId: $userId,
			worksId: Uuid::fromString($workId),
			body: $bodyObj,
			requestedKeys: $requestedKeys,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/works/{workId}',
		operationId: 'deleteWork',
		tags: ['work'],
		summary: '削除する',
		description: "既存の Work を削除する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workId',
				description: 'WorkのID',
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
	public function deleteWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workId)) {
			$this->logger->warning(
				"Invalid UUID format ({workId})",
				['workId' => $workId],
			);
			return Utils::withUuidError($response);
		}
		return $this->worksService->delete(
			userId: $userId,
			worksId: Uuid::fromString($workId),
		)->getResponseWithJson($response);
	}
}
