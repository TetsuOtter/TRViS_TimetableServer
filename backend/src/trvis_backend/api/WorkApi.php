<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class WorkApi extends AbstractWorkApi
{
	const REMARKS_MAX_LENGTH = 255;

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

		if ($checkPropExists('affect_date')) {
			$affectDateStr = $getValue('affect_date');
			if (!is_string($affectDateStr)) {
				return RetValueOrError::withBadReq("Invalid value for 'affect_date' @ $key (not a string)");
			}
			$affectDate = Utils::fromJsonDateOnlyStrToDateTime($affectDateStr, $logger);
			if (is_null($affectDate)) {
				return RetValueOrError::withBadReq("Invalid value for 'affect_date' @ $key (not a valid date)");
			}
			$setValue('affect_date', $d, $affectDate);
		}

		if ($checkPropExists('affix_content_type')) {
			try {
				$contentType = $getValue('affix_content_type');
				if (is_int($contentType)) {
					// 裏機能的に、数値での指定にも対応する
					$contentType = TrvisContentType::from($contentType);
				} else if (is_string($contentType)) {
					$contentType = TrvisContentType::fromString($contentType);
				} else {
					$message = "Invalid type for parameter affix_content_type, expected: string";
					return RetValueOrError::withBadReq($message);
				}
				$setValue('affix_content_type', $d, $contentType);
			} catch (\Exception $e) {
				return RetValueOrError::withBadReq($e->getMessage());
			}
		}

		if ($checkPropExists('affix_content')) {
			$affixContent = $getValue('affix_content');
			if (!is_string($affixContent)) {
				return RetValueOrError::withBadReq("Invalid type for 'content_type' @ $key (expected: string)");
			}
			// TODO: validate
		}

		if ($checkPropExists('remarks')) {
			$remarks = $getValue('remarks');
			if (!is_string($remarks)) {
				return RetValueOrError::withBadReq("Invalid type for 'remarks' @ $key (expected: string)");
			}

			$remarksLength = strlen($remarks);
			if (self::REMARKS_MAX_LENGTH < $remarksLength) {
				return RetValueOrError::withBadReq("Invalid value for 'remarks' @ $key (length > " . self::REMARKS_MAX_LENGTH . ")");
			}
		}

		if ($checkPropExists('has_e_train_timetable')) {
			$hasETrainTimetable = $getValue('has_e_train_timetable');
			if (!is_bool($hasETrainTimetable)) {
				return RetValueOrError::withBadReq("Invalid type for 'has_e_train_timetable' @ $key (expected: bool)");
			}
		}

		if ($checkPropExists('e_train_timetable_content_type')) {
			try {
				$contentType = $getValue('e_train_timetable_content_type');
				if (is_int($contentType)) {
					// 裏機能的に、数値での指定にも対応する
					$contentType = TrvisContentType::from($contentType);
				} else if (is_string($contentType)) {
					$contentType = TrvisContentType::fromString($contentType);
				} else {
					$message = "Invalid type for parameter e_train_timetable_content_type, expected: string";
					return RetValueOrError::withBadReq($message);
				}
				$setValue('e_train_timetable_content_type', $d, $contentType);
			} catch (\Exception $e) {
				return RetValueOrError::withBadReq($e->getMessage());
			}
		}

		if ($checkPropExists('e_train_timetable_content')) {
			$eTrainTimetableContent = $getValue('e_train_timetable_content');
			if (!is_string($eTrainTimetableContent)) {
				return RetValueOrError::withBadReq("Invalid type for 'e_train_timetable_content' @ $key (expected: string)");
			}
			// TODO: validate
		}

		return RetValueOrError::withValue(null);
	}

	private readonly WorksService $worksService;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->worksService = new WorksService($db, $logger);
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
		string $workGroupId,
		string $workId
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

		return $this->worksService->delete(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			worksId: Uuid::fromString($workId),
		)->getResponseWithJson($response);
	}

	public function getWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId
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

		return $this->worksService->getOne(
			workGroupsId: Uuid::fromString($workGroupId),
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

		return $this->worksService->getPage(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			pageFrom1: $p,
			perPage: $limit,
			topId: $uuid,
		)->getResponseWithJson($response);
	}

	public function updateWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
		string $workId
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
		$workData = new Work();
		$workData->setData($body);
		return $this->worksService->update(
			senderUserId: $userId,
			worksId: Uuid::fromString($workId),
			workGroupsId: Uuid::fromString($workGroupId),
			data: $workData,
			requestBody: $body,
		)->getResponseWithJson($response);
	}
}
