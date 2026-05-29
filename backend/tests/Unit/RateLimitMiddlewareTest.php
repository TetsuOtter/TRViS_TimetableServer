<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * H4 unit test (no database, no Firebase): boots a minimal Slim app with
 * RoutingMiddleware + RateLimitMiddleware + a trivial route, mirroring
 * RouteSmokeTest's self-contained boot style. Storage is the in-memory
 * symfony InMemoryStorage so nothing touches the filesystem.
 *
 * Regression criterion (how it is guaranteed):
 *   testDefaultLimitRejectsAtLimitPlusOne sends `limit + 1` requests with an
 *   identical REMOTE_ADDR and asserts the (limit+1)th response has status 429
 *   AND a Retry-After header. Without RateLimitMiddleware::process()
 *   invoking consume()->isAccepted() and short-circuiting, every request
 *   reaches the trivial handler which returns 200 with no Retry-After, so
 *   that assertion is impossible to pass — the suite fails if the middleware
 *   is not registered or its consume() is bypassed.
 *
 * Note on fuzzed/unmatched paths: bounding them is NOT this middleware's
 * job. Slim's RoutingMiddleware::performRouting() throws
 * HttpNotFoundException synchronously for an unmatched path, before any
 * inner middleware (RateLimit, MyAuth) runs, so an unmatched path never
 * reaches Firebase verification at all. That guarantee is structural
 * (Routing-before-Auth in RegisterMiddlewares), so there is intentionally
 * no unmatched-path test here.
 */
class RateLimitMiddlewareTest extends TestCase
{
	/** Tiny limit so the tests stay fast. */
	private const TEST_LIMIT = 3;

	private function logger(): AbstractLogger
	{
		return new class extends AbstractLogger {
			/** @var list<array{level:mixed,message:string}> */
			public array $records = [];
			public function log($level, $message, array $context = []): void
			{
				$this->records[] = ['level' => $level, 'message' => (string)$message];
			}
		};
	}

	/**
	 * Boot a minimal Slim app: RoutingMiddleware (outer) -> RateLimitMiddleware
	 * -> trivial 200 handler. Registered innermost-first (Slim add() is LIFO).
	 *
	 * @param array<string,array<string,mixed>> $routeLimits
	 */
	private function app(
		StorageInterface $storage,
		object $logger,
		array $routeLimits = [],
	): \Slim\App {
		$app = AppFactory::create();

		$mw = new RateLimitMiddleware(
			$storage,
			new LockFactory(new InMemoryStore()),
			new ResponseFactory(),
			$logger,
			['policy' => 'sliding_window', 'limit' => self::TEST_LIMIT, 'interval' => '1 minute'],
			$routeLimits,
		);

		$app->get('/ping', function (ServerRequestInterface $req, ResponseInterface $res) {
			$res->getBody()->write('pong');
			return $res;
		})->setName('ping');

		$app->post('/api/v1/invite_keys/{inviteKeyId}', function (ServerRequestInterface $req, ResponseInterface $res) {
			$res->getBody()->write('used');
			return $res;
		})->setName('useInviteKey');

		$app->get('/api/v1/dump/{workGroupId}', function (ServerRequestInterface $req, ResponseInterface $res) {
			$res->getBody()->write('dumped');
			return $res;
		})->setName('dumpTimetable');

		// add() is LIFO: last added == outermost. Routing must run before the
		// rate-limit middleware so the matched route name is available.
		$app->add($mw);
		$app->addRoutingMiddleware();
		return $app;
	}

	private function request(string $method, string $path, string $ip): ServerRequestInterface
	{
		return (new ServerRequestFactory())
			->createServerRequest($method, $path, ['REMOTE_ADDR' => $ip]);
	}

	public function testFirstNRequestsPassThenLimitPlusOneIsRejected(): void
	{
		$app = $this->app(new InMemoryStorage(), $this->logger());

		for ($i = 1; $i <= self::TEST_LIMIT; $i++) {
			$res = $app->handle($this->request('GET', '/ping', '203.0.113.7'));
			$this->assertNotSame(429, $res->getStatusCode(), "request #{$i} must pass");
			$this->assertSame(200, $res->getStatusCode());
		}

		$res = $app->handle($this->request('GET', '/ping', '203.0.113.7'));
		$this->assertSame(429, $res->getStatusCode());
		$this->assertNotSame('', $res->getHeaderLine('Retry-After'));
		$this->assertGreaterThanOrEqual(1, (int)$res->getHeaderLine('Retry-After'));
		$this->assertSame(
			(string)self::TEST_LIMIT,
			$res->getHeaderLine('X-RateLimit-Limit'),
		);
		$this->assertNotSame('', $res->getHeaderLine('X-RateLimit-Remaining'));
		$this->assertNotSame('', $res->getHeaderLine('X-RateLimit-Retry-After'));
		$body = json_decode((string)$res->getBody(), true);
		$this->assertSame(429, $body['code']);
		$this->assertSame('Too many requests', $body['message']);
	}

