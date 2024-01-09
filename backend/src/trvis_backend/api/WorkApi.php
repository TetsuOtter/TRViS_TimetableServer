<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class WorkApi extends AbstractWorkApi
{
	const REMARKS_MAX_LENGTH = 255;

	private readonly WorksService $worksService;
	private readonly RequestValidator $bodyValidator;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->worksService = new WorksService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getNameValidationRule(),
			RequestValidator::getDescriptionValidationRule(),
			new DateTimeValidationRule(
				key: 'affect_date',
				isNullable: true,
				isRequired: false,
				isDateOnly: true,
			),
			new EnumValidationRule(
				key: 'affix_content_type',
				isNullable: true,
				isRequired: false,
				className: TrvisContentType::class,
			),
			new StringValidationRule(
				key: 'affix_content',
				isNullable: true,
				isRequired: false,
			),
			new StringValidationRule(
				key: 'remarks',
				isNullable: true,
				isRequired: false,
				maxLength: self::REMARKS_MAX_LENGTH,
			),
			new BoolValidationRule(
				key: 'has_e_train_timetable',
				isNullable: true,
				isRequired: false,
			),
			new EnumValidationRule(
				key: 'e_train_timetable_content_type',
				isNullable: true,
				isRequired: false,
				className: TrvisContentType::class,
			),
			new StringValidationRule(
				key: 'e_train_timetable_content',
				isNullable: true,
				isRequired: false,
			),
		);
	}

	public function createWork(
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
			$worksList = array_map(function ($req) {
				$work = new Work();
				$work->setData($req);
				return $work;
			}, (is_array($body) && array_is_list($body)) ? $body : [$body]);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'Failed to create Work object: {exception}',
				[
					'exception' => $e,
				],
			);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, 'Failed to create Work object - ' . $e->getMessage());
		}

		$createResult = $this->worksService->create(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			worksList: $worksList,
		);
		if (!$createResult->isError && (!is_array($body) || !array_is_list($body))) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	public function deleteWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workId))
		{
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
			return Utils::withUuidError($response);
		}

		return $this->worksService->delete(
			senderUserId: $userId,
			worksId: Uuid::fromString($workId),
		)->getResponseWithJson($response);
	}

	public function getWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workId))
		{
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
			return Utils::withUuidError($response);
		}

		return $this->worksService->getOne(
			senderUserId: $userId,
			worksId: Uuid::fromString($workId),
		)->getResponseWithJson($response);
	}

	public function getWorkList(
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

		return $this->worksService->getPage(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	public function updateWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workId))
		{
			$this->logger->warning("Invalid UUID format ({workId})", ['workId' => $workId]);
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
		$workData = new Work();
		$workData->setData($body);
		return $this->worksService->update(
			senderUserId: $userId,
			worksId: Uuid::fromString($workId),
			data: $workData,
			requestBody: $body,
		)->getResponseWithJson($response);
	}
}
