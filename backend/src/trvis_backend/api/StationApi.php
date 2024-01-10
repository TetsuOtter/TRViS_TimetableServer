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
