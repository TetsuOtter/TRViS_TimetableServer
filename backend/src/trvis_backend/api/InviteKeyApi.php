<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKey;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\InviteKeysService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
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
 * InviteKey CRUD + use endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * InviteKey is a bearer-capability: possession of the UUID is sufficient to
 * use it. Read/disable/list are gated behind WorkGroup `admin`.
 *
 * updateInviteKey is intentionally NOT implemented (returns 501); there is no
 * corresponding InviteKeysService method — this is the deliberate contract
 * documented by the legacy test.
 *
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file. (PDO, LoggerInterface) are autowired by
 * PHP-DI when Slim resolves the route callable.
 *
 * §7: for endpoints that legitimately use getUserIdOrAnonymous it returns a
 * non-null string in the new backend, so the dead `?? Constants::UID_ANONYMOUS`
 * null-coalesce is dropped. Token-required endpoints (getMyInviteKeyList,
 * useInviteKey, createInviteKey) still use getUserIdOrNull + null check and
 * return 401 — that is NOT a dead guard.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` invariant.
 */
class InviteKeyApi
{
	private readonly InviteKeysService $inviteKeysService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->inviteKeysService = new InviteKeysService($db, $logger);

		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			new DateTimeValidationRule(
				key: 'valid_from',
				isDateOnly: false,
				isRequired: false,
				isNullable: true,
			),
			new DateTimeValidationRule(
				key: 'expires_at',
				isDateOnly: false,
				isRequired: false,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'use_limit',
				isRequired: false,
				isNullable: true,
				minValue: 1,
			),
			new EnumValidationRule(
				key: 'privilege_type',
				className: InviteKeyPrivilegeType::class,
				isRequired: true,
				isNullable: false,
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
				'path' => '/invite_keys',
				'handler' => [self::class, 'getMyInviteKeyList'],
				'name' => 'getMyInviteKeyList',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/invite_keys/{inviteKeyId}',
				'handler' => [self::class, 'getInviteKey'],
				'name' => 'getInviteKey',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/invite_keys/{inviteKeyId}',
				'handler' => [self::class, 'deleteInviteKey'],
				'name' => 'deleteInviteKey',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/invite_keys/{inviteKeyId}',
				'handler' => [self::class, 'updateInviteKey'],
				'name' => 'updateInviteKey',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/invite_keys/{inviteKeyId}',
				'handler' => [self::class, 'useInviteKey'],
				'name' => 'useInviteKey',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}/invite_keys',
				'handler' => [self::class, 'getInviteKeyList'],
				'name' => 'getInviteKeyList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}/invite_keys',
				'handler' => [self::class, 'createInviteKey'],
				'name' => 'createInviteKey',
			],
		];
	}

	#[OA\Get(
		path: '/invite_keys',
		operationId: 'getMyInviteKeyList',
		tags: ['invite_key'],
		summary: '一覧を取得する',
		description: "自身が作成した (オーナーな) 招待キーの一覧を取得する\n\n実行にはサインインが必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
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
			new OA\QueryParameter(
				name: 'expired',
				description: '期限切れ・無効化済みのキーを含めるか',
				required: false,
				schema: new OA\Schema(type: 'boolean'),
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
					items: new OA\Items(ref: '#/components/schemas/InviteKey'),
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
		],
	)]
	public function getMyInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!is_null($p)) {
			if (!is_numeric($p)) {
				$message = "Invalid type for parameter p, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$p = intval($p);
			if ($p < Constants::PAGE_MIN_VALUE) {
				$message = sprintf(
					"Invalid value for parameter p, must be greater than or equal to %d.",
					Constants::PAGE_MIN_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$p = Constants::PAGE_DEFAULT_VALUE;
		}

		if (!is_null($limit)) {
			if (!is_numeric($limit)) {
				$message = "Invalid type for parameter limit, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$limit = intval($limit);
			if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit) {
				$message = sprintf(
					"Invalid value for parameter limit, must be between %d and %d.",
					Constants::PER_PAGE_MIN_VALUE,
					Constants::PER_PAGE_MAX_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$limit = Constants::PER_PAGE_DEFAULT_VALUE;
		}

		if (!is_null($top) && !Uuid::isValid($top)) {
			$this->logger->warning("Invalid UUID format ({top})", ['top' => $top]);
			return Utils::withUuidError($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithOwnerUid(
			ownerUserId: $userId,
			page: $p,
			perPage: $limit,
			topId: is_null($top) ? null : Uuid::fromString($top),
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/invite_keys/{inviteKeyId}',
		operationId: 'getInviteKey',
		tags: ['invite_key'],
		summary: '1件取得する',
		description: "招待キーの情報を1件取得する\n\n削除された招待キーは取得できませんが、無効化された招待キーは取得できます。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'inviteKeyId',
				description: 'InviteKeyのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/InviteKey'),
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
	public function getInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($inviteKeyId)) {
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->selectInviteKey(
			inviteKeyId: Uuid::fromString($inviteKeyId),
			userId: $userId,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/invite_keys/{inviteKeyId}',
		operationId: 'deleteInviteKey',
		tags: ['invite_key'],
		summary: '無効化する',
		description: "指定の招待キーを無効化する。\n\nなお、この操作はキーが有効な間だけ行える。\n\n指定のWorkGroupへのADMIN権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'inviteKeyId',
				description: 'InviteKeyのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '無効化成功',
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
	public function deleteInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($inviteKeyId)) {
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->disableInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/invite_keys/{inviteKeyId}',
		operationId: 'updateInviteKey',
		tags: ['invite_key'],
		summary: '(未実装) 更新する',
		description: "既存の招待キーを更新する\n\n指定のWorkGroupへのADMIN権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'inviteKeyId',
				description: 'InviteKeyのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新する招待キーの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/InviteKey'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/InviteKey'),
			),
			new OA\Response(
				response: 501,
				description: '未実装',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function updateInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$this->logger->warning("updateInviteKey not implemented yet");
		return Utils::withError($response, Constants::HTTP_NOT_IMPLEMENTED, "updateInviteKey not implemented yet");
	}

	#[OA\Post(
		path: '/invite_keys/{inviteKeyId}',
		operationId: 'useInviteKey',
		tags: ['invite_key'],
		summary: '使用する',
		description: "招待キーを使用する\n\nサインインが必須です。サインインしたユーザに対して、この招待キーに割り当てられた権限が付与されます。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'inviteKeyId',
				description: 'InviteKeyのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '成功',
				content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup'),
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
	public function useInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		if (!Uuid::isValid($inviteKeyId)) {
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->useInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/work_groups/{workGroupId}/invite_keys',
		operationId: 'getInviteKeyList',
		tags: ['invite_key'],
		summary: '一覧を取得する',
		description: "指定のWorkGroupに属する招待キーの一覧を取得する\n\n指定のWorkGroupへのADMIN権限が必要です。",
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
			new OA\QueryParameter(
				name: 'expired',
				description: '期限切れ・無効化済みのキーを含めるか',
				required: false,
				schema: new OA\Schema(type: 'boolean'),
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
					items: new OA\Items(ref: '#/components/schemas/InviteKey'),
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
	public function getInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithWorkGroupsId(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			page: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/work_groups/{workGroupId}/invite_keys',
		operationId: 'createInviteKey',
		tags: ['invite_key'],
		summary: '作成する',
		description: "指定のWorkGroupに属する招待キーを新規に作成する\n\n指定のWorkGroupへのADMIN権限が必要です。",
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
			description: '作成する招待キーの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/InviteKey'),
		),
		responses: [
			new OA\Response(
				response: 201,
				description: '作成成功',
				content: new OA\JsonContent(ref: '#/components/schemas/InviteKey'),
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
	public function createInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
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

		$inviteKey = new InviteKey();
		$inviteKey->setData($body);
		if ($inviteKey->privilege_type->value <= InviteKeyPrivilegeType::none->value) {
			$message = "Invalid value for parameter privilege_type, must be greater than " . InviteKeyPrivilegeType::none->name;
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}

		return $this->inviteKeysService->createInviteKey(
			owner: $userId,
			workGroupId: Uuid::fromString($workGroupId),
			inviteKey: $inviteKey,
		)->getResponseWithJson($response);
	}
}
