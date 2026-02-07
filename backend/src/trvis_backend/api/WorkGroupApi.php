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
use OpenApi\Attributes as OA;
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

	#[OA\Post(
		path: '/work_groups',
		operationId: 'createWorkGroup',
		summary: '作成する',
		description: "新しいWorkGroupを作成する\n\n認証が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work_group']
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup')
	)]
	#[OA\Response(
		response: 201,
		description: '作成成功',
		content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
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

	#[OA\Get(
		path: '/work_groups/{workGroupId}',
		operationId: 'getWorkGroup',
		summary: '1件取得する',
		description: "WorkGroupを1件取得する\n\n属するWorkGroupへのREAD権限が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work_group']
	)]
	#[OA\Parameter(
		name: 'workGroupId',
		in: 'path',
		required: true,
		description: 'WorkGroupのID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(ref: '#/components/schemas/WorkGroup')
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
	#[OA\Response(response: 404, description: 'WorkGroupが見つからない')]
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

	#[OA\Get(
		path: '/work_groups',
		operationId: 'getWorkGroupList',
		summary: '複数件取得する',
		description: "WorkGroupの情報を複数件取得する\n\n認証が必要です。",
		security: [['bearerAuth' => []]],
		tags: ['work_group']
	)]
	#[OA\Parameter(
		name: 'pageNumber',
		in: 'query',
		required: false,
		description: 'ページ番号 (0以上)',
		schema: new OA\Schema(type: 'integer', minimum: 0)
	)]
	#[OA\Parameter(
		name: 'pageSize',
		in: 'query',
		required: false,
		description: 'ページサイズ (1以上)',
		schema: new OA\Schema(type: 'integer', minimum: 1)
	)]
	#[OA\Response(
		response: 200,
		description: '取得成功',
		content: new OA\JsonContent(
			type: 'array',
			items: new OA\Items(ref: '#/components/schemas/WorkGroup')
		)
	)]
	#[OA\Response(response: 400, description: 'リクエストが不正')]
	#[OA\Response(response: 401, description: '認証エラー')]
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
