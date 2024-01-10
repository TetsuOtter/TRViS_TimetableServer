<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\FloatValidationRule;
use dev_t0r\trvis_backend\validator\LonLatValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class StationApi extends AbstractStationApi
{
	private readonly StationsService $stationsService;
	private readonly RequestValidator $bodyValidator;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationsService = new StationsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
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
		);
	}

	public function createStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: true,
		);
		if ($validateResult->isError) {
			$this->logger->error(
				"invalid input: {message}",
				[
					"message" => $validateResult->errorMsg,
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		try
		{
			$stationsList = array_map(function ($req) {
				$station = new Station();
				$station->setData($req);
				return $station;
			}, (is_array($body) && array_is_list($body)) ? $body : [$body]);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'Failed to create Station object: {exception}',
				[
					'exception' => $e,
				],
			);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, 'Failed to create Station object - ' . $e->getMessage());
		}

		$createResult = $this->stationsService->create(
			parentId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			dataList: $stationsList,
		);
		if (!$createResult->isError && (!is_array($body) || !array_is_list($body))) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	public function deleteStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			return $this->stationsService->delete(
				senderUserId: $userId,
				id: Uuid::fromString($stationId),
			)->getResponseWithJson($response);
		}

	public function getStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			return $this->stationsService->getOne(
				senderUserId: $userId,
				id: Uuid::fromString($stationId),
			)->getResponseWithJson($response);
		}

	public function getStationList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}

			$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
			if ($pagingParams->isError) {
				return $pagingParams->reqError->getResponseWithJson($response);
			}

			return $this->stationsService->getPage(
				parentId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				pageFrom1: $pagingParams->pageFrom1,
				perPage: $pagingParams->perPage,
				topId: $pagingParams->topId,
			)->getResponseWithJson($response);
		}

	public function updateStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			$body = $request->getParsedBody();
			if (is_null($body)) {
				$this->logger->warning('empty request body');
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, 'empty request body');
			}

			$validateBodyResult = $this->bodyValidator->validate(
				d: $body,
				checkRequired: false,
				allowNestedArray: false,
			);
			if ($validateBodyResult->isError) {
				$this->logger->warning(
					'invalid request body: {message}',
					[
						'message' => $validateBodyResult->errorMsg,
					],
				);
				return $validateBodyResult->getResponseWithJson($response);
			}
			$stationData = new Station();
			$stationData->setData($body);
			return $this->stationsService->update(
				senderUserId: $userId,
				stationsId: Uuid::fromString($stationId),
				data: $stationData,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}