	public function testDistinctRemoteAddrGetIndependentBuckets(): void
	{
		$app = $this->app(new InMemoryStorage(), $this->logger());

		// Exhaust IP A.
		for ($i = 0; $i <= self::TEST_LIMIT; $i++) {
			$app->handle($this->request('GET', '/ping', '198.51.100.1'));
		}
		$resA = $app->handle($this->request('GET', '/ping', '198.51.100.1'));
		$this->assertSame(429, $resA->getStatusCode(), 'IP A must be throttled');

		// IP B is independent and still passes.
		$resB = $app->handle($this->request('GET', '/ping', '198.51.100.2'));
		$this->assertSame(200, $resB->getStatusCode(), 'IP B must have its own bucket');
	}

	/**
	 * Per-route override: useInviteKey has a tighter limit (2) than the
	 * default (TEST_LIMIT=3). Sending exactly TEST_LIMIT requests to each
	 * with the same IP must reject the invite-key route but NOT the default
	 * route — proving the "<METHOD> <routeName>" config precedence resolver.
	 */
	public function testPerRouteOverrideRejectsTighterRouteOnly(): void
	{
		$tighter = 2;
		$routeLimits = [
			'POST useInviteKey' => ['policy' => 'sliding_window', 'limit' => $tighter, 'interval' => '1 minute'],
		];
		$app = $this->app(new InMemoryStorage(), $this->logger(), $routeLimits);

		$ip = '192.0.2.55';
		$inviteStatuses = [];
		$defaultStatuses = [];
		for ($i = 0; $i < self::TEST_LIMIT; $i++) {
			$inviteStatuses[] = $app
				->handle($this->request('POST', '/api/v1/invite_keys/abc', $ip))
				->getStatusCode();
			$defaultStatuses[] = $app
				->handle($this->request('GET', '/ping', $ip))
				->getStatusCode();
		}

		// invite-key limit is 2: request index 2 (the 3rd) must be 429.
		$this->assertContains(429, $inviteStatuses, 'tighter useInviteKey limit must reject within TEST_LIMIT requests');
		$this->assertSame(429, $inviteStatuses[$tighter], 'request after the tighter limit must be 429');
		// default limit is 3: TEST_LIMIT requests must all pass.
		$this->assertNotContains(429, $defaultStatuses, 'default-limit route must not reject within its limit');
	}

	/**
	 * Per-route override for dumpTimetable: GET /api/v1/dump/{workGroupId} is
	 * an expensive bulk export configured with limit 1 / 5 seconds. The 1st
	 * GET from an IP must pass; an immediate 2nd GET from the same IP within
	 * the window must be 429 — proving the "GET dumpTimetable" override is
	 * resolved and enforces a hard limit of 1.
	 */
	public function testDumpTimetableOverrideRejectsSecondRequestWithinWindow(): void
	{
		$routeLimits = [
			'GET dumpTimetable' => ['policy' => 'sliding_window', 'limit' => 1, 'interval' => '5 seconds'],
		];
		$app = $this->app(new InMemoryStorage(), $this->logger(), $routeLimits);

		$ip = '192.0.2.77';

		$res1 = $app->handle($this->request('GET', '/api/v1/dump/wg-abc', $ip));
		$this->assertNotSame(429, $res1->getStatusCode(), 'first dump must pass');
		$this->assertSame(200, $res1->getStatusCode());

		$res2 = $app->handle($this->request('GET', '/api/v1/dump/wg-abc', $ip));
		$this->assertSame(429, $res2->getStatusCode(), 'second dump within the window must be 429');
		$this->assertNotSame('', $res2->getHeaderLine('Retry-After'));
		$this->assertSame('1', $res2->getHeaderLine('X-RateLimit-Limit'));
	}

	/**
	 * Fail-open: a storage whose save()/fetch() throw must NOT take the API
	 * down. The request must complete (handler reached, non-429, not 500) and
	 * a WARNING must be logged.
	 */
	public function testFailsOpenWhenStorageThrows(): void
	{
		$throwing = new class implements StorageInterface {
			public function save(LimiterStateInterface $limiterState): void
			{
				throw new \RuntimeException('storage down');
			}
			public function fetch(string $limiterStateId): ?LimiterStateInterface
			{
				throw new \RuntimeException('storage down');
			}
			public function delete(string $limiterStateId): void
			{
				throw new \RuntimeException('storage down');
			}
		};
		$logger = $this->logger();
		$app = $this->app($throwing, $logger);

		$res = $app->handle($this->request('GET', '/ping', '203.0.113.9'));

		$this->assertSame(200, $res->getStatusCode(), 'must fail open (handler reached)');
		$this->assertNotSame(429, $res->getStatusCode());
		$this->assertNotSame(500, $res->getStatusCode(), 'a broken limiter must not 500');
		$this->assertSame('pong', (string)$res->getBody());
		$warned = array_filter(
			$logger->records,
			fn(array $r): bool => $r['level'] === \Psr\Log\LogLevel::WARNING,
		);
		$this->assertNotEmpty($warned, 'fail-open path must log at WARNING');
	}
}
