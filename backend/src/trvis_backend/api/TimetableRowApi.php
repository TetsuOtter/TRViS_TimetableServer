<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;

final class TimetableRowApi extends AbstractTimetableRowApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new TimetableRowsService($db, $logger),
			logger: $logger,
			modelClassName: TimetableRow::class,
			bodyValidator: new RequestValidator(
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
			),
		);
	}

	#[OA\Post(
		path: '/trains/{trainId}/timetable_rows',
		operationId: 'createTimetableRow',
		summary: '作成する',
		description: "指定のTrainに属する TimetableRow を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['timetable_row']
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
		content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow')
	)]
	#[OA\Response(
		response: 201,
		description: '作成成功',
		content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function createTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $trainId,
		);
	}

	#[OA\Delete(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'deleteTimetableRow',
		summary: '削除する',
		description: "既存のTimetableRowを削除する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['timetable_row']
	)]
	#[OA\Parameter(
		name: 'timetableRowId',
		in: 'path',
		required: true,
		description: 'Timetable RowのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(response: 200, description: '削除成功')]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 403, description: '許可されていない操作')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function deleteTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}

	#[OA\Get(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'getTimetableRow',
		summary: '1件取得する',
		description: "TimetableRowを1件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['timetable_row']
	)]
	#[OA\Parameter(
		name: 'timetableRowId',
		in: 'path',
		required: true,
		description: 'Timetable RowのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function getTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}

	#[OA\Get(
		path: '/trains/{trainId}/timetable_rows',
		operationId: 'getTimetableRowList',
		summary: '複数件取得する',
		description: "指定のTrainに属するTimetableRowの情報を複数件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['timetable_row']
	)]
	#[OA\Parameter(
		name: 'trainId',
		in: 'path',
		required: true,
		description: 'TrainのID',
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
			items: new OA\Items(ref: '#/components/schemas/TimetableRow')
		)
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function getTimetableRowList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $trainId,
		);
	}

	#[OA\Put(
		path: '/timetable_rows/{timetableRowId}',
		operationId: 'updateTimetableRow',
		summary: '更新する',
		description: "既存のTimetableRowの情報を更新する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['timetable_row']
	)]
	#[OA\Parameter(
		name: 'timetableRowId',
		in: 'path',
		required: true,
		description: 'Timetable RowのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow')
	)]
	#[OA\Response(
		response: 200,
		description: '更新成功',
		content: new OA\JsonContent(ref: '#/components/schemas/TimetableRow')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'コンテンツまたはWorkGroupが見つからない')]
	public function updateTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}
}
