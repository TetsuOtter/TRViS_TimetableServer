<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use dev_t0r\trvis_backend\validator\UuidValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class TimetableRowApi extends AbstractTimetableRowApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new TimetableRowsService($db, $logger),
			logger: $logger,
			modelClassName: TimetableRow::class,
			bodyValidator: new RequestValidator(
				new UuidValidationRule(
					key: 'stations_id',
					isRequired: true,
					isNullable: false,
				),
				new UuidValidationRule(
					key: 'station_tracks_id',
					isRequired: false,
					isNullable: true,
				),
				new UuidValidationRule(
					key: 'colors_id_marker',
					isRequired: false,
					isNullable: true,
				),
				RequestValidator::getDescriptionValidationRule(),

				new IntValidationRule(
					key: 'drive_time_mm',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 99,
				),
				new IntValidationRule(
					key: 'drive_time_ss',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 59,
				),

				new BoolValidationRule(
					key: 'is_operation_only_stop',
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(
					key: 'is_pass',
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(
					key: 'has_bracket',
					isNullable: true,
					isRequired: false,
				),
				new BoolValidationRule(
					key: 'is_last_stop',
					isNullable: true,
					isRequired: false,
				),

				new IntValidationRule(
					key: 'arrive_time_hh',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 23,
				),
				new IntValidationRule(
					key: 'arrive_time_mm',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 59,
				),
				new IntValidationRule(
					key: 'arrive_time_ss',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 59,
				),

				new IntValidationRule(
					key: 'departure_time_hh',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 23,
				),
				new IntValidationRule(
					key: 'departure_time_mm',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 59,
				),
				new IntValidationRule(
					key: 'departure_time_ss',
					isNullable: true,
					isRequired: false,
					minValue: 0,
					maxValue: 59,
				),

				new IntValidationRule(
					key: 'run_in_limit',
					isNullable: true,
					isRequired: false,
					minValue: 1,
					maxValue: 999,
				),
				new IntValidationRule(
					key: 'run_out_limit',
					isNullable: true,
					isRequired: false,
					minValue: 1,
					maxValue: 999,
				),

				new StringValidationRule(
					key: 'remarks',
					isNullable: true,
					isRequired: false,
					maxLength: 255,
				),

				new StringValidationRule(
					key: 'arrive_str',
					isNullable: true,
					isRequired: false,
					maxLength: 255,
				),
				new StringValidationRule(
					key: 'departure_str',
					isNullable: true,
					isRequired: false,
					maxLength: 255,
				),

				new StringValidationRule(
					key: 'marker_text',
					isNullable: true,
					isRequired: false,
					maxLength: 8,
				),

				new EnumValidationRule(
					key: 'work_type',
					className: WorkAtStationType::class,
					isNullable: true,
					isRequired: false,
				),
			),
		);
	}

	public function createTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $trainId,
		);
	}

	public function deleteTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}

	public function getTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}

	public function getTimetableRowList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $trainId,
		);
	}

	public function updateTimetableRow(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $timetableRowId,
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $timetableRowId,
		);
	}
}
