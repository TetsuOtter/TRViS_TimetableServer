<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\service\StationsOnLineService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class StationOnLineApi extends AbstractStationOnLineApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new StationsOnLineService($db, $logger),
			logger: $logger,
			modelClassName: StationOnLine::class,
			bodyValidator: new RequestValidator(
				new UuidValidationRule(
					key: 'project_stations_id',
					isNullable: false,
					isRequired: true,
				),
				new FloatValidationRule(
					key: 'location_m',
					isNullable: false,
					isRequired: true,
				),
				new LonLatValidationRule(
					key: 'location_lonlat',
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(
					key: 'track_hidden_by_default',
					isNullable: false,
					isRequired: false,
				),
			),
		);
	}

	public function createStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		return $this->apiHandler->create(request: $request, response: $response, parentId: $lineId);
	}

	public function deleteStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		return $this->apiHandler->delete(request: $request, response: $response, id: $stationOnLineId);
	}

	public function getStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		return $this->apiHandler->getOne(request: $request, response: $response, id: $stationOnLineId);
	}

	public function getStationOnLineList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		return $this->apiHandler->getPage(request: $request, response: $response, parentId: $lineId);
	}

	public function updateStationOnLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationOnLineId
	): ResponseInterface {
		return $this->apiHandler->update(request: $request, response: $response, id: $stationOnLineId);
	}
}
