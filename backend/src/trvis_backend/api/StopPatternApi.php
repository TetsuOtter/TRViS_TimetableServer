<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class StopPatternApi extends AbstractStopPatternApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new StopPatternsService($db, $logger),
			logger: $logger,
			modelClassName: StopPattern::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				new UuidValidationRule(
					key: 'lines_id',
					isNullable: false,
					isRequired: true,
				),
				new UuidValidationRule(
					key: 'from_project_stations_id',
					isNullable: true,
					isRequired: false,
				),
				new UuidValidationRule(
					key: 'to_project_stations_id',
					isNullable: true,
					isRequired: false,
				),
				new IntValidationRule(
					key: 'direction',
					minValue: -1,
					maxValue: 1,
					isNullable: false,
					isRequired: false,
				),
			),
		);
	}

	public function createStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->create(request: $request, response: $response, parentId: $projectId);
	}

	public function deleteStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		return $this->apiHandler->delete(request: $request, response: $response, id: $stopPatternId);
	}

	public function getStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		return $this->apiHandler->getOne(request: $request, response: $response, id: $stopPatternId);
	}

	public function getStopPatternList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $projectId
	): ResponseInterface {
		return $this->apiHandler->getPage(request: $request, response: $response, parentId: $projectId);
	}

	public function updateStopPattern(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		return $this->apiHandler->update(request: $request, response: $response, id: $stopPatternId);
	}
}
