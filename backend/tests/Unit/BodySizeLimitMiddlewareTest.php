<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\middleware\BodySizeLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * M9 unit test (no database, no Firebase, no Slim app): instantiates
 * BodySizeLimitMiddleware directly and drives it with a synthetic
 * ServerRequestInterface built via Slim\Psr7\Factory\ServerRequestFactory.
 * The inner RequestHandler is a tiny inline class that records whether it
 * was invoked, so we can assert "handler NOT called" for rejected requests.
 *
 * Regression criteria (one per test method):
 *
 *   testOversizedContentLengthHeaderIs413BeforeHandler
 *     Sends Content-Length: MAX+1.  Primary signal (line 41-43 of the
 *     middleware): is_numeric($contentLength) && (int)$cl > $limit.  The
 *     middleware must short-circuit with 413 and the handler must NOT be
 *     invoked.  If that branch were removed, the request falls through to
 *     the handler (200), and both the status assertion and the
 *     "handler not invoked" assertion fail.
 *
 *   testAtLimitContentLengthPassesThrough
 *     Content-Length exactly at the limit (=, not >) must pass through —
 *     pins the strict-greater-than semantics of the guard.  If the guard
 *     were changed to >=, this case is incorrectly rejected (413), and the
 *     assertSame(200, …) fails.
 *
 *   testNoContentLengthWithSmallBodyPassesThrough
 *     No Content-Length header, body stream whose getSize() is 0.  Both
 *     signals evaluate to false so the handler must be invoked.  Pins
 *     the "unknown size falls through" contract for typical chunked /
 *     streaming requests.
 *
 *   testNoContentLengthButOversizedBodyStreamIs413
 *     No Content-Length header but body stream whose getSize() reports
 *     MAX+1.  Secondary signal (lines 49-52): $bodySize !== null && $bodySize
 *     > $limit.  Must 413 before handler.  If this branch were removed the
 *     test fails because the handler is reached (200).
 *
 *   test413ResponseIsJsonWithCodeAndMessage
 *     The 413 body is produced by Utils::withError, which writes a JSON
 *     object with "code" and "message" keys.  Pins the exact shape so a
 *     refactor that swaps withError for a plain createResponse()->withStatus()
 *     is caught.
 */
class BodySizeLimitMiddlewareTest extends TestCase
{
	/** Build a fresh middleware backed by the Slim Psr7 ResponseFactory. */
	private function middleware(): BodySizeLimitMiddleware
	{
		return new BodySizeLimitMiddleware(new ResponseFactory());
	}

	/**
	 * An inline RequestHandlerInterface that records whether it was called and
	 * always returns an HTTP 200 response.
	 *
	 * A \stdClass is used for the "called" flag rather than a bool[] because
	 * objects are always passed by handle (reference semantics survive the
	 * array-return from this method), whereas a returned array is a value copy
	 * that would disconnect the anonymous class's array reference from the
	 * caller's copy.
	 *
	 * @return array{0: RequestHandlerInterface, 1: \stdClass}
	 */
	private function captureHandler(): array
	{
		$state = new \stdClass();
		$state->called = false;
		$handler = new class ($state) implements RequestHandlerInterface {
			public function __construct(private \stdClass $state)
			{
			}
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->state->called = true;
				$response = (new ResponseFactory())->createResponse(200);
				$response->getBody()->write('ok');
				return $response;
			}
		};
		return [$handler, $state];
	}

	/** Build a plain POST request with no body and no Content-Length header. */
	private function plainRequest(): ServerRequestInterface
	{
		return (new ServerRequestFactory())->createServerRequest('POST', '/test');
	}

	// -----------------------------------------------------------------------
	// Test cases
	// -----------------------------------------------------------------------

	/**
	 * Primary signal: Content-Length > limit → 413 BEFORE the inner handler.
	 */
	public function testOversizedContentLengthHeaderIs413BeforeHandler(): void
	{
		[$handler, $state] = $this->captureHandler();
		$request = $this->plainRequest()
			->withHeader('Content-Length', (string)(Constants::MAX_REQUEST_BODY_BYTES + 1));

		$response = $this->middleware()->process($request, $handler);

		$this->assertSame(413, $response->getStatusCode());
		$this->assertFalse($state->called, 'inner handler must NOT be invoked when Content-Length exceeds limit');
	}

	/**
	 * Boundary: Content-Length == limit must pass through (guard is strict >).
	 */
	public function testAtLimitContentLengthPassesThrough(): void
	{
		[$handler, $state] = $this->captureHandler();
		$request = $this->plainRequest()
			->withHeader('Content-Length', (string)Constants::MAX_REQUEST_BODY_BYTES);

		$response = $this->middleware()->process($request, $handler);

		$this->assertTrue($state->called, 'inner handler must be invoked when Content-Length == limit');
		$this->assertSame(200, $response->getStatusCode());
	}

	/**
	 * No Content-Length header + small (empty) body stream → passes through.
	 */
	public function testNoContentLengthWithSmallBodyPassesThrough(): void
	{
		[$handler, $state] = $this->captureHandler();
		$request = $this->plainRequest(); // no Content-Length, body size = 0

		$response = $this->middleware()->process($request, $handler);

		$this->assertTrue($state->called, 'inner handler must be invoked when no Content-Length and body is small');
		$this->assertSame(200, $response->getStatusCode());
	}

	/**
	 * Secondary signal: no Content-Length but body stream getSize() > limit → 413.
	 *
	 * This pins the stream-size fallback (lines 49-52 of the middleware) that
	 * catches SAPI-populated streams even when the header is absent.
	 */
	public function testNoContentLengthButOversizedBodyStreamIs413(): void
	{
		[$handler, $state] = $this->captureHandler();

		$oversize = str_repeat('a', Constants::MAX_REQUEST_BODY_BYTES + 1);
		$stream = (new StreamFactory())->createStream($oversize);

		$request = $this->plainRequest()->withBody($stream);
		// Confirm: no Content-Length set, but stream size is known and > limit.
		$this->assertSame('', $request->getHeaderLine('Content-Length'));
		$this->assertGreaterThan(
			Constants::MAX_REQUEST_BODY_BYTES,
			$request->getBody()->getSize(),
		);

		$response = $this->middleware()->process($request, $handler);

		$this->assertSame(413, $response->getStatusCode());
		$this->assertFalse($state->called, 'inner handler must NOT be invoked when body stream size exceeds limit');
	}

	/**
	 * The 413 response body must be JSON produced by Utils::withError:
	 *   {"code":413,"message":"Request body too large"}
	 *
	 * This pins the exact shape — a plain withStatus(413) would have no body,
	 * which would fail both field assertions.
	 */
	public function test413ResponseIsJsonWithCodeAndMessage(): void
	{
		[$handler] = $this->captureHandler();
		$request = $this->plainRequest()
			->withHeader('Content-Length', (string)(Constants::MAX_REQUEST_BODY_BYTES + 1));

		$response = $this->middleware()->process($request, $handler);

		$this->assertSame(413, $response->getStatusCode());

		$body = json_decode((string)$response->getBody(), true);
		$this->assertIsArray($body, 'response body must be valid JSON');
		$this->assertArrayHasKey('code', $body);
		$this->assertArrayHasKey('message', $body);
		$this->assertSame(413, $body['code']);
		$this->assertSame('Request body too large', $body['message']);
	}
}
