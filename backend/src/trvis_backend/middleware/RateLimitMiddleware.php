<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\middleware;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\Utils;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * H4: application-layer rate limiting / throttling.
 *
 * Without this, an authenticated request triggers a Firebase ID-token
 * verification on EVERY hit (MyAuthMiddleware -> Auth::verifyIdToken), so
 * unbounded Bearer replay/fuzzing amplifies the auth cost (Firebase
 * load/quota/billing) and the invite-key redemption capability endpoint can
 * be brute-forced without bound. This middleware is the app-layer backstop.
 *
 * Ordering (see RegisterMiddlewares): this runs AFTER RoutingMiddleware (so
 * the matched route NAME is available for per-route config) but BEFORE
 * MyAuthMiddleware (so a rejected request never spends a Firebase token
 * verification — the H4 amplification vector).
 *
 * Like {@see BodySizeLimitMiddleware} this is self-contained and FAILS OPEN:
 * any internal limiter/storage failure is caught, logged at WARNING, and the
 * request is forwarded — a broken limiter must never take the API down.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
	/**
	 * @param array<string,mixed> $defaultLimit
	 *        e.g. ['policy' => 'sliding_window', 'limit' => 60, 'interval' => '1 minute']
	 * @param array<string,array<string,mixed>> $routeLimits
	 *        keyed by "<METHOD> <routeName>" or "<routeName>"; same value shape.
	 */
	public function __construct(
		private readonly StorageInterface $storage,
		private readonly LockFactory $lockFactory,
		private readonly ResponseFactoryInterface $responseFactory,
		private readonly LoggerInterface $logger,
		private readonly array $defaultLimit,
		private readonly array $routeLimits,
	) {
	}

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		try {
			// REMOTE_ADDR ONLY. We deliberately never read X-Forwarded-For /
			// Forwarded: those headers are client-supplied and trivially
			// spoofed, so trusting them would let an attacker rotate the
			// rate-limit bucket per request and bypass the limit entirely.
			$clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';

			// RoutingMiddleware ran before us, so the matched route (if any)
			// is on the request. Unmatched paths (fuzzed/garbage) return null
			// and fall through to the default limit — this still bounds the
			// Firebase-verification cost of garbage-path fuzzing.
			$routeName = RouteContext::fromRequest($request)->getRoute()?->getName();
			$method = $request->getMethod();

			[$lookupKey, $config] = $this->resolveLimit($method, $routeName);

			$config['id'] = $lookupKey;
			$factory = new RateLimiterFactory($config, $this->storage, $this->lockFactory);
			$rateLimit = $factory->create($clientIp)->consume(1);
		} catch (\Throwable $th) {
			// FAIL OPEN: a broken limiter/storage must never take the API
			// down. Log at WARNING and forward the request unthrottled.
			$this->logger->warning(
				'RateLimitMiddleware failed open: {message}',
				['message' => $th->getMessage()],
			);
			return $handler->handle($request);
		}

		if (!$rateLimit->isAccepted()) {
			$retryAfterSeconds = \max(1, $rateLimit->getRetryAfter()->getTimestamp() - \time());

			// Log the client IP and the route NAME only. Never the raw
			// path/URI: an invite-key UUID in the path is a bearer
			// capability (H5 strips `url` from the WebProcessor for the same
			// reason — do not reintroduce it here).
			$this->logger->info(
				'Rate limit exceeded ip={ip} route={route}',
				['ip' => $clientIp, 'route' => $routeName ?? '(unmatched)'],
			);

			$response = $this->responseFactory->createResponse();
			$response = Utils::withError(
				$response,
				Constants::HTTP_TOO_MANY_REQUESTS,
				'Too many requests',
			);
			return $response
				->withHeader('Retry-After', (string)$retryAfterSeconds)
				->withHeader('X-RateLimit-Limit', (string)$rateLimit->getLimit())
				->withHeader('X-RateLimit-Remaining', (string)$rateLimit->getRemainingTokens())
				->withHeader('X-RateLimit-Retry-After', (string)$retryAfterSeconds);
		}

		return $handler->handle($request);
	}

	/**
	 * Resolve the limit config for this request. Precedence:
	 *   "<METHOD> <routeName>"  ->  "<routeName>"  ->  default.
	 *
	 * The returned key is also used as the RateLimiterFactory `id` so each
	 * route's per-IP buckets occupy a distinct storage namespace and do not
	 * collide with a different route's bucket for the same IP.
	 *
	 * @return array{0:string,1:array<string,mixed>} [lookupKey, config]
	 */
	private function resolveLimit(string $method, ?string $routeName): array
	{
		if ($routeName !== null) {
			$methodKey = $method . ' ' . $routeName;
			if (isset($this->routeLimits[$methodKey])) {
				return [$methodKey, $this->routeLimits[$methodKey]];
			}
			if (isset($this->routeLimits[$routeName])) {
				return [$routeName, $this->routeLimits[$routeName]];
			}
		}
		return ['_default', $this->defaultLimit];
	}
}
