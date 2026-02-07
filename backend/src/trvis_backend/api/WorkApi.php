<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;

final class WorkApi extends AbstractWorkApi
{

	const REMARKS_MAX_LENGTH = 255;

	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new WorksService($db, $logger),
			logger: $logger,
			modelClassName: Work::class,
			bodyValidator: new RequestValidator(
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
				),
			),
		);
	}

	#[OA\Post(
		path: '/work_groups/{workGroupId}/works',
		operationId: 'createWork',
		summary: '作成する',
		description: "指定のWorkGroupに属する Work を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work']
	)]
	#[OA\Parameter(
		name: 'workGroupId',
		in: 'path',
		required: true,
		description: 'WorkGroupのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(ref: '#/components/schemas/Work')
	)]
	#[OA\Response(
		response: 201,
		description: '作成成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Work')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function createWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $workGroupId,
		);
	}

	#[OA\Delete(
		path: '/works/{workId}',
		operationId: 'deleteWork',
		summary: '削除する',
		description: "既存のWorkを削除する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work']
	)]
	#[OA\Parameter(
		name: 'workId',
		in: 'path',
		required: true,
		description: 'WorkのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(response: 200, description: '削除成功')]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 403, description: '許可されていない操作')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function deleteWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $workId,
		);
	}

	#[OA\Get(
		path: '/works/{workId}',
		operationId: 'getWork',
		summary: '1件取得する',
		description: "Workを1件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work']
	)]
	#[OA\Parameter(
		name: 'workId',
		in: 'path',
		required: true,
		description: 'WorkのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Work')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function getWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $workId,
		);
	}

	#[OA\Get(
		path: '/work_groups/{workGroupId}/works',
		operationId: 'getWorkList',
		summary: '複数件取得する',
		description: "指定のWorkGroupに属するWorkの情報を複数件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work']
	)]
	#[OA\Parameter(
		name: 'workGroupId',
		in: 'path',
		required: true,
		description: 'WorkGroupのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Parameter(
		name: 'pageNumber',
		in: 'query',
		required: false,
		description: 'ページ番号 (0以上)',
		schema: new OA\Schema(type: 'integer', minimum: 0)
	)]
	#[OA\Parameter(
		name: 'pageSize',
		in: 'query',
		required: false,
		description: 'ページサイズ (1以上)',
		schema: new OA\Schema(type: 'integer', minimum: 1)
	)]
	#[OA\Parameter(
		name: 'pageTopId',
		in: 'query',
		required: false,
		description: 'ページの先頭ID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(
			type: 'array',
			items: new OA\Items(ref: '#/components/schemas/Work')
		)
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function getWorkList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $workGroupId,
		);
	}

	#[OA\Put(
		path: '/works/{workId}',
		operationId: 'updateWork',
		summary: '更新する',
		description: "既存のWorkの情報を更新する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work']
	)]
	#[OA\Parameter(
		name: 'workId',
		in: 'path',
		required: true,
		description: 'WorkのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(ref: '#/components/schemas/Work')
	)]
	#[OA\Response(
		response: 200,
		description: '更新成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Work')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function updateWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
		): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $workId,
		);
	}
}
