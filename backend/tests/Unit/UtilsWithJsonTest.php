<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\unit;

use dev_t0r\trvis_backend\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Utils::withJson must never emit a success status with an empty body when
 * json_encode() fails. A bare write(json_encode($data)) would write "" (false
 * coerced to string) under a 200, which looks to the client like a valid empty
 * response. The encode-failure path must fall back to a 500 error envelope.
 */
#[CoversClass(Utils::class)]
#[CoversMethod(Utils::class, 'withJson')]
class UtilsWithJsonTest extends TestCase
{
	public function testEncodableDataKeepsStatusAndBody(): void
	{
		$resp = Utils::withJson((new ResponseFactory())->createResponse(), ['a' => 1], 201);
		$this->assertSame(201, $resp->getStatusCode());
		$this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));
		$this->assertSame('{"a":1}', (string)$resp->getBody());
	}

	public function testUnencodableDataFallsBackTo500Envelope(): void
	{
		// NAN cannot be represented in JSON -> json_encode() returns false.
		$resp = Utils::withJson((new ResponseFactory())->createResponse(), NAN, 200);
		$this->assertSame(500, $resp->getStatusCode(), 'encode failure must not stay 200');
		$decoded = json_decode((string)$resp->getBody(), true);
		$this->assertIsArray($decoded, 'fallback body must be valid JSON, not empty');
		$this->assertSame(500, $decoded['code']);
		$this->assertSame('Internal Server Error', $decoded['message']);
	}
}
