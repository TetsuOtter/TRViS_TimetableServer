<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractWorkGroupApi;
use dev_t0r\trvis_backend\model\WorkGroup;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
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

	public function __construct(
		PDO $db,
		LoggerInterface $logger
	) {
		$this->db = $db;
		$this->logger = $logger;
	}

	const MAX_LEN_DESCRIPTION = 255;
	const MAX_LEN_NAME = 255;

	const SQL_CREATE_WORK_GROUP = <<<SQL
INSERT INTO work_groups (
	work_groups_id,
	owner,
	description,
	name
) VALUES (
	:work_groups_id,
	:owner,
	:description,
	:name
);
SQL;
const SQL_SELECT_WORK_GROUP_ONE = <<<SQL
SELECT
	work_groups_id,
	created_at,
	description,
	name
FROM
	work_groups
WHERE
	work_groups_id = :work_groups_id
;
SQL;

	private function _selectWorkGroupOne(
		UuidInterface $workGroupId
	): WorkGroup {
		$query = $this->db->prepare($this::SQL_SELECT_WORK_GROUP_ONE);
		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				sprintf(
					"Failed to execute SQL (%s -> %s)",
					$errCode,
					implode('\n\t', $query->errorInfo())
				)
			);
			throw new \RuntimeException("Failed to execute SQL - " . $errCode, 500);
		}

		$data = $query->fetch(PDO::FETCH_ASSOC);
		if (!$data) {
			$message = sprintf("WorkGroup not found: %s", $workGroupId);
			throw new \InvalidArgumentException($message, 404);
		}

		$workGroup = new WorkGroup();
		$workGroup->setData([
			'work_groups_id' => Uuid::fromBytes($data['work_groups_id']),
			'created_at' => $data['created_at'],
			'description' => $data['description'],
			'name' => $data['name']
		]);
		return $workGroup;
	}

	private function _insertWorkGroup(
		UuidInterface $workGroupId,
		string $owner,
		string $description,
		string $name
	): void {
		$query = $this->db->prepare($this::SQL_CREATE_WORK_GROUP);

		$query->bindValue(':work_groups_id', $workGroupId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(':owner', $owner, PDO::PARAM_STR);
		$query->bindValue(':description', $description, PDO::PARAM_STR);
		$query->bindValue(':name', $name, PDO::PARAM_STR);

		$isSuccess = $query->execute();
		if (!$isSuccess) {
			$errCode = $query->errorCode();
			$this->logger->error(
				sprintf(
					"Failed to execute SQL (%s -> %s)",
					$errCode,
					implode('\n\t', $query->errorInfo())
				)
			);
			throw new \RuntimeException("Failed to execute SQL - " . $errCode, 500);
		}
	}

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
			throw new \InvalidArgumentException($message, 400);
		}
		if (empty($req_value_name)) {
			$message = "Missing the required parameter 'name' when calling createWorkGroup";
			throw new \InvalidArgumentException($message, 400);
		}
		if ($this::MAX_LEN_NAME < strlen($req_value_name)) {
			$message = sprintf(
				"Invalid length for parameter name, must be smaller than or equal to %d.",
				$this::MAX_LEN_NAME
			);
			throw new \InvalidArgumentException($message, 400);
		}

		$uuid = Uuid::uuid7();

		$this->_insertWorkGroup(
			$uuid,
			'',
			$req_value_description,
			$req_value_name
		);

		return Utils::withJson($response, $this->_selectWorkGroupOne($uuid), 201);
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
		$message = "How about implementing getWorkGroup as a GET method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
		throw new HttpNotImplementedException($request, $message);
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
