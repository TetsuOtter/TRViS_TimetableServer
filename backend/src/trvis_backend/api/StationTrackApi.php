<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
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
		string $stationId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

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
		string $stationTrackId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationTrackId))
			{
				$this->logger->warning("Invalid UUID format ({stationTrackId})", ['stationTrackId' => $stationTrackId]);
				return Utils::withUuidError($response);
			}

			return $this->stationTracksService->delete(
				senderUserId: $userId,
				stationTracksId: Uuid::fromString($stationTrackId),
			)->getResponseWithJson($response);
		}

	public function getStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationTrackId))
			{
				$this->logger->warning("Invalid UUID format ({stationTrackId})", ['stationTrackId' => $stationTrackId]);
				return Utils::withUuidError($response);
			}

			return $this->stationTracksService->getOne(
				senderUserId: $userId,
				stationTracksId: Uuid::fromString($stationTrackId),
			)->getResponseWithJson($response);
		}

	public function getStationTrackList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($stationId))
			{
				$this->logger->warning("Invalid UUID format ({stationId})", ['stationId' => $stationId]);
				return Utils::withUuidError($response);
			}

			$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
			if ($pagingParams->isError) {
				return $pagingParams->reqError->getResponseWithJson($response);
			}

			return $this->stationTracksService->getPage(
				stationsId: Uuid::fromString($stationId),
				senderUserId: $userId,
				pageFrom1: $pagingParams->pageFrom1,
				perPage: $pagingParams->perPage,
				topId: $pagingParams->topId,
			)->getResponseWithJson($response);
		}

	public function updateStationTrack(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stationTrackId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

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
				data: $stationTrackData,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}
