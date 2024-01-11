<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\service\MyServiceBase;
use dev_t0r\trvis_backend\Utils;
use dev_t0r\trvis_backend\validator\PagingQueryValidator;
use dev_t0r\trvis_backend\validator\RequestValidator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class MyApiHandler
{
	public function __construct(
		private readonly MyServiceBase $service,
		private readonly LoggerInterface $logger,
		private readonly string $modelClassName,
		private readonly RequestValidator $bodyValidator,
	) {
	}

	public function create(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $parentId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($parentId))
		{
			$this->logger->warning("Invalid UUID format ({parentId})", ['parentId' => $parentId]);
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

		$isSingleItemRequest = !is_array($body) || !array_is_list($body);
		try
		{
			$stationsList = array_map(function ($req) {
				$station = new $this->modelClassName();
				$station->setData($req);
				return $station;
			}, ($isSingleItemRequest) ? [$body] : $body);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(
				'Failed to create {modelClassName} object: {exception}',
				[
					'modelClassName' => $this->modelClassName,
					'exception' => $e,
				],
			);
			return Utils::withError(
				$response,
				Constants::HTTP_BAD_REQUEST,
				"Failed to create {$this->modelClassName} object - " . $e->getMessage(),
			);
		}

		$createResult = $this->service->create(
			parentId: Uuid::fromString($parentId),
			senderUserId: $userId,
			dataList: $stationsList,
		);
		if (!$createResult->isError && $isSingleItemRequest) {
			$createResult = RetValueOrError::withValue($createResult->value[0]);
		}
		return $createResult->getResponseWithJson($response);
	}

	public function delete(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $id,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($id))
			{
				$this->logger->warning("Invalid UUID format ({id})", ['id' => $id]);
				return Utils::withUuidError($response);
			}

			return $this->service->delete(
				senderUserId: $userId,
				id: Uuid::fromString($id),
			)->getResponseWithJson($response);
		}

	public function getOne(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $id,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($id))
			{
				$this->logger->warning("Invalid UUID format ({id})", ['id' => $id]);
				return Utils::withUuidError($response);
			}

			return $this->service->getOne(
				senderUserId: $userId,
				id: Uuid::fromString($id),
			)->getResponseWithJson($response);
		}

	public function getPage(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $parentId,
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($parentId))
			{
				$this->logger->warning("Invalid UUID format ({parentId})", ['parentId' => $parentId]);
				return Utils::withUuidError($response);
			}

			$pagingParams = PagingQueryValidator::withRequest($request, $this->logger);
			if ($pagingParams->isError) {
				return $pagingParams->reqError->getResponseWithJson($response);
			}

			return $this->service->getPage(
				parentId: Uuid::fromString($parentId),
				senderUserId: $userId,
				pageFrom1: $pagingParams->pageFrom1,
				perPage: $pagingParams->perPage,
				topId: $pagingParams->topId,
			)->getResponseWithJson($response);
		}

	public function update(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $id
		): ResponseInterface {
			$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

			if (!Uuid::isValid($id))
			{
				$this->logger->warning("Invalid UUID format ({id})", ['id' => $id]);
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
			$data = new $this->modelClassName();
			$data->setData($body);
			return $this->service->update(
				senderUserId: $userId,
				id: Uuid::fromString($id),
				data: $data,
				requestBody: $body,
			)->getResponseWithJson($response);
		}
	}
