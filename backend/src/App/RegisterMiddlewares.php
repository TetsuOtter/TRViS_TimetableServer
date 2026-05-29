<?php

declare(strict_types=1);

namespace dev_t0r\App;

/**
 * RegisterMiddlewares
 *
 * Global middlewares only. Route-related middlewares belong in
 * \dev_t0r\App\RegisterRoutes.
 *
 * Middlewares are added by full class name so Slim resolves them from the
 * DI Container already configured (no manual option passing).
 */
final class RegisterMiddlewares
{
	/**
	 * Adds middlewares to Slim app instance.
	 *
	 * @param \Slim\App $app App instance.
	 */
	public function __invoke(\Slim\App $app): void
	{
		// Slim's $app->add() is LIFO: the LAST-added middleware is the
		// OUTERMOST (runs first). Middlewares are registered here
		// innermost-first so the resulting execution order (outer -> inner)
		// is:
		//
		//   BodySizeLimitMiddleware   (cheapest reject — outermost)
		//   ErrorMiddleware           (must wrap RoutingMiddleware — see below)
		//   RoutingMiddleware         (resolves the matched route NAME)
		//   RateLimitMiddleware       (per-IP / per-route throttle)
		//   MyAuthMiddleware          (Firebase token verification)
		//   BodyParsing
		//   handler                   (innermost)
		//
		// Rationale:
		//  - BodySize stays outermost so an oversized body is rejected before
		//    it is buffered/parsed or any token verification is spent on it
		//    (C1 / H4 cost-amplification).
		//  - ErrorMiddleware MUST be OUTSIDE RoutingMiddleware. Slim's
		//    RoutingMiddleware::performRouting() throws HttpNotFoundException /
		//    HttpMethodNotAllowedException SYNCHRONOUSLY, before it calls
		//    $handler->handle(). If Error were inside Routing it would never
		//    catch that exception and every unmatched/fuzzed path would become
		//    an uncaught 500. So Error wraps Routing here.
		//  - Routing must run BEFORE rate-limit so the matched route NAME is
		//    available for per-route limit config (RouteContext).
		//  - Rate-limit must run BEFORE auth so a throttled request to a
		//    MATCHED route never spends a Firebase token verification (the H4
		//    amplification vector). Garbage/unmatched paths are bounded
		//    structurally instead: RoutingMiddleware rejects them before
		//    MyAuthMiddleware is ever reached, so no Firebase verification is
		//    spent on them either (Routing-before-Auth, not RateLimit, is the
		//    load-bearing guarantee for fuzzed paths).

		// Parse json, form data and xml (innermost)
		$app->addBodyParsingMiddleware();

		$app->add(\dev_t0r\trvis_backend\auth\MyAuthMiddleware::class);

		// H4: throttle AFTER routing (needs the route name) but BEFORE auth
		// (so a rejected request to a matched route costs no Firebase
		// verification).
		$app->add(\dev_t0r\trvis_backend\middleware\RateLimitMiddleware::class);

		// Routing Middleware — must run before RateLimitMiddleware so the
		// matched route name is on the request when rate-limit resolves the
		// per-route config.
		$app->addRoutingMiddleware();

		// Error Middleware — registered AFTER routing so it is OUTSIDE it and
		// can catch RoutingMiddleware's synchronously-thrown Http*Exception.
		$app->add(\Slim\Middleware\ErrorMiddleware::class);

		// Added last == outermost: runs before everything else so an
		// oversized body is rejected before it is buffered/parsed or a
		// Firebase token verification is spent on it (C1 / H4).
		$app->add(\dev_t0r\trvis_backend\middleware\BodySizeLimitMiddleware::class);
	}
}
