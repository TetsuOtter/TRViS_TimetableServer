<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class StationApi extends AbstractStationApi
{
	private static function _validateAndConvertInput(
		array|object|null &$d,
		LoggerInterface $logger,
		bool $checkRequired = true,
		string|int|null $key = null,
		bool $isKvpArray = false,
		bool $allowNestedArray = true,
	): RetValueOrError
	{
		if (is_null($d)) {
			return RetValueOrError::withBadReq("Missing required body");
		}

		if (is_array($d) && !$isKvpArray) {
			if (!array_is_list($d)) {
				return self::_validateAndConvertInput($d, $logger, $checkRequired, $key, true, false);
			}

			if (!$allowNestedArray) {
				return RetValueOrError::withBadReq("Nested array is not allowed");
			}
			foreach ($d as $_key => $value) {
				$result = self::_validateAndConvertInput($value, $logger, $checkRequired, $_key, $isKvpArray, false);
				if ($result->isError) {
					return $result;
				}
			}
			return RetValueOrError::withValue(null);
		}

		$key ??= 0;
		$checkPropExists = $isKvpArray
			? (fn(string $key): bool => array_key_exists($key, $d))
			: (fn(string $key): bool => property_exists($d, $key))
		;
		$getValue = $isKvpArray
			? (fn(string $key): mixed => $d[$key])
			: (fn(string $key): mixed => $d->{$key})
		;
		$setValue = $isKvpArray
			? (fn(string $key, array &$d, mixed $value): mixed => $d[$key] = $value)
			: (fn(string $key, object &$d, mixed $value): mixed => $d->{$key} = $value)
		;

		$nameExists = $checkPropExists('name');
		$descriptionExists = $checkPropExists('description');
		if ($checkRequired) {
			if (!$nameExists) {
				return RetValueOrError::withBadReq("Missing required field: 'name' @ $key");
			}
			if (!$descriptionExists) {
				return RetValueOrError::withBadReq("Missing required field: 'description' @ $key");
			}
		}

		if ($nameExists) {
			$nameValue = $getValue('name');
			if (!is_string($nameValue)) {
				return RetValueOrError::withBadReq("Invalid type for 'name' @ $key (expected: string)");
			}
			$nameLength = strlen($nameValue);
			if ($nameLength <= 0) {
				return RetValueOrError::withBadReq("Invalid value for 'name' @ $key (length <= 0)");
			} else if (Constants::NAME_MAX_LENGTH < $nameLength) {
				return RetValueOrError::withBadReq("Invalid value for 'name' @ $key (length > " . Constants::NAME_MAX_LENGTH . ")");
			}
		}

		if ($descriptionExists) {
			$descriptionValue = $getValue('description');
			if (!is_string($descriptionValue)) {
				return RetValueOrError::withBadReq("Invalid type for 'description' @ $key (expected: string)");
			}
			$descriptionLength = strlen($descriptionValue);
			if (Constants::DESCRIPTION_MAX_LENGTH < $descriptionLength) {
				return RetValueOrError::withBadReq("Invalid value for 'description' @ $key (length > " . Constants::DESCRIPTION_MAX_LENGTH . ")");
			}
		}

		if ($checkPropExists('location_km')) {
			$locationKm = $getValue('location_km');
			if (!is_float($locationKm) && !is_int($locationKm)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_km' @ $key (not a float/int)");
			}
			if (is_nan($locationKm)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_km' @ $key (NaN)");
			}
			if (is_infinite($locationKm)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_km' @ $key (infinite)");
			}
		}

		if ($checkPropExists('location_lonlat')
			&& !is_null($locationLonLat = $getValue('location_lonlat'))) {
			$lon = Utils::getValue($locationLonLat, 'longitude');
			$lat = Utils::getValue($locationLonLat, 'latitude');
			if (!is_float($lon) && !is_int($lon)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.longitude' @ $key (not a float/int)");
			}
			if (is_nan($lon)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.longitude' @ $key (NaN)");
			}
			if ($lon < -180.0 || 180.0 < $lon) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.longitude' @ $key (out of range)");
			}

			if (!is_float($lat) && !is_int($lat)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.latitude' @ $key (not a float/int)");
			}
			if (is_nan($lat)) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.latitude' @ $key (NaN)");
			}
			if ($lat < -90.0 || 90.0 < $lat) {
				return RetValueOrError::withBadReq("Invalid value for 'location_lonlat.latitude' @ $key (out of range)");
			}

			$lonlatObj = new StationLocationLonlat;
			$lonlatObj->setData([
				'longitude' => $lon,
				'latitude' => $lat,
			]);
			$setValue('location_lonlat', $d, $lonlatObj);
		}

		if ($checkPropExists('on_station_detect_radius_m')) {
			$radius = $getValue('on_station_detect_radius_m');
			if (!is_float($radius) && !is_int($radius)) {
				return RetValueOrError::withBadReq("Invalid value for 'on_station_detect_radius_m' @ $key (not a float/int)");
			}
			if (is_nan($radius)) {
				return RetValueOrError::withBadReq("Invalid value for 'on_station_detect_radius_m' @ $key (NaN)");
			}
			if (is_infinite($radius)) {
				return RetValueOrError::withBadReq("Invalid value for 'on_station_detect_radius_m' @ $key (infinite)");
			}
		}

		if ($checkPropExists('record_type')) {
			try {
				$contentType = $getValue('record_type');
				if (is_int($contentType)) {
					$contentType = StationRecordType::from($contentType);
				} else if (is_string($contentType)) {
					// 裏機能的に、文字列での指定にも対応する
					$contentType = StationRecordType::fromString($contentType);
				} else {
					$message = "Invalid type for parameter record_type, expected: int";
					return RetValueOrError::withBadReq($message);
				}
				$setValue('record_type', $d, $contentType);
			} catch (\Exception $e) {
				return RetValueOrError::withBadReq($e->getMessage());
			}
		}

		return RetValueOrError::withValue(null);
	}

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
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$validateResult = self::_validateAndConvertInput($body, $this->logger);
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
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			stationsList: $stationsList,
		);
		if (!$createResult->isError && (!is_array($body) || !array_is_list($body))) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);	}

	public function deleteStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			return $this->stationsService->delete(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				stationsId: Uuid::fromString($stationId),
			)->getResponseWithJson($response);
		}

	public function getStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			return $this->stationsService->getOne(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				stationsId: Uuid::fromString($stationId),
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

			$queryParams = $request->getQueryParams();
			$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
			$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
			$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;

			$hasP = !is_null($p);
			if ($hasP)
			{
				if (!is_numeric($p))
				{
					$this->logger->warning("Invalid number format (p:{p})", ['p' => $p]);
					return Utils::withError($response, 400, "Invalid number format for parameter `p`");
				}

				$p = intval($p);
				if ($p < Constants::PAGE_MIN_VALUE)
				{
					$this->logger->warning("Value out of range (p:{p})", ['p' => $p]);
					return Utils::withError($response, 400, "Value out of range (parameter `p`)");
				}
			}
			else
			{
				$p = Constants::PAGE_DEFAULT_VALUE;
			}

			$hasLimit = !is_null($limit);
			if ($hasLimit)
			{
				if (!is_numeric($limit))
				{
					$this->logger->warning("Invalid number format (limit:{limit})", ['limit' => $limit]);
					return Utils::withError($response, 400, "Invalid number format for parameter `limit`");
				}

				$limit = intval($limit);
				if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit)
				{
					$this->logger->warning("Value out of range (limit:{limit})", ['limit' => $limit]);
					return Utils::withError($response, 400, "Value out of range (parameter `limit`)");
				}
			}
			else
			{
				$limit = Constants::PER_PAGE_DEFAULT_VALUE;
			}

			$hasTop = !is_null($top);
			if ($hasTop && !Uuid::isValid($top))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $top]);
				return Utils::withUuidError($response);
			}

			$uuid = $hasTop ? Uuid::fromString($top) : null;

			return $this->stationsService->getPage(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				pageFrom1: $p,
				perPage: $limit,
				topId: $uuid,
			)->getResponseWithJson($response);
		}

	public function updateStation(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
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

			$validateBodyResult = self::_validateAndConvertInput(
				d: $body,
				logger: $this->logger,
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
				workGroupsId: Uuid::fromString($workGroupId),
				data: $stationData,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}
