<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\service\ProjectTransferService;
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
 * Project CRUD + privilege endpoints. Standalone (no generated Abstract*
 * parent): the #[OA\*] attributes below are the source of truth swagger-php
 * reads; routes() feeds RegisterRoutes' FQCN registry.
 *
 * Body/paging logic is a faithful port of the legacy ProjectApi; the
 * Allman braces were reshaped to PSR12 (same-line for control structures).
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file. (PDO, LoggerInterface) are autowired by
 * PHP-DI when Slim resolves the route callable.
 */
class ProjectApi
{
	private readonly ProjectsService $projectsService;
	private readonly ProjectTransferService $transferService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->projectsService = new ProjectsService($db, $logger);
		$this->transferService = new ProjectTransferService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			RequestValidator::getNameValidationRule(),
		);
	}

	/**
	 * Operation descriptors for RegisterRoutes. `basePath` + `path` must
	 * agree byte-for-byte with the #[OA\*] `path:` (server base is /api/v1).
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
				'path' => '/projects',
				'handler' => [self::class, 'getProjectList'],
				'name' => 'getProjectList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects',
				'handler' => [self::class, 'createProject'],
				'name' => 'createProject',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/import',
				'handler' => [self::class, 'importProject'],
				'name' => 'importProject',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}',
				'handler' => [self::class, 'getProject'],
				'name' => 'getProject',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/export',
				'handler' => [self::class, 'exportProject'],
				'name' => 'exportProject',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}',
				'handler' => [self::class, 'updateProject'],
				'name' => 'updateProject',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}',
				'handler' => [self::class, 'deleteProject'],
				'name' => 'deleteProject',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/privileges',
				'handler' => [self::class, 'getProjectPrivilege'],
				'name' => 'getProjectPrivilege',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/privileges',
				'handler' => [self::class, 'updateProjectPrivilege'],
				'name' => 'updateProjectPrivilege',
			],
		];
	}

	#[OA\Get(
		path: '/projects',
		operationId: 'getProjectList',
		tags: ['project'],
		summary: '複数件取得する',
		description: '自身が取得できる Project の情報を複数件取得する',
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
					items: new OA\Items(ref: '#/components/schemas/Project'),
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
	public function getProjectList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->projectsService->selectProjectPage(
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects',
		operationId: 'createProject',
		tags: ['project'],
		summary: '作成する',
		description: "Project を新しく作成する\n\nこの操作にはサインインが必要です。",
		security: [['bearerAuth' => []]],
		requestBody: new OA\RequestBody(
			description: '作成するProjectの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Project'),
		),
		responses: [
			new OA\Response(
				response: 201,
				description: '作成成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Project'),
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
	public function createProject(
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

		return $this->projectsService->createProject(
			userId: $userId,
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/projects/{projectId}',
		operationId: 'getProject',
		tags: ['project'],
		summary: '1件取得する',
		description: "Project の情報を1件取得する\n\nこのProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Project'),
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
	public function getProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($projectId);
		$this->logger->debug("projectId parsed: {projectId}", ['projectId' => $uuid]);
		return $this->projectsService->selectProjectOne(
			currentUserId: $userId,
			projectsId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/projects/{projectId}/export',
		operationId: 'exportProject',
		tags: ['project'],
		summary: 'エクスポートする',
		description: "Project の全グラフ (子エンティティを含む) を1つの ProjectGraph として取得する。\n\nこのProjectへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/ProjectGraph'),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正 (Projectが大きすぎる等)',
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
	public function exportProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		return $this->transferService->export(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/import',
		operationId: 'importProject',
		tags: ['project'],
		summary: 'インポートする',
		description: "ProjectGraph を読み込み、新しい Project として全グラフを複製する。すべての id はサーバ側で再採番される。\n\nこの操作にはサインインが必要です。",
		security: [['bearerAuth' => []]],
		requestBody: new OA\RequestBody(
			description: 'インポートする ProjectGraph',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/ProjectGraph'),
		),
		responses: [
			new OA\Response(
				response: 201,
				description: '作成成功 (作成された Project を返す)',
				content: new OA\JsonContent(ref: '#/components/schemas/Project'),
			),
			new OA\Response(
				response: 400,
				description: 'リクエストが不正 (payloadが大きすぎる等)',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
			new OA\Response(
				response: 401,
				description: '認証トークンのエラー',
				content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorData'),
			),
		],
	)]
	public function importProject(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		$body = $request->getParsedBody();
		if (!is_array($body) && !is_object($body)) {
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, "Invalid request body");
		}

		return $this->transferService->import(
			userId: $userId,
			graph: $body,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/projects/{projectId}',
		operationId: 'updateProject',
		tags: ['project'],
		summary: '更新する',
		description: "既存の Project を更新する\n\nこのProjectへのWRITE権限が必要です。",
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
			description: '更新後のProjectの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Project'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Project'),
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
	public function updateProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
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

		return $this->projectsService->updateProject(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/projects/{projectId}',
		operationId: 'deleteProject',
		tags: ['project'],
		summary: '削除する',
		description: "既存の Project を削除する\n\nこのProjectへのADMIN権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
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
	public function deleteProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}
		// getUserIdOrAnonymous() returns a non-null string in the new backend
		// (anonymous => Constants::UID_ANONYMOUS), so no `?? UID_ANONYMOUS`
		// fallback is needed here — unlike the legacy nullable-uid signature.
		return $this->projectsService->deleteProject(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
		)->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/projects/{projectId}/privileges',
		operationId: 'getProjectPrivilege',
		tags: ['project'],
		summary: '権限情報を取得する',
		description: "このProjectに関する自身の権限を取得する。\n\n管理者の場合は、指定のユーザの権限を取得することも可能。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
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
				content: new OA\JsonContent(ref: '#/components/schemas/ProjectsPrivilege'),
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
	public function getProjectPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$hasUid = key_exists('uid', $queryParams);
		$uid = ($hasUid) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;

		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true')) {
			$uid = Constants::UID_ANONYMOUS;
		}

		return $this->projectsService->getPrivileges(
			projectsId: Uuid::fromString($projectId),
			senderUserId: $userId,
			targetUserId: $uid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/projects/{projectId}/privileges',
		operationId: 'updateProjectPrivilege',
		tags: ['project'],
		summary: '権限を更新する',
		description: "このProjectに対する自身の権限を更新する。(現在の権限以下の権限のみ設定可能)\n\n管理者の場合は、指定のユーザの権限を追加・更新することも可能。(invite_key_idはNULLになります)",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'projectId',
				description: 'ProjectのID',
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
			content: new OA\JsonContent(ref: '#/components/schemas/ProjectsPrivilege'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/ProjectsPrivilege'),
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
	public function updateProjectPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;
		$body = $request->getParsedBody();

		if (!Uuid::isValid($projectId)) {
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
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

		return $this->projectsService->updatePrivilege(
			projectsId: Uuid::fromString($projectId),
			senderUserId: $userId,
			targetUserId: $uid,
			newPrivilegeType: Utils::getValueOrNull($body, 'privilege_type'),
		)->getResponseWithJson($response);
	}
}
