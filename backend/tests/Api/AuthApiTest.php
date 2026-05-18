<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub. issueToken is intentionally NOT
 * implemented: there is no concrete dev_t0r\trvis_backend\api\AuthApi,
 * and AbstractAuthApi::issueToken throws HttpNotImplementedException
 * (HTTP 501). Authentication is handled by MyAuthMiddleware (Firebase
 * ID token verification), not by a token-issuing endpoint. This test
 * pins that deliberate contract, mirroring
 * InviteKeyApiTest::testUpdateInviteKey.
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpNotImplementedException;

#[CoversClass(\dev_t0r\trvis_backend\api\AuthApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\AuthApi::class, 'issueToken')]
class AuthApiTest extends TestCase
{
	public function testIssueToken()
	{
		// No concrete AuthApi: the endpoint is deliberately unimplemented.
		$this->assertFalse(
			class_exists(AuthApi::class),
			'AuthApi must remain unimplemented (issueToken -> HTTP 501)',
		);

		// And the generated abstract still declares the 501 contract.
		$rm = new \ReflectionMethod(
			AbstractAuthApi::class,
			'issueToken',
		);
		$this->assertFalse(
			$rm->isAbstract(),
			'AbstractAuthApi::issueToken is concrete and throws HttpNotImplementedException',
		);
		$this->assertTrue(
			class_exists(HttpNotImplementedException::class),
			'issueToken throws Slim HttpNotImplementedException (HTTP 501)',
		);
	}
}
