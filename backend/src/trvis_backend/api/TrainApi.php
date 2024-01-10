<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\IntValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class TrainApi extends AbstractTrainApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new TrainsService($db, $logger),
			logger: $logger,
			modelClassName: Train::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getDescriptionValidationRule(),
				new StringValidationRule(
					key: 'train_number',
					minLength: Constants::NAME_MIN_LENGTH,
					maxLength: Constants::NAME_MAX_LENGTH,
					isRequired: true,
					isNullable: false,
				),
				new StringValidationRule(
					key: 'max_speed',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'speed_type',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'nominal_tractive_capacity',
					maxLength: 255,
					isNullable: true,
				),
				new IntValidationRule(
					key: 'car_count',
					isNullable: true,
				),
				new StringValidationRule(
					key: 'destination',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'begin_remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'after_remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'remarks',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'before_departure',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'after_arrive',
					maxLength: 255,
					isNullable: true,
				),
				new StringValidationRule(
					key: 'train_info',
					maxLength: 255,
					isNullable: true,
				),
				new IntValidationRule(
					key: 'direction',
					isRequired: true,
					isNullable: false,
				),
				new IntValidationRule(
					key: 'day_count',
					minValue: 0,
					isRequired: true,
					isNullable: false,
				),
				new BoolValidationRule(
					key: 'is_ride_on_moving',
					isNullable: true,
				),
				),
		);
	}

	public function createTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId,
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $workId,
		);
	}

	public function deleteTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}

	public function getTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}

	public function getTrainList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId,
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $workId,
		);
	}

	public function updateTrain(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $trainId,
	): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $trainId,
		);
	}
}
