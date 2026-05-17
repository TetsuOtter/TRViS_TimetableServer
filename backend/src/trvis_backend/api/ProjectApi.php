<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractProjectApi;
use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\ProjectsService;
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
 * AbstractProjectApi Class Doc Comment
 *
 * @package dev_t0r\trvis_backend\api
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class ProjectApi extends AbstractProjectApi
{
	private readonly ProjectsService $projectsService;
	private readonly RequestValidator $bodyValidator;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->projectsService = new ProjectsService($db, $logger);
		$this->bodyValidator = new RequestValidator(
			RequestValidator::getDescriptionValidationRule(),
			RequestValidator::getNameValidationRule(),
		);
	}

	const MAX_LEN_DESCRIPTION = 255;
	const MAX_LEN_NAME = 255;

	public function createProject(
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

		return $this->projectsService->createProject(
			userId: $userId,
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	public function deleteProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId))
		{
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}
		return $this->projectsService->deleteProject(
			userId: $userId ?? Constants::UID_ANONYMOUS,
			projectsId: Uuid::fromString($projectId),
		)->getResponseWithJson($response);
	}

	public function getProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		if (!Uuid::isValid($projectId))
		{
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($projectId);
		$this->logger->debug("projectId parsed: {projectId}", ['projectId' => $uuid]);
		return $this->projectsService->selectProjectOne(
			currentUserId: $userId,
			projectsId: $uuid,
		)->getResponseWithJson($response);
	}

	public function getProjectList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
		if ($pagingParams->isError) {
			return $pagingParams->reqError->getResponseWithJson($response);
		}

		return $this->projectsService->selectProjectPage(
			userId: $userId,
			pageFrom1: $pagingParams->pageFrom1,
			perPage: $pagingParams->perPage,
			topId: $pagingParams->topId,
		)->getResponseWithJson($response);
	}

	public function updateProject(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();

		if (!Uuid::isValid($projectId))
		{
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
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

		return $this->projectsService->updateProject(
			userId: $userId,
			projectsId: Uuid::fromString($projectId),
			description: Utils::getValueOrNull($body, 'description'),
			name: Utils::getValueOrNull($body, 'name'),
		)->getResponseWithJson($response);
	}

	public function getProjectPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$hasUid = key_exists('uid', $queryParams);
		$uid = ($hasUid) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;

		if (!Uuid::isValid($projectId))
		{
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
			return Utils::withUuidError($response);
		}

		if ($hasUidAnonymous && is_null($uid) && ($uidAnonymous === '' || $uidAnonymous === 'true'))
		{
			$uid = Constants::UID_ANONYMOUS;
		}

		return $this->projectsService->getPrivileges(
			projectsId: Uuid::fromString($projectId),
			senderUserId: $userId,
			targetUserId: $uid,
		)->getResponseWithJson($response);
	}

	public function updateProjectPrivilege(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
		$hasUidAnonymous = key_exists('uid-anonymous', $queryParams);
		$uidAnonymous = ($hasUidAnonymous) ? $queryParams['uid-anonymous'] : null;
		$body = $request->getParsedBody();

		if (!Uuid::isValid($projectId))
		{
			$this->logger->warning("Invalid UUID format ({projectId})", ['projectId' => $projectId]);
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

		return $this->projectsService->updatePrivilege(
			projectsId: Uuid::fromString($projectId),
			senderUserId: $userId,
			targetUserId: $uid,
			newPrivilegeType: Utils::getValueOrNull($body, 'privilege_type'),
		)->getResponseWithJson($response);
	}
}
