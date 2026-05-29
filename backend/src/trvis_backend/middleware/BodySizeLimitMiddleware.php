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

/**
 * Rejects requests whose body exceeds {@see Constants::MAX_REQUEST_BODY_BYTES}
 * BEFORE body parsing and authentication run.
 *
 * This is the first layer of the C1 (unbounded bulk input DoS) defence:
 * without it, an attacker can POST a multi-MB JSON array and force the body
 * parser + per-element validation loop + model construction to allocate
 * before the BULK_INSERT_MAX_COUNT / RequestValidator count gate fires.
 * The RequestValidator count gate and PHP post_max_size/memory_limit
 * (Dockerfile) remain as defence-in-depth.
 */
final class BodySizeLimitMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly ResponseFactoryInterface $responseFactory,
	) {
	}

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		$limit = Constants::MAX_REQUEST_BODY_BYTES;

		// Primary signal: declared Content-Length. is_numeric guards against a
		// missing/garbage header (getHeaderLine returns '' when unset).
		$contentLength = $request->getHeaderLine('Content-Length');
		if (is_numeric($contentLength) && (int)$contentLength > $limit) {
			return $this->tooLarge();
		}

		// Secondary signal: a body stream whose size is known (e.g. populated
		// from CONTENT_LENGTH by the SAPI) but where the header was absent.
		// Streams of unknown size (null) fall through to the PHP ini backstop.
		$bodySize = $request->getBody()->getSize();
		if ($bodySize !== null && $bodySize > $limit) {
			return $this->tooLarge();
		}

		return $handler->handle($request);
	}

	private function tooLarge(): ResponseInterface
	{
		return Utils::withError(
			$this->responseFactory->createResponse(),
			Constants::HTTP_PAYLOAD_TOO_LARGE,
			'Request body too large',
		);
	}
}
