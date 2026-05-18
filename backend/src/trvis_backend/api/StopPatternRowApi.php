<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\service\StopPatternRowsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class StopPatternRowApi extends AbstractStopPatternRowApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new StopPatternRowsService($db, $logger),
			logger: $logger,
			modelClassName: StopPatternRow::class,
			bodyValidator: new RequestValidator(
				new UuidValidationRule(
					key: 'project_stations_id',
					isNullable: false,
					isRequired: true,
				),
				new IntValidationRule(
					key: 'sort_key',
					minValue: 0,
					isNullable: false,
					isRequired: false,
				),
				new StringValidationRule(
					key: 'track_name',
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(key: 'track_hidden', isNullable: false, isRequired: false),
				new BoolValidationRule(key: 'is_operation_only_stop', isNullable: false, isRequired: false),
				new BoolValidationRule(key: 'is_pass', isNullable: false, isRequired: false),
				new IntValidationRule(key: 'drive_time_mm', minValue: 0, maxValue: 99, isNullable: true, isRequired: false),
				new IntValidationRule(key: 'drive_time_ss', minValue: 0, maxValue: 59, isNullable: true, isRequired: false),
				new IntValidationRule(key: 'dwell_time_mm', minValue: 0, maxValue: 99, isNullable: true, isRequired: false),
				new IntValidationRule(key: 'dwell_time_ss', minValue: 0, maxValue: 59, isNullable: true, isRequired: false),
				new BoolValidationRule(key: 'show_arrive', isNullable: false, isRequired: false),
				new BoolValidationRule(key: 'show_departure', isNullable: false, isRequired: false),
				new StringValidationRule(key: 'arrive_str', isNullable: true, isRequired: false),
				new StringValidationRule(key: 'departure_str', isNullable: true, isRequired: false),
				new IntValidationRule(key: 'run_in_limit', minValue: 0, isNullable: true, isRequired: false),
				new IntValidationRule(key: 'run_out_limit', minValue: 0, isNullable: true, isRequired: false),
				new StringValidationRule(key: 'remarks', isNullable: true, isRequired: false),
				new BoolValidationRule(key: 'always_show_hh', isNullable: false, isRequired: false),
			),
		);
	}

	public function createStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		return $this->apiHandler->create(request: $request, response: $response, parentId: $stopPatternId);
	}

	public function deleteStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		return $this->apiHandler->delete(request: $request, response: $response, id: $stopPatternRowId);
	}

	public function getStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		return $this->apiHandler->getOne(request: $request, response: $response, id: $stopPatternRowId);
	}

	public function getStopPatternRowList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternId
	): ResponseInterface {
		return $this->apiHandler->getPage(request: $request, response: $response, parentId: $stopPatternId);
	}

	public function updateStopPatternRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $stopPatternRowId
	): ResponseInterface {
		return $this->apiHandler->update(request: $request, response: $response, id: $stopPatternRowId);
	}
}
