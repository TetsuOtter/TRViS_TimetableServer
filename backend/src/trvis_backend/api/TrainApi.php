<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class TrainApi extends AbstractTrainApi
{
	private readonly TrainsService $trainsService;
	private readonly RequestValidator $requestValidator;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->trainsService = new TrainsService($db, $logger);
		$this->requestValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			new StringValidationRule(
				key: 'train_number',
				minLength: Constants::NAME_MIN_LENGTH,
				maxLength: Constants::NAME_MAX_LENGTH,
				isRequired: true,
				isNullable: false,
			),
			new StringValidationRule(
				key: 'max_speed',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'speed_type',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'nominal_tractive_capacity',
				maxLength: 255,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'car_count',
				isNullable: true,
			),
			new StringValidationRule(
				key: 'destination',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'begin_remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'after_remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'remarks',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'before_departure',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'after_arrive',
				maxLength: 255,
				isNullable: true,
			),
			new StringValidationRule(
				key: 'train_info',
				maxLength: 255,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'direction',
				isRequired: true,
				isNullable: false,
			),
			new IntValidationRule(
				key: 'day_count',
				minValue: 0,
				isRequired: true,
				isNullable: false,
			),
			new BoolValidationRule(
				key: 'is_ride_on_moving',
				isNullable: true,
			),
		);
	}

	public function createTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}
		if (!Uuid::isValid($workId))
		{
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$validateResult = $this->requestValidator->validate(
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
			$trainsList = array_map(function ($req) {
				$train = new Train();
				$train->setData($req);
				return $train;
			}, (is_array($body) && array_is_list($body)) ? $body : [$body]);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'Failed to create Train object: {exception}',
				[
					'exception' => $e,
				],
			);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, 'Failed to create Train object - ' . $e->getMessage());
		}

		$createResult = $this->trainsService->create(
			workGroupsId: Uuid::fromString($workGroupId),
			worksId: Uuid::fromString($workId),
			senderUserId: $userId,
			trainsList: $trainsList,
		);
		if (!$createResult->isError && (!is_array($body) || !array_is_list($body))) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);	}

	public function deleteTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId,
		string $trainId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($workId))
			{
				$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($trainId))
			{
				$this->logger->warning("Invalid UUID format ({trainId})", ['trainId' => $trainId]);
				return Utils::withUuidError($response);
			}

			return $this->trainsService->delete(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				worksId: Uuid::fromString($workId),
				trainsId: Uuid::fromString($trainId),
			)->getResponseWithJson($response);
		}

	public function getTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId,
		string $trainId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($workId))
			{
				$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($trainId))
			{
				$this->logger->warning("Invalid UUID format ({trainId})", ['trainId' => $trainId]);
				return Utils::withUuidError($response);
			}

			return $this->trainsService->getOne(
				workGroupsId: Uuid::fromString($workGroupId),
				senderUserId: $userId,
				worksId: Uuid::fromString($workId),
				trainsId: Uuid::fromString($trainId),
			)->getResponseWithJson($response);
		}

	public function getTrainList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($workId))
			{
				$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
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

			return $this->trainsService->getPage(
				workGroupsId: Uuid::fromString($workGroupId),
				worksId: Uuid::fromString($workId),
				senderUserId: $userId,
				pageFrom1: $p,
				perPage: $limit,
				topId: $uuid,
			)->getResponseWithJson($response);
		}

	public function updateTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId,
		string $trainId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($workGroupId))
			{
				$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($workId))
			{
				$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
				return Utils::withUuidError($response);
			}
			if (!Uuid::isValid($trainId))
			{
				$this->logger->warning("Invalid UUID format ({trainId})", ['trainId' => $trainId]);
				return Utils::withUuidError($response);
			}

			$body = $request->getParsedBody();
			$validateBodyResult = $this->requestValidator->validate(
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
			$trainData = new Train();
			$trainData->setData($body);
			return $this->trainsService->update(
				senderUserId: $userId,
				trainsId: Uuid::fromString($trainId),
				worksId: Uuid::fromString($workId),
				workGroupsId: Uuid::fromString($workGroupId),
				data: $trainData,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}
