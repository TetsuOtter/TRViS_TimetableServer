<?php

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\App\RegisterRoutes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Auth-501 contract pin (code-first-adapted port of the legacy
 * AuthApiTest). The legacy stub asserted AbstractAuthApi::issueToken
 * throws HTTP 501; the code-first rewrite has NO generated Abstract*
 * layer, so the equivalent invariant is: no concrete AuthApi, no `auth`
 * entry in the route registry, no `/auth/` route — authentication is
 * handled solely by MyAuthMiddleware (Firebase ID token verification),
 * never by a token-issuing endpoint.
 *
 * Plain TestCase (no DB, no CoversClass on the deliberately-absent
 * AuthApi — there is no concrete class to cover).
 */
class AuthApiTest extends TestCase
{
	public function testNoConcreteAuthApi(): void
	{
		$this->assertFalse(
			class_exists(\dev_t0r\trvis_backend\api\AuthApi::class),
			'AuthApi must remain unimplemented (no token-issuing endpoint)',
		);
	}

	public function testMyAuthMiddlewareIsTheAuthMechanism(): void
	{
		$this->assertTrue(
			class_exists(\dev_t0r\trvis_backend\auth\MyAuthMiddleware::class),
			'Authentication is handled by MyAuthMiddleware (Firebase ID token verification)',
		);
	}

	public function testNoAuthRouteRegistered(): void
	{
		$apiClasses = (array)(new ReflectionClass(RegisterRoutes::class))
			->getConstant('API_CLASSES');

		foreach ($apiClasses as $apiClass) {
			$this->assertStringNotContainsStringIgnoringCase(
				'auth',
				$apiClass,
				"RegisterRoutes::API_CLASSES must not register an Auth* API ($apiClass)",
			);
			foreach ($apiClass::routes() as $op) {
				$this->assertStringNotContainsStringIgnoringCase(
					'/auth',
					$op['basePath'] . $op['path'],
					"No /auth/ route may be registered (found in {$apiClass})",
				);
			}
		}
	}
}
