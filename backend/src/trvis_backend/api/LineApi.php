<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class LineApi extends AbstractLineApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new LineService($db, $logger),
			logger: $logger,
			modelClassName: Line::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				RequestValidator::getDescriptionValidationRule(),
			),
		);
	}

	public function createLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $projectId,
		);
	}

	public function deleteLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $lineId,
		);
	}

	public function getLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $lineId,
		);
	}

	public function getLineList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $projectId,
		);
	}

	public function updateLine(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $lineId
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $lineId,
		);
	}
}
