<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractWorkGroupApi;
use dev_t0r\trvis_backend\model\WorkGroup;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;
use Slim\Exception\HttpNotImplementedException;

/**
 * AbstractWorkGroupApi Class Doc Comment
 *
 * @package dev_t0r\trvis_backend\api
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class WorkGroupApi extends AbstractWorkGroupApi
{
	private readonly PDO $db;
	private readonly LoggerInterface $logger;
	private readonly \dev_t0r\trvis_backend\repo\WorkGroups $workGroupsRepo;

	public function __construct(
		PDO $db,
		LoggerInterface $logger
	) {
		$this->db = $db;
		$this->logger = $logger;
		$this->workGroupsRepo = new \dev_t0r\trvis_backend\repo\WorkGroups($db, $logger);
	}

	const MAX_LEN_DESCRIPTION = 255;
	const MAX_LEN_NAME = 255;

	public function createWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
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
			return Utils::withError($response, $message, 400);
		}
		if (empty($req_value_name)) {
			$message = "Missing the required parameter 'name' when calling createWorkGroup";
			return Utils::withError($response, $message, 400);
		}
		if ($this::MAX_LEN_NAME < strlen($req_value_name)) {
			$message = sprintf(
				"Invalid length for parameter name, must be smaller than or equal to %d.",
				$this::MAX_LEN_NAME
			);
			return Utils::withError($response, $message, 400);
		}

		$uuid = Uuid::uuid7();

		$insertResult = $this->workGroupsRepo->insertWorkGroup(
			$uuid,
			'',
			$req_value_description,
			$req_value_name
		);
		if ($insertResult->isError) {
			return $insertResult->getResponseWithJson($response);
		}

		return $this->workGroupsRepo->selectWorkGroupOne($uuid)->getResponseWithJson($response, 201);
	}

	public function deleteWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$message = "How about implementing deleteWorkGroup as a DELETE method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
		throw new HttpNotImplementedException($request, $message);
	}

	public function getWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		if (!preg_match(LazyUuidFromString::VALID_REGEX, $workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$uuid = Uuid::fromString($workGroupId);
		$this->logger->debug("workGroupId parsed: {workGroupId}", ['workGroupId' => $uuid]);
		return $this->workGroupsRepo->selectWorkGroupOne($uuid)->getResponseWithJson($response);
	}

	public function getWorkGroupList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$message = "How about implementing getWorkGroupList as a GET method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
		throw new HttpNotImplementedException($request, $message);
	}

	public function updateWorkGroup(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$body = $request->getParsedBody();
		$message = "How about implementing updateWorkGroup as a PUT method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
		throw new HttpNotImplementedException($request, $message);
	}
}
