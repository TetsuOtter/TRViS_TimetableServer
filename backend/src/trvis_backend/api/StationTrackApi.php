<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class StationTrackApi extends AbstractStationTrackApi
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

		if ($checkPropExists('run_in_limit')) {
			$value = $getValue('run_in_limit');
			if (!is_int($value)) {
				return RetValueOrError::withBadReq("Invalid value for 'run_in_limit' @ $key (not a int)");
			}
			if ($value <= 0) {
				return RetValueOrError::withBadReq("Invalid value for 'run_in_limit' @ $key (<= 0)");
			}
			if (1000 <= $value) {
				return RetValueOrError::withBadReq("Invalid value for 'run_in_limit' @ $key (>= 1000)");
			}
		}

		if ($checkPropExists('run_out_limit')) {
			$value = $getValue('run_out_limit');
			if (!is_int($value)) {
				return RetValueOrError::withBadReq("Invalid value for 'run_out_limit' @ $key (not a int)");
			}
			if ($value <= 0) {
				return RetValueOrError::withBadReq("Invalid value for 'run_out_limit' @ $key (<= 0)");
			}
			if (1000 <= $value) {
				return RetValueOrError::withBadReq("Invalid value for 'run_out_limit' @ $key (>= 1000)");
			}
		}

		return RetValueOrError::withValue(null);
	}

	private readonly StationTracksService $stationTracksService;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationTracksService = new StationTracksService($db, $logger);
	}

	public function createStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId,
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
			$stationTracksList = array_map(function ($req) {
				$stationTrack = new StationTrack();
				$stationTrack->setData($req);
				return $stationTrack;
			}, (is_array($body) && array_is_list($body)) ? $body : [$body]);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'Failed to create StationTrack object: {exception}',
				[
					'exception' => $e,
				],
			);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, 'Failed to create StationTrack object - ' . $e->getMessage());
		}

		$createResult = $this->stationTracksService->create(
			workGroupsId: Uuid::fromString($workGroupId),
			stationsId: Uuid::fromString($stationId),
			senderUserId: $userId,
			stationTracksList: $stationTracksList,
		);
		if (!$createResult->isError && (!is_array($body) || !array_is_list($body))) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);	}

	public function deleteStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId,
		string $stationTrackId,
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
			if (!Uuid::isValid($stationTrackId))
			{
				$this->logger->warning("Invalid UUID format ({stationTrackId})", ['stationTrackId' => $stationTrackId]);
				return Utils::withUuidError($response);
			}

			return $this->stationTracksService->delete(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				stationsId: Uuid::fromString($stationId),
				stationTracksId: Uuid::fromString($stationTrackId),
			)->getResponseWithJson($response);
		}

	public function getStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId,
		string $stationTrackId,
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
			if (!Uuid::isValid($stationTrackId))
			{
				$this->logger->warning("Invalid UUID format ({stationTrackId})", ['stationTrackId' => $stationTrackId]);
				return Utils::withUuidError($response);
			}

			return $this->stationTracksService->getOne(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				stationsId: Uuid::fromString($stationId),
				stationTracksId: Uuid::fromString($stationTrackId),
			)->getResponseWithJson($response);
		}

	public function getStationTrackList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId,
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

			return $this->stationTracksService->getPage(
				workGroupsId: Uuid::fromString($workGroupId),
				stationsId: Uuid::fromString($stationId),
				senderUserId: $userId,
				pageFrom1: $p,
				perPage: $limit,
				topId: $uuid,
			)->getResponseWithJson($response);
		}

	public function updateStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $stationId,
		string $stationTrackId,
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
			if (!Uuid::isValid($stationTrackId))
			{
				$this->logger->warning("Invalid UUID format ({stationTrackId})", ['stationTrackId' => $stationTrackId]);
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
			$stationTrackData = new StationTrack();
			$stationTrackData->setData($body);
			return $this->stationTracksService->update(
				senderUserId: $userId,
				stationTracksId: Uuid::fromString($stationTrackId),
				stationsId: Uuid::fromString($stationId),
				workGroupsId: Uuid::fromString($workGroupId),
				data: $stationTrackData,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}