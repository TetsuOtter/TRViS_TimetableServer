<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with a real test. getApiInfo is
 * pure (no DB, no privilege): it reflects app.name / app.version from
 * the DI container into an ApiInfo JSON body. Driven directly through
 * the Slim handler with PSR-7 request/response.
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(\dev_t0r\trvis_backend\api\ApiInfoApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\ApiInfoApi::class, 'getApiInfo')]
class ApiInfoApiTest extends TestCase
{
	private function invoke(Container $c): array
	{
		$api = new ApiInfoApi($c);
		$req = (new ServerRequestFactory())->createServerRequest('GET', '/api/info');
		$res = (new ResponseFactory())->createResponse();
		$out = $api->getApiInfo($req, $res);
		$this->assertSame(200, $out->getStatusCode());
		$body = (string)$out->getBody();
		$json = json_decode($body, true);
		$this->assertIsArray($json, "response body must be JSON: $body");
		return $json;
	}

	public function testGetApiInfo()
	{
		// defaults when the container has no app.name / app.version
		$json = $this->invoke(new Container());
		$this->assertSame('trvis-backend', $json['server_name']);
		$this->assertSame('0.0.0', $json['version']);

		// container values are reflected when present
		$c = new Container();
		$c->set('app.name', 'my-server');
		$c->set('app.version', '1.2.3');
		$json = $this->invoke($c);
		$this->assertSame('my-server', $json['server_name']);
		$this->assertSame('1.2.3', $json['version']);
	}
}
