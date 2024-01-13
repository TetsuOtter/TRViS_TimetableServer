<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKey;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\InviteKeysService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class InviteKeyApi extends AbstractInviteKeyApi
{
	private readonly InviteKeysService $inviteKeysService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->inviteKeysService = new InviteKeysService($db, $logger);

		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			new DateTimeValidationRule(
				key: 'valid_from',
				isDateOnly: false,
				isRequired: false,
				isNullable: true,
			),
			new DateTimeValidationRule(
				key: 'expires_at',
				isDateOnly: false,
				isRequired: false,
				isNullable: true,
			),
			new IntValidationRule(
				key: 'use_limit',
				isRequired: false,
				isNullable: true,
				minValue: 1,
			),
			new EnumValidationRule(
				key: 'privilege_type',
				className: InviteKeyPrivilegeType::class,
				isRequired: true,
				isNullable: false,
			),
		);
	}

	public function createInviteKey(
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
		// TODO: 現在から一定期間以内のexpires_atはBad Requestにする
		// TODO: valid_from ~ expires_atが一定以下の期間しかない場合はBad Requestにする
		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: false,
		);
		if ($validateResult->isError) {
			$this->logger->warning(
				"Invalid request body: {msg}",
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		$inviteKey = new InviteKey;
		$inviteKey->setData($body);
		if ($inviteKey->privilege_type->value <= InviteKeyPrivilegeType::none->value) {
			$message = "Invalid value for parameter privilege_type, must be greater than " . InviteKeyPrivilegeType::none->name;
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}

		return $this->inviteKeysService->createInviteKey(
			owner: $userId,
			workGroupId: Uuid::fromString($workGroupId),
			inviteKey: $inviteKey,
		)->getResponseWithJson($response);
	}

	public function deleteInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->disableInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	public function getInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->selectInviteKey(
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	public function getInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithWorkGroupsId(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			page: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	public function getMyInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!is_null($p)) {
			if (!is_numeric($p)) {
				$message = "Invalid type for parameter p, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$p = intval($p);
			if ($p < Constants::PAGE_MIN_VALUE) {
				$message = sprintf(
					"Invalid value for parameter p, must be greater than or equal to %d.",
					Constants::PAGE_MIN_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$p = Constants::PAGE_DEFAULT_VALUE;
		}

		if (!is_null($limit)) {
			if (!is_numeric($limit)) {
				$message = "Invalid type for parameter limit, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$limit = intval($limit);
			if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit) {
				$message = sprintf(
					"Invalid value for parameter limit, must be between %d and %d.",
					Constants::PER_PAGE_MIN_VALUE,
					Constants::PER_PAGE_MAX_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$limit = Constants::PER_PAGE_DEFAULT_VALUE;
		}

		if (!is_null($top) && !Uuid::isValid($top)) {
			$this->logger->warning("Invalid UUID format ({top})", ['top' => $top]);
			return Utils::withUuidError($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithOwnerUid(
			ownerUserId: $userId,
			page: $p,
			perPage: $limit,
			topId: is_null($top) ? null : Uuid::fromString($top),
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	public function updateInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		// TODO: implement updateInviteKey
		// $body = $request->getParsedBody();
		$this->logger->warning("updateInviteKey not implemented yet");
		return Utils::withError($response, Constants::HTTP_NOT_IMPLEMENTED, "updateInviteKey not implemented yet");
	}

	public function useInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->useInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}
}
