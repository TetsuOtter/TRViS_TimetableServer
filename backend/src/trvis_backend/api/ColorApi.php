<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\service\ColorsService;
use dev_t0r\trvis_backend\validator\Color8bitValidationRule;
use dev_t0r\trvis_backend\validator\ColorRealValidationRule;
use dev_t0r\trvis_backend\validator\RequestValidator;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class ColorApi extends AbstractColorApi
{
	private readonly MyApiHandler $apiHandler;
	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->apiHandler = new MyApiHandler(
			service: new ColorsService($db, $logger),
			logger: $logger,
			modelClassName: Color::class,
			bodyValidator: new RequestValidator(
				RequestValidator::getNameValidationRule(),
				RequestValidator::getDescriptionValidationRule(),
				new Color8bitValidationRule(
					key: 'color_8bit',
					isRequired: true,
					isNullable: false,
				),
				new ColorRealValidationRule(
					key: 'color_real',
					isNullable: true,
				),
			),
		);
	}

	public function createColor(
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

	public function deleteColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId
		): ResponseInterface {
			return $this->apiHandler->delete(
				request: $request,
				response: $response,
				id: $colorId,
			);
		}

	public function getColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId
		): ResponseInterface {
			return $this->apiHandler->getOne(
				request: $request,
				response: $response,
				id: $colorId,
			);
		}

	public function getColorList(
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

	public function updateColor(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $colorId
		): ResponseInterface {
			return $this->apiHandler->update(
				request: $request,
				response: $response,
				id: $colorId,
			);
		}
	}
