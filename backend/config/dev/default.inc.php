<?php

/**
 * App configuration defaults for development.
 * This file used when 'APP_ENV' variable set to 'dev' or 'development'. Check public/.htaccess file
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set("display_errors", 1);

/**
 * Each environment(dev, prod) should contain two files default.inc.php and config.inc.php.
 * This is the first file with development defaults. It contains all data which can be safely committed
 * to VCS(version control system). For sensitive values(passwords, api keys, emails) use config.inc.php
 * and make sure it's excluded from VCS by .gitignore.
 * do not add dependencies here, use dev_t0r\App\RegisterDependencies class
 * @see https://php-di.org/doc/php-definitions.html#values
 */
return [
	'mode' => 'development',

	// Returns a detailed HTML page with error details and
	// a stack trace. Should be disabled in production.
	'slim.displayErrorDetails' => true,

	// Whether to display errors on the internal PHP log or not.
	'slim.logErrors' => false,

	// If true, display full errors with message and stack trace on the PHP log.
	// If false, display only "Slim Application Error" on the PHP log.
	// Doesn't do anything when 'logErrors' is false.
	'slim.logErrorDetails' => false,

	// CORS settings
	// @see https://github.com/neomerx/cors-psr7/blob/master/src/Strategies/Settings.php
	'cors.settings' => [
		isset($_SERVER['HTTPS']) ? 'https' : 'http', // serverOriginScheme
		$_SERVER['SERVER_NAME'], // serverOriginHost
		null, // serverOriginPort
		false, // isPreFlightCanBeCached
		0, // preFlightCacheMaxAge
		false, // isForceAddMethods
		false, // isForceAddHeaders
		// 認証は Cookie ではなく Authorization: Bearer のみ。credentials を有効にすると
		// neomerx が「リクエスト Origin の反射 + Access-Control-Allow-Credentials: true」を
		// 返し、任意オリジン許可と組み合わさって CSRF 的悪用の踏み台になるため無効化する。
		false, // isUseCredentials
		true, // areAllOriginsAllowed
		[], // allowedOrigins
		true, // areAllMethodsAllowed
		[], // allowedLcMethods
		'GET, POST, PUT, PATCH, DELETE', // allowedMethodsList
		true, // areAllHeadersAllowed
		[], // allowedLcHeaders
		'authorization, content-type, x-requested-with', // allowedHeadersList
		// H4: expose the rate-limit headers so the openapi-fetch frontend can
		// read them (Retry-After + the X-RateLimit-* trio).
		'X-Total-Count, Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Retry-After', // exposedHeadersList
		true, // isCheckHost
	],

	// H4: rate limiting / throttling.
	// Cache dir under sys_get_temp_dir() — always writable in the
	// php:8.2-apache container; deliberately NOT under backend/cache because
	// compose bind-mounts ./backend read-only.
	'ratelimiter.cache_dir' => \sys_get_temp_dir() . '/trvis-ratelimiter',
	// Default: 60 requests / 1 minute, sliding window, per client IP.
	'ratelimiter.default' => [
		'policy' => 'sliding_window',
		'limit' => 60,
		'interval' => '1 minute',
	],
	// Per-route overrides keyed by "<METHOD> <routeName>" or "<routeName>".
	// useInviteKey is POST /api/v1/invite_keys/{inviteKeyId} (an invite-key
	// capability redemption) — tighten it to blunt brute-force.
	'ratelimiter.routes' => [
		'POST useInviteKey' => [
			'policy' => 'sliding_window',
			'limit' => 10,
			'interval' => '1 minute',
		],
		// dumpTimetable is GET /api/v1/dump/{workGroupId} (an expensive bulk
		// export of an entire WorkGroup) — throttle it to 1 req / 5 s.
		'GET dumpTimetable' => [
			'policy' => 'sliding_window',
			'limit' => 1,
			'interval' => '5 seconds',
		],
	],

	// PDO
	'pdo.dsn' => 'mysql:host=localhost;charset=utf8mb4',
	'pdo.username' => 'root',
	'pdo.password' => 'root',
	'pdo.options' => [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		// H7: rowCount() after UPDATE must report matched rows, not changed
		// rows, so a no-op PATCH on an existing row is not a false 404.
		\PDO::MYSQL_ATTR_FOUND_ROWS => true,
		// L5: disable emulated prepares so the driver sends real prepared
		// statements to MySQL, preventing silent type coercions and ensuring
		// accurate error information on constraint violations.
		\PDO::ATTR_EMULATE_PREPARES => false,
	],

	// logger
	'logger.name' => 'App',
	'logger.path' => \realpath(__DIR__ . '/../../logs') . '/app',
	'logger.level' => 100, // equals DEBUG level
	'logger.options' => [],

	'app.name' => 'trvis-backend-dev',
	'app.version' => '1.0.0-dev',
];
