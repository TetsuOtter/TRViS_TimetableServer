<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * M7 unit test (no database, no real Firebase): boots a minimal Slim app with
 * RoutingMiddleware + MyAuthMiddleware + trivial routes, mirroring
 * RateLimitMiddlewareTest's self-contained boot style. The kreait Auth is a
 * PHPUnit mock so verifyIdToken's $checkIfRevoked argument can be observed
 * directly.
 *
 * Regression criterion (how it is guaranteed):
 *   testEachHighImpactRouteEnablesRevocationCheck asserts the mock receives
 *   $checkIfRevoked === true for createProject/deleteProject/deleteWorkGroup/
 *   createInviteKey, while testControlRouteSkipsRevocationCheck asserts it is
 *   false for a non-listed route. If MyAuthMiddleware reverted to the kreait
 *   default ($checkIfRevoked omitted ⇒ false) or applied the check globally,
 *   one of these two opposite assertions necessarily fails.
 *
 * testRevokedTokenOnHighImpactRouteIs401 proves the existing
 * catch (\Throwable) branch (RevokedIdToken is a RuntimeException, NOT a
 * FailedToVerifyToken) maps a revoked token to a generic 401 on a checked
 * route; testRevokedTokenOnControlRouteStillAccepted proves the accepted-risk
 * window — a non-listed route never performs the check, so the same
 * would-be-revoked token still authenticates there.
 */
class MyAuthMiddlewareRevocationTest extends TestCase
{
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

	/** A minimal valid UnencryptedToken whose claims()->get('sub') == 'test-uid'. */
	private function validToken(): UnencryptedToken
	{
		$token = $this->createMock(UnencryptedToken::class);
		$token->method('claims')->willReturn(new DataSet(['sub' => 'test-uid'], ''));
		return $token;
	}

	/**
	 * Boot a minimal Slim app: RoutingMiddleware (outer) -> MyAuthMiddleware
	 * -> trivial 200 handler. Registered innermost-first (Slim add() is LIFO),
	 * so routing resolves the matched route NAME before MyAuthMiddleware runs.
	 */
	private function app(Auth $auth, object $logger): \Slim\App
	{
		$app = AppFactory::create();

		$mw = new MyAuthMiddleware($auth, $logger, new ResponseFactory());

		$ok = function (ServerRequestInterface $req, ResponseInterface $res) {
			$res->getBody()->write('ok');
			return $res;
		};

		// Control route NOT in ROUTES_REQUIRING_REVOCATION_CHECK.
		$app->get('/api/v1/projects', $ok)->setName('getProjectList');

		// The four high-impact routes (names must match exactly; paths/methods
		// only need to be plausible enough for routing to resolve a name).
		$app->post('/api/v1/projects', $ok)->setName('createProject');
		$app->delete('/api/v1/projects/{projectsId}', $ok)->setName('deleteProject');
		$app->delete('/api/v1/work_groups/{workGroupsId}', $ok)->setName('deleteWorkGroup');
		$app->post('/api/v1/invite_keys', $ok)->setName('createInviteKey');

		// add() is LIFO: last added == outermost. Routing must run before
		// MyAuthMiddleware so the matched route name is available.
		$app->add($mw);
		$app->addRoutingMiddleware();
		return $app;
	}

	private function request(string $method, string $path): ServerRequestInterface
	{
		return (new ServerRequestFactory())
			->createServerRequest($method, $path)
			->withHeader('Authorization', 'Bearer testtoken');
	}

	public function testControlRouteSkipsRevocationCheck(): void
	{
		$captured = null;
		$auth = $this->createMock(Auth::class);
		$auth->method('verifyIdToken')->willReturnCallback(
			function ($token, $checkIfRevoked = false, $leeway = null) use (&$captured) {
				$captured = $checkIfRevoked;
				return $this->validToken();
			},
		);

		$app = $this->app($auth, $this->logger());
		$res = $app->handle($this->request('GET', '/api/v1/projects'));

		$this->assertFalse($captured, 'control route must NOT enable the revocation check');
		$this->assertSame(200, $res->getStatusCode());
		$this->assertSame('ok', (string)$res->getBody());
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function highImpactRouteProvider(): array
	{
		return [
			'createProject' => ['POST', '/api/v1/projects'],
			'deleteProject' => ['DELETE', '/api/v1/projects/p-1'],
			'deleteWorkGroup' => ['DELETE', '/api/v1/work_groups/wg-1'],
			'createInviteKey' => ['POST', '/api/v1/invite_keys'],
		];
	}

	#[DataProvider('highImpactRouteProvider')]
	public function testEachHighImpactRouteEnablesRevocationCheck(string $method, string $path): void
	{
		$captured = null;
		$capturedTokenStr = null;
		$auth = $this->createMock(Auth::class);
		$auth->method('verifyIdToken')->willReturnCallback(
			function ($token, $checkIfRevoked = false, $leeway = null) use (&$captured, &$capturedTokenStr) {
				$captured = $checkIfRevoked;
				$capturedTokenStr = $token;
				return $this->validToken();
			},
		);

		$app = $this->app($auth, $this->logger());
		$res = $app->handle($this->request($method, $path));

		$this->assertSame('testtoken', $capturedTokenStr);
		$this->assertTrue($captured, "$method $path must enable the revocation check");
		$this->assertSame(200, $res->getStatusCode());
	}

	public function testRevokedTokenOnHighImpactRouteIs401(): void
	{
		$auth = $this->createMock(Auth::class);
		$auth->method('verifyIdToken')->willReturnCallback(
			function ($token, $checkIfRevoked = false, $leeway = null) {
				if ($checkIfRevoked === true) {
					throw new RevokedIdToken($this->createMock(Token::class));
				}
				return $this->validToken();
			},
		);

		$app = $this->app($auth, $this->logger());
		$res = $app->handle($this->request('POST', '/api/v1/projects'));

		$this->assertSame(401, $res->getStatusCode());
		$body = json_decode((string)$res->getBody(), true);
		$this->assertSame('Authentication failed', $body['message']);
	}

	public function testRevokedTokenOnControlRouteStillAccepted(): void
	{
		$auth = $this->createMock(Auth::class);
		$auth->method('verifyIdToken')->willReturnCallback(
			function ($token, $checkIfRevoked = false, $leeway = null) {
				// Would be revoked IF the check were performed; on the control
				// route $checkIfRevoked is false so a valid token is returned.
				if ($checkIfRevoked === true) {
					throw new RevokedIdToken($this->createMock(Token::class));
				}
				return $this->validToken();
			},
		);

		$app = $this->app($auth, $this->logger());
		$res = $app->handle($this->request('GET', '/api/v1/projects'));

		$this->assertSame(200, $res->getStatusCode());
		$this->assertSame('ok', (string)$res->getBody());
	}
}
