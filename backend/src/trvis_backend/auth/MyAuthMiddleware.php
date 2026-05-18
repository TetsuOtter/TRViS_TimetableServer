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
		// getHeaderLine は未設定時に '' を返すため undefined index にならない
		$authHeader = $request->getHeaderLine('Authorization');
		if ($authHeader !== '' && preg_match('/^Bearer\s+(.*)$/', $authHeader, $matches))
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

				// 失敗理由の詳細はクライアントへ返さない (情報露出を避ける)。
				// 期限切れだけは UX 上トークン更新の契機になるため区別して通知する。
				$isTokenExpired = str_contains($errorMsg, 'The token is expired');
				$response = $this->responseFactory->createResponse();
				return $isTokenExpired
					? Utils::withError($response, 401, 'The token is expired')
					: Utils::withError($response, 401, 'Invalid authentication token');
			}
			catch (\Throwable $th)
			{
				// RevokedIdToken (checkIfRevoked 有効時) やトークン検証中の予期せぬ
				// 例外を補足する。スタックトレースを 500 で返さず汎用 401 にフォールバックする。
				$this->logger->error(
					"Unexpected error during token verification - {message}",
					['message' => $th->getMessage()],
				);
				$response = $this->responseFactory->createResponse();
				return Utils::withError($response, 401, 'Authentication failed');
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
		return self::getUserIdOrNull($request) ?? Constants::UID_ANONYMOUS;
	}
}

