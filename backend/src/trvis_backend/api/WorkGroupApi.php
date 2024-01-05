<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractWorkGroupApi;
use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\WorkGroup;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\Utils;
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

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsService = new WorkGroupsService($db, $logger);
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
		$requestData = new WorkGroup();
		$requestData->setData($body);
		$d = $requestData->getData();

		$req_value_description = $d->{'description'};
		$req_value_name = $d->{'name'};

		// validate params
		if ($this::MAX_LEN_DESCRIPTION < strlen($req_value_description)) {
			$message = sprintf(
				"Invalid length for parameter description, must be smaller than or equal to %d.",
				$this::MAX_LEN_DESCRIPTION
			);
			return Utils::withError($response, 400, $message);
		}
		if (empty($req_value_name)) {
			$message = "Missing the required parameter 'name' when calling createWorkGroup";
			return Utils::withError($response, 400, $message);
		}
		if ($this::MAX_LEN_NAME < strlen($req_value_name)) {
			$message = sprintf(
				"Invalid length for parameter name, must be smaller than or equal to %d.",
				$this::MAX_LEN_NAME
			);
			return Utils::withError($response, 400, $message);
		}

		return $this->workGroupsService->createWorkGroup(
			userId: $userId,
			description: $req_value_description,
			name: $req_value_name,
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
			userId: $userId ?? Constants::UID_ANONUMOUS,
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

		return $this->workGroupsService->selectWorkGroupPage(
			userId: $userId,
			pageFrom1: $p,
			perPage: $limit,
			topId: $uuid,
		)->getResponseWithJson($response);
	}

	public function updateWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$body = $request->getParsedBody();
		$requestData = new WorkGroup();
		$requestData->setData($body);
		$d = $requestData->getData();

		$req_value_description = $d->{'description'};
		$req_value_name = $d->{'name'};

		// validate params
		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}
		if (!is_null($req_value_description))
		{
			if ($this::MAX_LEN_DESCRIPTION < strlen($req_value_description)) {
				$message = sprintf(
					"Invalid length for parameter description, must be smaller than or equal to %d.",
					$this::MAX_LEN_DESCRIPTION
				);
				return Utils::withError($response, 400, $message);
			}
		}
		if (!is_null($req_value_name))
		{
			if (empty($req_value_name)) {
				$message = "Missing the required parameter 'name' when calling createWorkGroup";
				return Utils::withError($response, 400, $message);
			}
			if ($this::MAX_LEN_NAME < strlen($req_value_name)) {
				$message = sprintf(
					"Invalid length for parameter name, must be smaller than or equal to %d.",
					$this::MAX_LEN_NAME
				);
				return Utils::withError($response, 400, $message);
			}
		}

		return $this->workGroupsService->updateWorkGroup(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			description: $req_value_description,
			name: $req_value_name,
		)->getResponseWithJson($response);
	}
}
