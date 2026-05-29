<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\service\DumpService;
use dev_t0r\trvis_backend\Utils;
use OpenApi\Attributes as OA;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Dump aggregator endpoint — the terminal P3.5 epilogue. Standalone (no
 * generated Abstract* parent): the #[OA\Get] below is the source of truth
 * swagger-php reads; routes() feeds RegisterRoutes' FQCN registry.
 *
 * The pre-auth gate is an Api-level 403 (`MyAuthMiddleware::getUserIdOrNull`
 * -> null -> 'You must login to dump timetable') ported VERBATIM from the
 * legacy DumpApi. This is a deliberate Api-gate, NOT service-level privilege
 * logic — it is the documented CONTRIBUTING-P3.md §10 carve-out and is
 * exempt from the §11 "privilege-fail shape must match siblings" check
 * (siblings filter anonymous -> 200 []; dump requires sign-in -> 403).
 *
 * 200 returns a SINGLE TRViS_json_WorkGroup object — DumpService::dump()
 * returns one TRViSJsonWorkGroup, so the response schema is the object, not
 * the legacy spec's TRViS_json array wrapper (faithful to runtime behaviour;
 * the legacy spec being wrong is the migration premise).
 */
final class DumpApi
{
	private readonly DumpService $dumpService;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->dumpService = new DumpService(
			db: $this->db,
			logger: $this->logger,
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
				'path' => '/dump/{workGroupId}',
				'handler' => [self::class, 'dumpTimetable'],
				'name' => 'dumpTimetable',
			],
		];
	}

	#[OA\Get(
		path: '/dump/{workGroupId}',
		operationId: 'dumpTimetable',
		tags: ['dump'],
		summary: 'まとめて出力する',
		description: "WorkGroupに属するデータをまとめて出力する\n\n指定のWorkGroupへのREAD権限、およびサインインが必要です。",
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
				content: new OA\JsonContent(ref: '#/components/schemas/TRViS_json_WorkGroup'),
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
	public function dumpTimetable(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			return Utils::withError(
				$response,
				Constants::HTTP_FORBIDDEN,
				'You must login to dump timetable',
			);
		}

		if (!Uuid::isValid($workGroupId)) {
			return Utils::withUuidError($response);
		}

		$dumpResult = $this->dumpService->dump(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
		);
		return $dumpResult->getResponseWithJson($response);
	}
}
