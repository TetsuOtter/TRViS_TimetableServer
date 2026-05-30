<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
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
 * TimetableRow CRUD endpoints. Standalone (no generated Abstract* parent):
 * the #[OA\*] attributes below are the source of truth swagger-php reads;
 * routes() feeds RegisterRoutes' FQCN registry.
 *
 * TimetableRow is Train-rooted (trains_id FK). Privilege checks use
 * TrainsRepo (parent) for create/getTimetableRowList, TimetableRowsRepo
 * (target) for getTimetableRow/update/delete (see TimetableRowsService).
 *
 * update() follows the ColorApi kvpArray flow: validate (which also
 * converts uuid/enum strings in-place), rebuild the model, then
 * Utils::getArrayForUpdateSource to produce the partial-update kvp map.
 *
 * Parameters are INLINE (not $ref'd components) to keep the P3 fan-out
 * agent-independent. (PDO, LoggerInterface) are autowired by PHP-DI when
 * Slim resolves the route callable.
 *
 * operationId + route `name` follow the `<verb><Entity><Action>` pattern-lock.
 */
class TimetableRowApi
{
	private const UPDATE_KEYS = [
		'stations_id',
		'station_tracks_id',
		'colors_id_marker',
		'description',
		'drive_time_mm',
		'drive_time_ss',
		'is_operation_only_stop',
		'is_pass',
		'has_bracket',
		'is_last_stop',
		'arrive_time_hh',
		'arrive_time_mm',
		'arrive_time_ss',
		'departure_time_hh',
		'departure_time_mm',
		'departure_time_ss',
		'run_in_limit',
		'run_out_limit',
		'remarks',
		'arrive_str',
		'departure_str',
		'marker_text',
		'work_type',
	];

	private readonly TimetableRowsService $timetableRowsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->timetableRowsService = new TimetableRowsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			new UuidValidationRule(
				key: 'stations_id',
				isRequired: true,
				isNullable: false,
			),
			new UuidValidationRule(
				key: 'station_tracks_id',
				isRequired: false,
				isNullable: true,
			),
			new UuidValidationRule(
				key: 'colors_id_marker',
				isRequired: false,
				isNullable: true,
			),
			RequestValidator::getDescriptionValidationRule(),
			new IntValidationRule(
				key: 'drive_time_mm',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 99,
			),
			new IntValidationRule(
				key: 'drive_time_ss',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 59,
			),
			new BoolValidationRule(
				key: 'is_operation_only_stop',
				isNullable: true,
				isRequired: false,
			),
			new BoolValidationRule(
				key: 'is_pass',
				isNullable: true,
				isRequired: false,
			),
			new BoolValidationRule(
				key: 'has_bracket',
				isNullable: true,
				isRequired: false,
			),
			new BoolValidationRule(
				key: 'is_last_stop',
				isNullable: true,
				isRequired: false,
			),
			new IntValidationRule(
				key: 'arrive_time_hh',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 23,
			),
			new IntValidationRule(
				key: 'arrive_time_mm',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 59,
			),
			new IntValidationRule(
				key: 'arrive_time_ss',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 59,
			),
			new IntValidationRule(
				key: 'departure_time_hh',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 23,
			),
			new IntValidationRule(
				key: 'departure_time_mm',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 59,
			),
			new IntValidationRule(
				key: 'departure_time_ss',
				isNullable: true,
				isRequired: false,
				minValue: 0,
				maxValue: 59,
			),
			new IntValidationRule(
				key: 'run_in_limit',
				isNullable: true,
				isRequired: false,
				minValue: 1,
				maxValue: 999,
			),
			new IntValidationRule(
				key: 'run_out_limit',
				isNullable: true,
				isRequired: false,
				minValue: 1,
				maxValue: 999,
			),
			new StringValidationRule(
				key: 'remarks',
				isNullable: true,
				isRequired: false,
				maxLength: 255,
			),
			new StringValidationRule(
				key: 'arrive_str',
				isNullable: true,
				isRequired: false,
				maxLength: 255,
			),
			new StringValidationRule(
				key: 'departure_str',
				isNullable: true,
				isRequired: false,
				maxLength: 255,
			),
			new StringValidationRule(
				key: 'marker_text',
				isNullable: true,
				isRequired: false,
				maxLength: 8,
			),
			new EnumValidationRule(
				key: 'work_type',
				className: WorkAtStationType::class,
				isNullable: true,
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
				'path' => '/trains/{trainId}/timetable_rows',
				'handler' => [self::class, 'getTimetableRowList'],
				'name' => 'getTimetableRowList',
			],
			[
				'methods' => ['POST'],
				'basePath' => '/api/v1',
				'path' => '/trains/{trainId}/timetable_rows',
				'handler' => [self::class, 'createTimetableRow'],
				'name' => 'createTimetableRow',
			],
			[
				'methods' => ['GET'],
				'basePath' => '/api/v1',
				'path' => '/timetable_rows/{timetableRowId}',
				'handler' => [self::class, 'getTimetableRow'],
				'name' => 'getTimetableRow',
			],
			[
				'methods' => ['PUT'],
				'basePath' => '/api/v1',
				'path' => '/timetable_rows/{timetableRowId}',
				'handler' => [self::class, 'updateTimetableRow'],
				'name' => 'updateTimetableRow',
			],
			[
				'methods' => ['DELETE'],
				'basePath' => '/api/v1',
				'path' => '/timetable_rows/{timetableRowId}',
				'handler' => [self::class, 'deleteTimetableRow'],
				'name' => 'deleteTimetableRow',
			],
		];
	}

	#[OA\Get(
		path: '/trains/{trainId}/timetable_rows',
		operationId: 'getTimetableRowList',
		tags: ['timetable_row'],
		summary: '複数件取得する',
		description: "指定のTrainに属する TimetableRow (時刻表の行) の情報を複数件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'trainId',
				description: 'TrainのID',
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
					items: new OA\Items(ref: '#/components/schemas/TimetableRow'),
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
	public function getTimetableRowList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($trainId)) {
			$this->logger->warning("Invalid UUID format ({trainId})", ['trainId' => $trainId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->timetableRowsService->getPage(
			userId: $userId,
			trainsId: Uuid::fromString($trainId),
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	#[OA\Post(
		path: '/trains/{trainId}/timetable_rows',
		operationId: 'createTimetableRow',
		tags: ['timetable_row'],
		summary: '作成する',
		description: "指定のTrainに属する TimetableRow (時刻表の行) を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
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
			description: '作成するTimetableRowの情報。単一オブジェクトを送ると単一作成（200・単一オブジェクト応答）、配列を送るとバルク作成（201・配列応答）になる。',
			required: true,
			content: new OA\JsonContent(
				oneOf: [
					new OA\Schema(ref: '#/components/schemas/TimetableRow'),
					new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/TimetableRow')),
				],
			),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '単一作成成功（リクエストボディが単一オブジェクトのとき）',
				content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow'),
			),
			new OA\Response(
				response: 201,
				description: 'バルク作成成功（リクエストボディが配列のとき）。作成された全要素を配列で返す。',
				content: new OA\JsonContent(
					type: 'array',
					items: new OA\Items(ref: '#/components/schemas/TimetableRow'),
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
	public function createTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}
		if (!Uuid::isValid($trainId)) {
			$this->logger->warning("Invalid UUID format ({trainId})", ['trainId' => $trainId]);
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
			$m = new TimetableRow();
			$m->setData((array)$item);
			return $m;
		}, $bodyList);

		$createResult = $this->timetableRowsService->create(
			trainsId: Uuid::fromString($trainId),
			userId: $userId,
			dataList: $modelList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	#[OA\Get(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'getTimetableRow',
		tags: ['timetable_row'],
		summary: '1件取得する',
		description: "TimetableRow (時刻表の行) の情報を1件取得する\n\nこのデータが属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'timetableRowId',
				description: 'TimetableRowのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		responses: [
			new OA\Response(
				response: 200,
				description: '取得成功',
				content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow'),
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
	public function getTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($timetableRowId)) {
			$this->logger->warning(
				"Invalid UUID format ({timetableRowId})",
				['timetableRowId' => $timetableRowId],
			);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($timetableRowId);
		$this->logger->debug("timetableRowId parsed: {timetableRowId}", ['timetableRowId' => $uuid]);
		return $this->timetableRowsService->getOne(
			userId: $userId,
			timetableRowsId: $uuid,
		)->getResponseWithJson($response);
	}

	#[OA\Put(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'updateTimetableRow',
		tags: ['timetable_row'],
		summary: '更新する',
		description: "既存のTimetableRowの情報を更新する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'timetableRowId',
				description: 'TimetableRowのID',
				required: true,
				schema: new OA\Schema(type: 'string', format: 'uuid'),
			),
		],
		requestBody: new OA\RequestBody(
			description: '更新後のTimetableRowの情報',
			required: true,
			content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow'),
		),
		responses: [
			new OA\Response(
				response: 200,
				description: '更新成功',
				content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow'),
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
	public function updateTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($timetableRowId)) {
			$this->logger->warning(
				"Invalid UUID format ({timetableRowId})",
				['timetableRowId' => $timetableRowId],
			);
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

		$timetableRowData = new TimetableRow();
		$timetableRowData->setData([
			'stations_id' => Utils::getValueOrNull($body, 'stations_id'),
			'station_tracks_id' => Utils::getValueOrNull($body, 'station_tracks_id'),
			'colors_id_marker' => Utils::getValueOrNull($body, 'colors_id_marker'),
			'description' => Utils::getValueOrNull($body, 'description'),
			'drive_time_mm' => Utils::getValueOrNull($body, 'drive_time_mm'),
			'drive_time_ss' => Utils::getValueOrNull($body, 'drive_time_ss'),
			// Use getBoolValueOrNull: getValueOrNull collapses a present
			// `false` to null (getValue's absent-sentinel is also false), which
			// would NULL these NOT NULL columns on UPDATE → SQLSTATE 23000.
			'is_operation_only_stop' => Utils::getBoolValueOrNull($body, 'is_operation_only_stop'),
			'is_pass' => Utils::getBoolValueOrNull($body, 'is_pass'),
			'has_bracket' => Utils::getBoolValueOrNull($body, 'has_bracket'),
			'is_last_stop' => Utils::getBoolValueOrNull($body, 'is_last_stop'),
			'arrive_time_hh' => Utils::getValueOrNull($body, 'arrive_time_hh'),
			'arrive_time_mm' => Utils::getValueOrNull($body, 'arrive_time_mm'),
			'arrive_time_ss' => Utils::getValueOrNull($body, 'arrive_time_ss'),
			'departure_time_hh' => Utils::getValueOrNull($body, 'departure_time_hh'),
			'departure_time_mm' => Utils::getValueOrNull($body, 'departure_time_mm'),
			'departure_time_ss' => Utils::getValueOrNull($body, 'departure_time_ss'),
			'run_in_limit' => Utils::getValueOrNull($body, 'run_in_limit'),
			'run_out_limit' => Utils::getValueOrNull($body, 'run_out_limit'),
			'remarks' => Utils::getValueOrNull($body, 'remarks'),
			'arrive_str' => Utils::getValueOrNull($body, 'arrive_str'),
			'departure_str' => Utils::getValueOrNull($body, 'departure_str'),
			'marker_text' => Utils::getValueOrNull($body, 'marker_text'),
			'work_type' => Utils::getValueOrNull($body, 'work_type'),
		]);

		$kvpArray = Utils::getArrayForUpdateSource(
			keys: self::UPDATE_KEYS,
			requestBody: $body ?? [],
			getDataResult: $timetableRowData->getData(),
		);

		return $this->timetableRowsService->update(
			userId: $userId,
			timetableRowsId: Uuid::fromString($timetableRowId),
			data: $timetableRowData,
			kvpArray: $kvpArray,
		)->getResponseWithJson($response);
	}

	#[OA\Delete(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'deleteTimetableRow',
		tags: ['timetable_row'],
		summary: '削除する',
		description: "既存のTimetableRowを削除する\n\nこのデータが属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		parameters: [
			new OA\PathParameter(
				name: 'timetableRowId',
				description: 'TimetableRowのID',
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
	public function deleteTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($timetableRowId)) {
			$this->logger->warning(
				"Invalid UUID format ({timetableRowId})",
				['timetableRowId' => $timetableRowId],
			);
			return Utils::withUuidError($response);
		}
		return $this->timetableRowsService->delete(
			userId: $userId,
			timetableRowsId: Uuid::fromString($timetableRowId),
		)->getResponseWithJson($response);
	}
}
