<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;

final class StationApi extends AbstractStationApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new StationsService($db, $logger),
			logger: $logger,
			modelClassName: Station::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				RequestValidator::getDescriptionValidationRule(),
				new FloatValidationRule(
					key: 'location_km',
					isNullable: false,
					isRequired: true,
				),
				new LonLatValidationRule(
					key: 'location_lonlat',
					isNullable: true,
					isRequired: false,
				),
				new FloatValidationRule(
					key: 'on_station_detect_radius_m',
					isNullable: false,
					isRequired: true,
					minValue: 0.0,
				),
				new EnumValidationRule(
					key: 'record_type',
					isNullable: false,
					isRequired: true,
					className: StationRecordType::class,
				),
			),
		);
	}

	#[OA\Post(
		path: '/work_groups/{workGroupId}/stations',
		operationId: 'createStation',
		summary: '作成する',
		description: "指定のWorkGroupに属する Station を新しく作成する\n\n属するWorkGroupへのWRITE権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['station']
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
		content: new OA\JsonContent(ref: '#/components/schemas/Station')
	)]
	#[OA\Response(
		response: 201,
		description: '作成成功',
		content: new OA\JsonContent(ref: '#/components/schemas/Station')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
	public function createStation(
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

	public function deleteStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			return $this->apiHandler->delete(
				request: $request,
				response: $response,
				id: $stationId,
			);
		}

	public function getStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			return $this->apiHandler->getOne(
				request: $request,
				response: $response,
				id: $stationId,
			);
		}

	public function getStationList(
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

	public function updateStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			return $this->apiHandler->update(
				request: $request,
				response: $response,
				id: $stationId,
			);
		}
	}
