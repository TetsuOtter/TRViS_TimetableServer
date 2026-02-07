<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;

final class TrainApi extends AbstractTrainApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new TrainsService($db, $logger),
			logger: $logger,
			modelClassName: Train::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getDescriptionValidationRule(),
				new StringValidationRule(
					key: 'train_number',
					minLength: Constants::NAME_MIN_LENGTH,
					maxLength: Constants::NAME_MAX_LENGTH,
					isRequired: true,
					isNullable: false,
				),
				new StringValidationRule(
					key: 'max_speed',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'speed_type',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'nominal_tractive_capacity',
					maxLength: 255,
					isNullable: true,
				),
				new IntValidationRule(
					key: 'car_count',
					isNullable: true,
				),
				new StringValidationRule(
					key: 'destination',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'begin_remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'after_remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'before_departure',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'after_arrive',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'train_info',
					maxLength: 255,
					isNullable: true,
				),
				new IntValidationRule(
					key: 'direction',
					isRequired: true,
					isNullable: false,
				),
				new IntValidationRule(
					key: 'day_count',
					minValue: 0,
					isRequired: true,
					isNullable: false,
				),
				new BoolValidationRule(
					key: 'is_ride_on_moving',
					isNullable: true,
				),
				),
		);
	}

	#[OA\Post(
		path: '/works/{workId}/trains',
		operationId: 'createTrain',
		summary: '作成する',
		description: "指定のWorkに属する Train を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['train']
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
		content: new OA\JsonContent(ref: '#/components/schemas/Train')
	)]
	#[OA\Response(
		response: 201,
		description: '作成成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Train')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function createTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId,
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $workId,
		);
	}

	#[OA\Delete(
		path: '/trains/{trainId}',
		operationId: 'deleteTrain',
		summary: '削除する',
		description: "既存のTrainを削除する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['train']
	)]
	#[OA\Parameter(
		name: 'trainId',
		in: 'path',
		required: true,
		description: 'TrainのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(response: 200, description: '削除成功')]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 403, description: '許可されていない操作')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function deleteTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}

	#[OA\Get(
		path: '/trains/{trainId}',
		operationId: 'getTrain',
		summary: '1件取得する',
		description: "Trainを1件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['train']
	)]
	#[OA\Parameter(
		name: 'trainId',
		in: 'path',
		required: true,
		description: 'TrainのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Train')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function getTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}

	#[OA\Get(
		path: '/works/{workId}/trains',
		operationId: 'getTrainList',
		summary: '複数件取得する',
		description: "指定のWorkに属するTrainの情報を複数件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['train']
	)]
	#[OA\Parameter(
		name: 'workId',
		in: 'path',
		required: true,
		description: 'WorkのID',
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
			items: new OA\Items(ref: '#/components/schemas/Train')
		)
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function getTrainList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId,
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $workId,
		);
	}

	#[OA\Put(
		path: '/trains/{trainId}',
		operationId: 'updateTrain',
		summary: '更新する',
		description: "既存のTrainの情報を更新する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['train']
	)]
	#[OA\Parameter(
		name: 'trainId',
		in: 'path',
		required: true,
		description: 'TrainのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(ref: '#/components/schemas/Train')
	)]
	#[OA\Response(
		response: 200,
		description: '更新成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Train')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function updateTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}
}
