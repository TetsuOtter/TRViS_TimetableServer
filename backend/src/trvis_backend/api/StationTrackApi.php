<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class StationTrackApi extends AbstractStationTrackApi
{
	private readonly StationTracksService $stationTracksService;
	private readonly RequestValidator $bodyValidator;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->stationTracksService = new StationTracksService($db, $logger);
		$this->bodyValidator = new RequestValidator(
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
		);
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
