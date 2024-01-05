<?php

namespace dev_t0r\trvis_backend\auth;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\Utils;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\UnencryptedToken;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class MyAuthMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly Auth $auth,
		private readonly LoggerInterface $logger,
		private readonly ResponseFactoryInterface $responseFactory
	) {
	}

	public const ATTR_NAME_TOKEN_OBJ = 'tokenObj';

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler
	): ResponseInterface {
		$tokenStr = $request->getHeader('Authorization')[0];
		if (!is_null($tokenStr) && preg_match('/^Bearer\s+(.*)$/', $tokenStr, $matches))
		{
			$tokenStr = $matches[1];
			try
			{
				$verifiedIdToken = $this->auth->verifyIdToken($tokenStr);

				$request = $request->withAttribute($this::ATTR_NAME_TOKEN_OBJ, $verifiedIdToken);

				$uid = $verifiedIdToken->claims()->get('sub');
				$this->logger->info("Token uid: {uid}", ['uid' => $uid]);
			}
			catch (FailedToVerifyToken $th)
			{
				$errorMsg = $th->getMessage();
				$this->logger->info("Token error - {message}", ['message' => $errorMsg]);

				$isTokenExpired = str_contains($errorMsg, 'The token is expired');
				$response = $this->responseFactory->createResponse();
				return $isTokenExpired
					? Utils::withError($response, 401, 'The token is expired')
					: Utils::withError($response, 400, 'JWT(JOSE) error - ' . $errorMsg);
			}
		}
		else
		{
			$this->logger->info("Token was not set");
		}

		$response = $handler->handle($request);

		return $response;
	}

	public static function getTokenOrNull(
		ServerRequestInterface $request,
	): ?UnencryptedToken {
		return $request->getAttribute(self::ATTR_NAME_TOKEN_OBJ);
	}
	public static function getUserIdOrNull(
		ServerRequestInterface $request,
	): ?string {
		return self::getTokenOrNull($request)?->claims()->get('sub');
	}
	public static function getUserIdOrAnonymous(
		ServerRequestInterface $request,
	): string {
		return self::getUserIdOrNull($request) ?? Constants::UID_ANONUMOUS;
	}
}

