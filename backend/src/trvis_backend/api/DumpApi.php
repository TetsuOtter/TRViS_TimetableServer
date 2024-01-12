<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\service\DumpService;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class DumpApi extends AbstractDumpApi
{
	private readonly DumpService $dumpService;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->dumpService = new DumpService(
			db: $this->db,
			logger: $this->logger,
		);
	}

	public function dumpTimetable(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId,
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId === null) {
			return Utils::withError(
				$response,
				Constants::HTTP_FORBIDDEN,
				'You must login to dump timetable',
			);
		}

		if (!Uuid::isValid($workGroupId)) {
			return Utils::withUuidError($response);
		}

		$dumpResult = $this->dumpService->dump(
			workGroupsId: Uuid::fromString($workGroupId),
			senderUserId: $userId,
		);
		return $dumpResult->getResponseWithJson($response);
	}
}
