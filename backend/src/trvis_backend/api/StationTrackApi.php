<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class StationTrackApi extends AbstractStationTrackApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new StationTracksService($db, $logger),
			logger: $logger,
			modelClassName: StationTrack::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				RequestValidator::getDescriptionValidationRule(),
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
			),
		);
	}

	public function createStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId,
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $stationId,
		);
	}

	public function deleteStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId,
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $stationTrackId,
		);
	}

	public function getStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId,
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $stationTrackId,
		);
	}

	public function getStationTrackList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId,
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $stationId,
		);
	}

	public function updateStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId,
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $stationTrackId,
		);
	}
}
