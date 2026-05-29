<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\ColorsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\Color8bitValidationRule;
use dev_t0r\trvis_backend\validator\ColorRealValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Color CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * Privilege root is Project (via projects_id; re-rooted from work-group).
 * Parameters are written INLINE (not $ref'd components) so P3 agents never
 * share a mutable Parameters file.
 *
 * operationId + route `name` are normalized to `<verb><Entity><Action>`
 * per CONTRIBUTING-P3.md §4 naming invariant (name === operationId).
 */
class ColorApi
{
	private readonly ColorsService $colorsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->colorsService = new ColorsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			RequestValidator::getDescriptionValidationRule(),
			new Color8bitValidationRule(
				key: 'color_8bit',
				isRequired: true,
				isNullable: false,
			),
			new ColorRealValidationRule(
				key: 'color_real',
				isNullable: true,
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
				'path' => '/projects/{projectId}/colors',
				'handler' => [self::class, 'getColorList'],
				'name' => 'getColorList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/projects/{projectId}/colors',
				'handler' => [self::class, 'createColor'],
				'name' => 'createColor',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/colors/{colorId}',
				'handler' => [self::class, 'getColor'],
				'name' => 'getColor',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/colors/{colorId}',
				'handler' => [self::class, 'updateColor'],
				'name' => 'updateColor',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/colors/{colorId}',
				'handler' => [self::class, 'deleteColor'],
				'name' => 'deleteColor',
			],
		];
	}

	#[OA\Get(
		path: '/projects/{projectId}/colors',
		operationId: 'getColorList',
		tags: ['color'],
		summary: '複数件取得する',
		description: '指定のProjectに属する Color (色) の情報を複数件取得する  このProjectへのREAD権限が必要です。',
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
					items: new OA\Items(ref: '#/components/schemas/Color'),
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
	public function getColorList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId,
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

		return $this->colorsService->getPage(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/projects/{projectId}/colors',
		operationId: 'createColor',
		tags: ['color'],
		summary: '作成する',
		description: '指定のProjectに属する Color (色) を新しく作成する  指定のProjectへのWRITE権限が必要です。',
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
			description: '作成するColorの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/Color'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/Color')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/Color'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/Color'),
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
	public function createColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId,
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

		$dataList = array_map(function ($item) {
			$colorData = new Color();
			$colorData->setData([
				'name' => Utils::getValueOrNull($item, 'name'),
				'description' => Utils::getValueOrNull($item, 'description'),
				'color_8bit' => Utils::getValueOrNull($item, 'color_8bit'),
				'color_real' => Utils::getValueOrNull($item, 'color_real'),
			]);
			return $colorData;
		}, $bodyList);

		$createResult = $this->colorsService->create(
			projectsId: Uuid::fromString($projectId),
			userId: $userId,
			dataList: $dataList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/colors/{colorId}',
		operationId: 'getColor',
		tags: ['color'],
		summary: '1件取得する',
		description: 'Color の情報を1件取得する  このデータが属するWorkGroupへのREAD権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'colorId',
				description: 'ColorのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Color'),
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
	public function getColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($colorId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $colorId]);
			return Utils::withUuidError($response);
		}

		return $this->colorsService->getOne(
			userId: $userId,
			colorsId: Uuid::fromString($colorId),
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/colors/{colorId}',
		operationId: 'updateColor',
		tags: ['color'],
		summary: '更新する',
		description: '既存の Color の情報を更新する  このデータが属するWorkGroupへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'colorId',
				description: 'ColorのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新するColorの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/Color'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/Color'),
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
	public function updateColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($colorId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $colorId]);
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

		$colorData = new Color();
		$colorData->setData([
			'name' => Utils::getValueOrNull($body, 'name'),
			'description' => Utils::getValueOrNull($body, 'description'),
			'color_8bit' => Utils::getValueOrNull($body, 'color_8bit'),
			'color_real' => Utils::getValueOrNull($body, 'color_real'),
		]);

		$kvpArray = Utils::getArrayForUpdateSource(
			keys: ['name', 'description', 'color_8bit', 'color_real'],
			requestBody: $body ?? [],
			getDataResult: $colorData->getData(),
		);

		return $this->colorsService->update(
			userId: $userId,
			colorsId: Uuid::fromString($colorId),
			data: $colorData,
			kvpArray: $kvpArray,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/colors/{colorId}',
		operationId: 'deleteColor',
		tags: ['color'],
		summary: '削除する',
		description: '既存の Color を削除する  このデータが属するWorkGroupへのWRITE権限が必要です。',
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'colorId',
				description: 'ColorのID',
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
	public function deleteColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($colorId)) {
			$this->logger->warning("Invalid UUID format ({id})", ['id' => $colorId]);
			return Utils::withUuidError($response);
		}

		return $this->colorsService->delete(
			userId: $userId,
			colorsId: Uuid::fromString($colorId),
		)->getResponseWithJson($response);
	}
}
