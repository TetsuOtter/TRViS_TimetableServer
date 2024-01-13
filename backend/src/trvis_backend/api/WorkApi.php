<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\TrvisContentType;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\validator\BoolValidationRule;
use dev_t0r\trvis_backend\validator\DateTimeValidationRule;
use dev_t0r\trvis_backend\validator\EnumValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use dev_t0r\trvis_backend\validator\StringValidationRule;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class WorkApi extends AbstractWorkApi
{

	const REMARKS_MAX_LENGTH = 255;

	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new WorksService($db, $logger),
			logger: $logger,
			modelClassName: Work::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				RequestValidator::getDescriptionValidationRule(),
				new DateTimeValidationRule(
					key: 'affect_date',
					isNullable: true,
					isRequired: false,
					isDateOnly: true,
				),
				new EnumValidationRule(
					key: 'affix_content_type',
					isNullable: true,
					isRequired: false,
					className: TrvisContentType::class,
				),
				new StringValidationRule(
					key: 'affix_content',
					isNullable: true,
					isRequired: false,
				),
				new StringValidationRule(
					key: 'remarks',
					isNullable: true,
					isRequired: false,
					maxLength: self::REMARKS_MAX_LENGTH,
				),
				new BoolValidationRule(
					key: 'has_e_train_timetable',
					isNullable: true,
					isRequired: false,
				),
				new EnumValidationRule(
					key: 'e_train_timetable_content_type',
					isNullable: true,
					isRequired: false,
					className: TrvisContentType::class,
				),
				new StringValidationRule(
					key: 'e_train_timetable_content',
					isNullable: true,
					isRequired: false,
				),
			),
		);
	}

	public function createWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		return $this->apiHandler->create(
			request: $request,
			response: $response,
			parentId: $workGroupId,
		);
	}

	public function deleteWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		return $this->apiHandler->delete(
			request: $request,
			response: $response,
			id: $workId,
		);
	}

	public function getWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
	): ResponseInterface {
		return $this->apiHandler->getOne(
			request: $request,
			response: $response,
			id: $workId,
		);
	}

	public function getWorkList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		return $this->apiHandler->getPage(
			request: $request,
			response: $response,
			parentId: $workGroupId,
		);
	}

	public function updateWork(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workId
		): ResponseInterface {
		return $this->apiHandler->update(
			request: $request,
			response: $response,
			id: $workId,
		);
	}
}
