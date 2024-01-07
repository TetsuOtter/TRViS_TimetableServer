<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\service\StationsService;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class StationApi extends AbstractStationApi
{
	private readonly StationsService $stationsService;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsService = new StationsService($db, $logger);
	}

	public function createStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$body = $request->getParsedBody();
	}

	public function deleteStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
	): ResponseInterface {
	}

	public function getStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
	): ResponseInterface {
	}

	public function getStationList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
	}

	public function updateStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
	): ResponseInterface {
		$body = $request->getParsedBody();
	}
}
