<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractWorkGroupApi;
use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * AbstractWorkGroupApi Class Doc Comment
 *
 * @package dev_t0r\trvis_backend\api
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class WorkGroupApi extends AbstractWorkGroupApi
{
	private readonly WorkGroupsService $workGroupsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsService = new WorkGroupsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			RequestValidator::getNameValidationRule(),
		);
	}

	const MAX_LEN_DESCRIPTION = 255;
	const MAX_LEN_NAME = 255;

	public function createWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null)
		{
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		$body = $request->getParsedBody();
		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: false,
		);
		if ($validateResult->isError)
		{
			$this->logger->warning(
				"Invalid request body: {msg}",
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->createWorkGroup(
			userId: $userId,
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	public function deleteWorkGroup(
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
		return $this->workGroupsService->deleteWorkGroup(
			userId: $userId ?? Constants::UID_ANONYMOUS,
			workGroupsId: Uuid::fromString($workGroupId),
		)->getResponseWithJson($response);
	}

	public function getWorkGroup(
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

		$uuid = Uuid::fromString($workGroupId);
		$this->logger->debug("workGroupId parsed: {workGroupId}", ['workGroupId' => $uuid]);
		return $this->workGroupsService->selectWorkGroupOne(
			currentUserId: $userId,
			workGroupsId: $uuid,
		)->getResponseWithJson($response);
	}

	public function getWorkGroupList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->workGroupsService->selectWorkGroupPage(
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	public function updateWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$validateResult = $this->bodyValidator->validate(
			d: $body,
			checkRequired: false,
			allowNestedArray: false,
		);
		if ($validateResult->isError)
		{
			$this->logger->warning(
				"Invalid request body: {msg}",
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->updateWorkGroup(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	public function getPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$hasUid = key_exists('uid', $queryParams);
		$uid = ($hasUid) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true'))
		{
			$uid = Constants::UID_ANONYMOUS;
		}

		return $this->workGroupsService->getPrivileges(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			targetUserId: $uid,
		)->getResponseWithJson($response);
	}

	public function updatePrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;
		$body = $request->getParsedBody();

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true'))
		{
			$uid = Constants::UID_ANONYMOUS;
		}

		$validateResult = (new RequestValidator(
			new EnumValidationRule(
				key: 'privilege_type',
				className: InviteKeyPrivilegeType::class,
				isRequired: true,
				isNullable: false,
			),
		))->validate(
			d: $body,
			checkRequired: true,
			allowNestedArray: false,
		);
		if ($validateResult->isError)
		{
			$this->logger->warning(
				"Invalid request body: {msg}",
				[
					'msg' => $validateResult->errorMsg
				],
			);
			return $validateResult->getResponseWithJson($response);
		}

		return $this->workGroupsService->updatePrivilege(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
			targetUserId: $uid,
			newPrivilegeType: Utils::getValueOrNull($body, 'privilege_type'),
		)->getResponseWithJson($response);
	}
}
