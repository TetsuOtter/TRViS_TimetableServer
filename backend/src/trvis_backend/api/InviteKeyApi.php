<?php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\InviteKeysService;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class InviteKeyApi extends AbstractInviteKeyApi
{
	private readonly InviteKeysService $inviteKeysService;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->inviteKeysService = new InviteKeysService($db, $logger);
	}

	public function createInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		$body = $request->getParsedBody();
		$req_value_description = Utils::getValue($body, 'description');
		$req_value_valid_from = Utils::getValue($body, 'valid_from');
		$req_value_expires_at = Utils::getValue($body, 'expires_at');
		$req_value_use_limit = Utils::getValue($body, 'use_limit');
		$req_value_privilege_type = Utils::getValue($body, 'privilege_type');

		// validate params
		if ($req_value_description === false || is_null($req_value_description) || empty($req_value_description)) {
			$message = "Missing the required parameter 'description' when calling createInviteKey";
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}
		if (Constants::DESCRIPTION_MAX_LENGTH < strlen($req_value_description)) {
			$message = sprintf(
				"Invalid length for parameter description, must be smaller than or equal to %d.",
				Constants::DESCRIPTION_MAX_LENGTH,
			);
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}

		if ($req_value_valid_from !== false && !is_null($req_value_valid_from)) {
			$req_value_valid_from = Utils::fromJsonDateStrToDateTime($req_value_valid_from, $this->logger);
			if (is_null($req_value_valid_from)) {
				$message = "Invalid date string for parameter valid_from";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$req_value_valid_from = null;
		}

		if ($req_value_expires_at !== false && !is_null($req_value_expires_at)) {
			$req_value_expires_at = Utils::fromJsonDateStrToDateTime($req_value_expires_at);
			if (is_null($req_value_expires_at)) {
				$message = "Invalid date string for parameter expires_at";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$req_value_expires_at = null;
		}

		if (!is_null($req_value_valid_from) && !is_null($req_value_expires_at)) {
			if ($req_value_expires_at <= $req_value_valid_from) {
				$message = "Invalid value for parameter expires_at, must be after valid_from";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		}

		// TODO: 現在から一定期間以内のexpires_atはBad Requestにする
		// TODO: valid_from ~ expires_atが一定以下の期間しかない場合はBad Requestにする

		if ($req_value_use_limit !== false && !is_null($req_value_use_limit)) {
			if (!is_int($req_value_use_limit)) {
				$message = "Invalid type for parameter use_limit, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$req_value_use_limit = null;
		}

		if ($req_value_privilege_type === false || is_null($req_value_privilege_type)) {
			$message = "Missing the required parameter 'privilege_type' when calling createInviteKey";
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}
		try {
			if (is_int($req_value_privilege_type)) {
				// 裏機能的に、数値での指定にも対応する
				$req_value_privilege_type = InviteKeyPrivilegeType::fromInt($req_value_privilege_type);
			} else if (is_string($req_value_privilege_type)) {
				$req_value_privilege_type = InviteKeyPrivilegeType::fromString($req_value_privilege_type);
			} else {
				$message = "Invalid type for parameter privilege_type, expected: string";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} catch (\Exception $e) {
			$message = $e->getMessage();
			$this->logger->warning('Invalid value for parameter privilege_type - {msg}', [
				'msg' => $message,
			]);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}

		if ($req_value_privilege_type->value <= InviteKeyPrivilegeType::none->value) {
			$message = "Invalid value for parameter privilege_type, must be greater than " . InviteKeyPrivilegeType::none;
			$this->logger->warning($message);
			return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
		}

		return $this->inviteKeysService->createInviteKey(
			owner: $userId,
			workGroupId: Uuid::fromString($workGroupId),
			description: $req_value_description,
			validFrom: $req_value_valid_from,
			expiresAt: $req_value_expires_at,
			useLimit: $req_value_use_limit,
			privilegeType: $req_value_privilege_type,
		)->getResponseWithJson($response);
	}

	public function deleteInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);

		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->disableInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	public function getInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->selectInviteKey(
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}

	public function getInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $workGroupId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);
		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!Uuid::isValid($workGroupId))
		{
			$this->logger->warning("Invalid UUID format ({workGroupId})", ['workGroupId' => $workGroupId]);
			return Utils::withUuidError($response);
		}

		if (!is_null($p)) {
			if (!is_numeric($p)) {
				$message = "Invalid type for parameter p, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$p = intval($p);
			if ($p < Constants::PAGE_MIN_VALUE) {
				$message = sprintf(
					"Invalid value for parameter p, must be greater than or equal to %d.",
					Constants::PAGE_MIN_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$p = Constants::PAGE_DEFAULT_VALUE;
		}

		if (!is_null($limit)) {
			if (!is_numeric($limit)) {
				$message = "Invalid type for parameter limit, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$limit = intval($limit);
			if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit) {
				$message = sprintf(
					"Invalid value for parameter limit, must be between %d and %d.",
					Constants::PER_PAGE_MIN_VALUE,
					Constants::PER_PAGE_MAX_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$limit = Constants::PER_PAGE_DEFAULT_VALUE;
		}

		if (!is_null($top) && !Uuid::isValid($top)) {
			$this->logger->warning("Invalid UUID format ({top})", ['top' => $top]);
			return Utils::withUuidError($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithWorkGroupsId(
			userId: $userId,
			workGroupsId: Uuid::fromString($workGroupId),
			page: $p,
			perPage: $limit,
			topId: is_null($top) ? null : Uuid::fromString($top),
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	public function getMyInviteKeyList(
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		$queryParams = $request->getQueryParams();
		$p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
		$limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
		$top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
		$isExpiredKeyExists = key_exists('expired', $queryParams);
		$expired = $isExpiredKeyExists ? $queryParams['expired'] : null;

		if (!is_null($p)) {
			if (!is_numeric($p)) {
				$message = "Invalid type for parameter p, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$p = intval($p);
			if ($p < Constants::PAGE_MIN_VALUE) {
				$message = sprintf(
					"Invalid value for parameter p, must be greater than or equal to %d.",
					Constants::PAGE_MIN_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$p = Constants::PAGE_DEFAULT_VALUE;
		}

		if (!is_null($limit)) {
			if (!is_numeric($limit)) {
				$message = "Invalid type for parameter limit, expected: int";
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
			$limit = intval($limit);
			if ($limit < Constants::PER_PAGE_MIN_VALUE || Constants::PER_PAGE_MAX_VALUE < $limit) {
				$message = sprintf(
					"Invalid value for parameter limit, must be between %d and %d.",
					Constants::PER_PAGE_MIN_VALUE,
					Constants::PER_PAGE_MAX_VALUE,
				);
				$this->logger->warning($message);
				return Utils::withError($response, Constants::HTTP_BAD_REQUEST, $message);
			}
		} else {
			$limit = Constants::PER_PAGE_DEFAULT_VALUE;
		}

		if (!is_null($top) && !Uuid::isValid($top)) {
			$this->logger->warning("Invalid UUID format ({top})", ['top' => $top]);
			return Utils::withUuidError($response);
		}

		$includeExpired = false;
		if ($isExpiredKeyExists) {
			$includeExpired = empty($expired) || ($expired === 'true');
		}

		return $this->inviteKeysService->selectInviteKeyListWithOwnerUid(
			ownerUserId: $userId,
			page: $p,
			perPage: $limit,
			topId: is_null($top) ? null : Uuid::fromString($top),
			includeExpired: $includeExpired,
		)->getResponseWithJson($response);
	}

	public function updateInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		// TODO: implement updateInviteKey
		// $body = $request->getParsedBody();
		$this->logger->warning("updateInviteKey not implemented yet");
		return Utils::withError($response, Constants::HTTP_NOT_IMPLEMENTED, "updateInviteKey not implemented yet");
	}

	public function useInviteKey(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $inviteKeyId
	): ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if (is_null($userId)) {
			$this->logger->warning("Token was not set");
			return Utils::withError($response, Constants::HTTP_UNAUTHORIZED, "Token was not set");
		}

		if (!Uuid::isValid($inviteKeyId))
		{
			$this->logger->warning("Invalid UUID format ({inviteKeyId})", ['inviteKeyId' => $inviteKeyId]);
			return Utils::withUuidError($response);
		}

		return $this->inviteKeysService->useInviteKey(
			userId: $userId,
			inviteKeyId: Uuid::fromString($inviteKeyId),
		)->getResponseWithJson($response);
	}
}
