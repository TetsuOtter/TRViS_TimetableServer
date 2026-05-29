<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use dev_t0r\App\RegisterRoutes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Routing-level test (no auth/Firebase): boots a real Slim app the same
 * way public/index.php does, registers the hand-written RegisterRoutes,
 * and dispatches through Slim's router.
 *
 * Locks two things the unit-level ApiInfoApiTest cannot see:
 *  - the actual mounted path is `/api/v1/` (base `/api/v1` + path `/`);
 *    Slim is strict about the trailing slash, so `/api/v1` must NOT match.
 *  - the `$app->options('/{routes:.*}')` CORS-preflight catch-all answers
 *    OPTIONS for any path.
 *
 * Also the Phase-3 fan-out regression net: every `routes()` entry declared
 * by every class in RegisterRoutes::API_CLASSES must actually be mounted
 * (right name, pattern, methods) with no (method, full-path) collision.
 * Expectations are derived from API_CLASSES via reflection, so adding an
 * entity does NOT require editing this test — but a bad routes() shape, a
 * basePath/path that disagrees with the mounted pattern, a duplicate name,
 * or a path collision between two entities fails it immediately.
 */
class RouteSmokeTest extends TestCase
{
	private function app(): \Slim\App
	{
		$container = (new ContainerBuilder())
			->addDefinitions([
				'app.name' => 'route-test',
				'app.version' => '9.9.9',
			])
			->build();
		$app = Bridge::create($container);
		$app->addRoutingMiddleware();
		$app->addErrorMiddleware(false, false, false);
		(new RegisterRoutes())($app);
		return $app;
	}

	public function testGetApiV1WithTrailingSlashHitsApiInfo(): void
	{
		$req = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/');
		$res = $this->app()->handle($req);
		$this->assertSame(200, $res->getStatusCode());
		$json = json_decode((string)$res->getBody(), true);
		$this->assertSame('route-test', $json['server_name']);
		$this->assertSame('9.9.9', $json['version']);
	}

	public function testGetApiV1WithoutTrailingSlashDoesNotMatch(): void
	{
		$req = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1');
		$res = $this->app()->handle($req);
		// Not the ApiInfo 200 — the trailing slash is mandatory.
		$this->assertNotSame(200, $res->getStatusCode());
		// 405 (not 404): the OPTIONS `/{routes:.*}` CORS catch-all matches
		// every path, so an unmatched concrete path resolves as "path known,
		// GET not allowed". This is inherited from the legacy router and is
		// intentional (the catch-all must keep answering CORS pre-flight).
		$this->assertSame(405, $res->getStatusCode());
	}

	public function testOptionsCatchAllAnswersPreflightForAnyPath(): void
	{
		$req = (new ServerRequestFactory())->createServerRequest('OPTIONS', '/anything/at/all');
		$res = $this->app()->handle($req);
		$this->assertSame(200, $res->getStatusCode());
	}

	/**
	 * Flatten every routes() entry from every API_CLASSES class (read via
	 * reflection — it's a private const).
	 *
	 * @return list<array{methods:string[],basePath:string,path:string,name:string}>
	 */
	private static function declaredOps(): array
	{
		$apiClasses = (new ReflectionClass(RegisterRoutes::class))->getConstant('API_CLASSES');
		$ops = [];
		foreach ($apiClasses as $apiClass) {
			foreach ($apiClass::routes() as $op) {
				$ops[] = $op;
			}
		}
		return $ops;
	}

	public function testEveryDeclaredRouteIsMountedWithMatchingNameAndPattern(): void
	{
		$collector = $this->app()->getRouteCollector();
		$ops = self::declaredOps();
		$this->assertNotEmpty($ops, 'API_CLASSES must declare at least one route');

		foreach ($ops as $op) {
			$fullPath = $op['basePath'] . $op['path'];
			$route = $collector->getNamedRoute($op['name']); // throws if unmounted
			$this->assertSame(
				$fullPath,
				$route->getPattern(),
				"route '{$op['name']}' pattern must equal basePath.path",
			);
			foreach ($op['methods'] as $m) {
				$this->assertContains(
					$m,
					$route->getMethods(),
					"route '{$op['name']}' must accept {$m}",
				);
			}
		}
	}

	public function testNoDuplicateRouteNamesOrMethodPathCollisions(): void
	{
		$names = [];
		$methodPaths = [];
		foreach (self::declaredOps() as $op) {
			$names[] = $op['name'];
			foreach ($op['methods'] as $m) {
				$methodPaths[] = $m . ' ' . $op['basePath'] . $op['path'];
			}
		}
		$this->assertSame(
			array_values(array_unique($names)),
			$names,
			'route names must be unique across API_CLASSES',
		);
		$this->assertSame(
			array_values(array_unique($methodPaths)),
			$methodPaths,
			'no two routes may share a (method, full-path) across API_CLASSES',
		);
	}

	public function testRouteCollectorHoldsExactlyDeclaredRoutesPlusOptionsCatchAll(): void
	{
		// Every declared (class) route + the single OPTIONS `/{routes:.*}`
		// CORS catch-all RegisterRoutes registers first. Auto-adjusts as
		// Phase-3 adds entities; a stray double-registration still fails.
		$expected = count(self::declaredOps()) + 1;
		$this->assertCount($expected, $this->app()->getRouteCollector()->getRoutes());
	}
}
