<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * WorkGroup CRUD + privilege + Project-scoped endpoints. Standalone (no
 * generated Abstract* parent): the #[OA\*] attributes below are the source
 * of truth swagger-php reads; routes() feeds RegisterRoutes' FQCN registry.
 *
 * Body/paging logic is a faithful port of the legacy WorkGroupApi; the
 * Allman braces were reshaped to PSR12 (same-line for control structures).
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file. (PDO, LoggerInterface) are autowired by
 * PHP-DI when Slim resolves the route callable.
 *
 * operationId + route `name` are normalized to `<verb><Entity><Action>` even
 * where the legacy spec used a bare name (`getPrivilege`/`updatePrivilege` ->
 * `getWorkGroupPrivilege`/`updateWorkGroupPrivilege`): this keeps the
 * ProjectApi pattern-lock invariant `name === operationId` and collision-
 * proofs the P3.4 fan-out. The PHP method names stay as the legacy/test
 * `#[CoversMethod]` contract dictates (`getPrivilege`/`updatePrivilege`) —
 * the method name is independent of operationId.
 */
class WorkGroupApi
{
	private readonly WorkGroupsService $workGroupsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsService = new WorkGroupsService($db, $logger);
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
				'path' => '/work_groups',
				'handler' => [self::class, 'getWorkGroupList'],
				'name' => 'getWorkGroupList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/work_groups',
				'handler' => [self::class, 'createWorkGroup'],
				'name' => 'createWorkGroup',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}',
				'handler' => [self::class, 'getWorkGroup'],
				'name' => 'getWorkGroup',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}',
				'handler' => [self::class, 'updateWorkGroup'],
				'name' => 'updateWorkGroup',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}',
				'handler' => [self::class, 'deleteWorkGroup'],
				'name' => 'deleteWorkGroup',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}/privileges',
				'handler' => [self::class, 'getPrivilege'],
				'name' => 'getWorkGroupPrivilege',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/work_groups/{workGroupId}/privileges',
				'handler' => [self::class, 'updatePrivilege'],
				'name' => 'updateWorkGroupPrivilege',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/work_groups',
				'handler' => [self::class, 'getWorkGroupListByProject'],
				'name' => 'getWorkGroupListByProject',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/work_groups',
				'handler' => [self::class, 'createWorkGroupInProject'],
				'name' => 'createWorkGroupInProject',
			],
		];
	}

	#[OA\Get(
		path: '/work_groups',
		operationId: 'getWorkGroupList',
		tags: ['work_group'],
		summary: '複数件取得する',
		description: '自身が取得できるWorkのまとまり (WorkGroup) の情報を複数件取得する',
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
					items: new OA\Items(ref: '#/components/schemas/WorkGroup'),
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
	public function getWorkGroupList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->workGroupsService->selectWorkGroupPage(
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/work_groups',
		operationId: 'createWorkGroup',
		tags: ['work_group'],
		summary: '作成する',
		description: "Workのまとまり (WorkGroup) を新しく作成する\n\nこの操作にはサインインが必要です。",
		security: [['bearerAuth' => []]],
		requestBody: new OA\RequestBody(
			description: '作成するWorkGroupの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup'),
		),
		responses: [
			new OA\Response(
				response: 201,
				description: '作成成功',
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
		],
	)]
	public function createWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
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
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->createWorkGroup(
			userId: $userId,
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/work_groups/{workGroupId}',
		operationId: 'getWorkGroup',
		tags: ['work_group'],
		summary: '1件取得する',
		description: "Workのまとまり (WorkGroup) の情報を1件取得する\n\nこのデータが属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
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
	public function getWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($workGroupId);
		$this->logger->debug("workGroupId parsed: {workGroupId}", ['workGroupId' => $uuid]);
		return $this->workGroupsService->selectWorkGroupOne(
			currentUserId: $userId,
			workGroupsId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/work_groups/{workGroupId}',
		operationId: 'updateWorkGroup',
		tags: ['work_group'],
		summary: '更新する',
		description: "既存の「Workのまとまり (WorkGroup)」を更新する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
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
			description: '作成するWorkGroupの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
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
	public function updateWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
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
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->updateWorkGroup(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/work_groups/{workGroupId}',
		operationId: 'deleteWorkGroup',
		tags: ['work_group'],
		summary: '削除する',
		description: "既存の「Workのまとまり (WorkGroup)」を削除する\n\nこのデータが属するWorkGroupへのADMIN権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
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
	public function deleteWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}
		// getUserIdOrAnonymous() returns a non-null string in the new backend
		// (anonymous => Constants::UID_ANONYMOUS), so no `?? UID_ANONYMOUS`
		// fallback is needed here — unlike the legacy nullable-uid signature.
		return $this->workGroupsService->deleteWorkGroup(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/work_groups/{workGroupId}/privileges',
		operationId: 'getWorkGroupPrivilege',
		tags: ['work_group'],
		summary: '権限情報を取得する',
		description: "このWorkGroupに関する自身の権限を取得する。\n\n管理者の場合は、指定のユーザの権限を取得することも可能。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
			new OA\QueryParameter(
				name: 'uid',
				description: 'ユーザのID',
				required: false,
				schema: new OA\Schema(type: 'string'),
			),
			new OA\QueryParameter(
				name: 'uid-anonymous',
				description: "匿名ユーザ・すべてのユーザに対する操作か\n\n`uid` が指定された場合、そちらが優先される",
				required: false,
				schema: new OA\Schema(type: 'boolean'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/WorkGroupsPrivilege'),
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
	public function getPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$hasUid = key_exists('uid', $queryParams);
		$uid = ($hasUid) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;

		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true')) {
			$uid = Constants::UID_ANONYMOUS;
		}

		return $this->workGroupsService->getPrivileges(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			targetUserId: $uid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/work_groups/{workGroupId}/privileges',
		operationId: 'updateWorkGroupPrivilege',
		tags: ['work_group'],
		summary: '権限を更新する',
		description: "このWorkGroupに対する自身の権限を更新する。(現在の権限以下の権限のみ設定可能)\n\n管理者の場合は、指定のユーザの権限を追加・更新することも可能。(invite_key_idはNULLになります)",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'workGroupId',
				description: 'WorkGroupのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
			new OA\QueryParameter(
				name: 'uid',
				description: 'ユーザのID',
				required: false,
				schema: new OA\Schema(type: 'string'),
			),
			new OA\QueryParameter(
				name: 'uid-anonymous',
				description: "匿名ユーザ・すべてのユーザに対する操作か\n\n`uid` が指定された場合、そちらが優先される",
				required: false,
				schema: new OA\Schema(type: 'boolean'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後の権限情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/WorkGroupsPrivilege'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/WorkGroupsPrivilege'),
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
	public function updatePrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;
		$body = $request->getParsedBody();

		if (!Uuid::isValid($workGroupId)) {
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true')) {
			$uid = Constants::UID_ANONYMOUS;
		}

		$validateResult = (new RequestValidator(
			new EnumValidationRule(
				key: 'privilege_type',
				className: InviteKeyPrivilegeType::class,
				isRequired: true,
				isNullable: false,
			),
		))->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: false,
		);
		if ($validateResult->isError) {
			$this->logger->warning(
				"Invalid request body: {msg}",
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->updatePrivilege(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			targetUserId: $uid,
			newPrivilegeType: Utils::getValueOrNull($body, 'privilege_type'),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/projects/{projectId}/work_groups',
		operationId: 'getWorkGroupListByProject',
		tags: ['work_group'],
		summary: '複数件取得する',
		description: "指定のProjectに属する WorkGroup の情報を複数件取得する\n\nこのProjectへのREAD権限が必要です。",
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
					items: new OA\Items(ref: '#/components/schemas/WorkGroup'),
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
	public function getWorkGroupListByProject(
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

		return $this->workGroupsService->selectWorkGroupListByProject(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/{projectId}/work_groups',
		operationId: 'createWorkGroupInProject',
		tags: ['work_group'],
		summary: '作成する',
		description: "指定のProjectに属する WorkGroup を新しく作成する\n\nこのProjectへのWRITE権限が必要です。",
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
			description: '作成するWorkGroupの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup'),
		),
		responses: [
			new OA\Response(
				response: 201,
				description: '作成成功',
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
	public function createWorkGroupInProject(
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
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->createWorkGroupInProject(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}
}
