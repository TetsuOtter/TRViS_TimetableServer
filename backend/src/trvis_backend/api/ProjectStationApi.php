<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\service\ProjectStationsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class ProjectStationApi extends AbstractProjectStationApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new ProjectStationsService($db, $logger),
			logger: $logger,
			modelClassName: ProjectStation::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				new StringValidationRule(
					key: 'full_name',
					isNullable: true,
					isRequired: false,
				),
				new LonLatValidationRule(
					key: 'location_lonlat',
					isNullable: true,
					isRequired: false,
				),
				new FloatValidationRule(
					key: 'on_station_detect_radius_m',
					minValue: 0.0,
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(
					key: 'always_show_hh',
					isNullable: false,
					isRequired: false,
				),
			),
		);
	}

	public function createProjectStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->create(request: $request, response: $response, parentId: $projectId);
	}

	public function deleteProjectStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectStationId
	): ResponseInterface {
		return $this->apiHandler->delete(request: $request, response: $response, id: $projectStationId);
	}

	public function getProjectStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectStationId
	): ResponseInterface {
		return $this->apiHandler->getOne(request: $request, response: $response, id: $projectStationId);
	}

	public function getProjectStationList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->getPage(request: $request, response: $response, parentId: $projectId);
	}

	public function updateProjectStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectStationId
	): ResponseInterface {
		return $this->apiHandler->update(request: $request, response: $response, id: $projectStationId);
	}
}
