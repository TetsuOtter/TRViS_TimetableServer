<?php

declare(strict_types=1);

namespace dev_t0r\App;

/**
 * RegisterDependencies
 *
 * Fully-qualified class names are used (instead of `use` imports) so each
 * package appears once; keeps the file short and the Git history clean.
 */
final class RegisterDependencies
{
	/**
	 * Adds dependency definitions.
	 *
	 * @param \DI\ContainerBuilder $containerBuilder Container builder.
	 *
	 * @see https://php-di.org/doc/php-definitions.html
	 */
	public function __invoke(\DI\ContainerBuilder $containerBuilder): void
	{
		$containerBuilder->addDefinitions([
			// Response factory required as typed argument in next ErrorMiddleware injection
			\Psr\Http\Message\ResponseFactoryInterface::class => \DI\factory([\Slim\Factory\AppFactory::class, 'determineResponseFactory']),

			// Slim error middleware
			// @see https://www.slimframework.com/docs/v4/middleware/error-handling.html
			\Slim\Middleware\ErrorMiddleware::class => \DI\autowire()
				->constructorParameter('displayErrorDetails', \DI\get('slim.displayErrorDetails'))
				->constructorParameter('logErrors', \DI\get('slim.logErrors'))
				->constructorParameter('logErrorDetails', \DI\get('slim.logErrorDetails'))
				->constructorParameter('logger', \DI\get(\Psr\Log\LoggerInterface::class)),

			// CORS
			\Neomerx\Cors\Contracts\AnalysisStrategyInterface::class => \DI\create(\Neomerx\Cors\Strategies\Settings::class)
				->method('setData', \DI\get('cors.settings')),

			\Neomerx\Cors\Contracts\AnalyzerInterface::class => \DI\factory([\Neomerx\Cors\Analyzer::class, 'instance']),

			// PDO class for database managing
			\PDO::class => \DI\create()
				->constructor(
					\DI\get('pdo.dsn'),
					\DI\get('pdo.username'),
					\DI\get('pdo.password'),
					\DI\get('pdo.options')
				),

			// Monolog Logger
			\Psr\Log\LoggerInterface::class => \DI\factory(function (string $mode, string $name, string $path, $level, array $options = []) {
				$logger = new \Monolog\Logger($name);

				$handlers = [];
				// stream logger as default handler across all environments
				// somebody might not need it during development
				$handlers[] = new \Monolog\Handler\RotatingFileHandler(
					filename: $path,
					level: $level,
					filenameFormat: '{filename}.{date}.log',
				);

				$logger->setTimezone(new \DateTimeZone('UTC'));

				$formatter = new \Monolog\Formatter\LineFormatter(
					"[%datetime%] %channel%.%level_name%: %message% %extra%\n"
				);
				$handlers[0]->setFormatter($formatter);

				$logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
				// H5: omit 'url' (REQUEST_URI) from the WebProcessor field map.
				// Invite-key redemption is POST /invite_keys/{inviteKeyId}; the
				// inviteKeyId is a bearer capability carried in the URL path, so
				// the default WebProcessor would stamp it onto EVERY log record
				// (incl. the WARNING-level lines emitted in production), turning
				// log-read access into capability theft. Keep the other request
				// fields, drop the capability-bearing URL.
				$logger->pushProcessor(new \Monolog\Processor\WebProcessor(null, [
					'ip'          => 'REMOTE_ADDR',
					'http_method' => 'REQUEST_METHOD',
					'server'      => 'SERVER_NAME',
					'referrer'    => 'HTTP_REFERER',
					'user_agent'  => 'HTTP_USER_AGENT',
				]));
				$logger->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
				$logger->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());

				if ($mode === 'development') {
					// add dev handlers if necessary
					// @see https://github.com/Seldaek/monolog/blob/f2f66cd480df5f165391ff9b6332700d467b25ac/doc/02-handlers-formatters-processors.md#logging-in-development
				} elseif ($mode === 'production') {
					// add prod handlers
					// @see https://github.com/Seldaek/monolog/blob/f2f66cd480df5f165391ff9b6332700d467b25ac/doc/02-handlers-formatters-processors.md#send-alerts-and-emails
					// handlers which doesn't make sense during development
					// Slack, Sentry, Swift or native mailer
				}

				return $logger->setHandlers($handlers);
			})
				->parameter('mode', \DI\get('mode'))
				->parameter('name', \DI\get('logger.name'))
				->parameter('path', \DI\get('logger.path'))
				->parameter('level', \DI\get('logger.level'))
				->parameter('options', \DI\get('logger.options')),

			// Firebase
			\Kreait\Firebase\Factory::class => \DI\factory(function (
				string $projectId,
				string $serviceAccountFile,
				string $mode,
				\Psr\Log\LoggerInterface $logger,
				?string $apiTokenCacheDir,
				?string $authPubKeyCacheDir
			) {
				$factory = (new \Kreait\Firebase\Factory())
					->withAuthTokenCache(new \Symfony\Component\Cache\Adapter\FilesystemAdapter(directory: $apiTokenCacheDir))
					->withVerifierCache(new \Symfony\Component\Cache\Adapter\FilesystemAdapter(directory: $authPubKeyCacheDir))
					->withProjectId($projectId)
					->withServiceAccount($serviceAccountFile);
				// M8: Firebase Auth HTTP round-trips can carry ID tokens / refresh
				// material / SA-derived bearers. Keep them out of the shared production
				// log sink; only wire the HTTP logger in non-production modes.
				if ($mode !== 'production') {
					$factory = $factory->withHttpLogger($logger);
				}
				return $factory;
			})
				->parameter('projectId', \DI\get('firebase.project_id'))
				->parameter('serviceAccountFile', \DI\get('firebase.sa_file'))
				->parameter('mode', \DI\get('mode'))
				->parameter('logger', \DI\get(\Psr\Log\LoggerInterface::class))
				->parameter('apiTokenCacheDir', \DI\get('firebase.api_token_cache_dir'))
				->parameter('authPubKeyCacheDir', \DI\get('firebase.auth.pubkey_cache_dir'))
			,
			\Kreait\Firebase\Contract\Auth::class => \DI\factory([\Kreait\Firebase\Factory::class, 'createAuth']),

				// H4: rate-limiter storage. CacheStorage over a FilesystemAdapter
				// (no APCu/Redis in the php:8.2-apache image). The cache dir is
				// under sys_get_temp_dir() by default (always writable in the
				// container; deliberately NOT under backend/cache because compose
				// bind-mounts ./backend read-only). Injected via DI so tests swap
				// in InMemoryStorage.
				\Symfony\Component\RateLimiter\Storage\StorageInterface::class => \DI\factory(function (string $cacheDir) {
					return new \Symfony\Component\RateLimiter\Storage\CacheStorage(
						new \Symfony\Component\Cache\Adapter\FilesystemAdapter(
							namespace: '',
							defaultLifetime: 0,
							directory: $cacheDir,
						),
					);
				})
					->parameter('cacheDir', \DI\get('ratelimiter.cache_dir')),

				// FlockStore gives cross-process atomicity for the limiter under
				// Apache prefork (no shared APCu/Redis lock available).
				\Symfony\Component\Lock\LockFactory::class => \DI\factory(function (string $cacheDir) {
					return new \Symfony\Component\Lock\LockFactory(
						new \Symfony\Component\Lock\Store\FlockStore($cacheDir),
					);
				})
					->parameter('cacheDir', \DI\get('ratelimiter.cache_dir')),

				\dev_t0r\trvis_backend\middleware\RateLimitMiddleware::class => \DI\autowire()
					->constructorParameter('defaultLimit', \DI\get('ratelimiter.default'))
					->constructorParameter('routeLimits', \DI\get('ratelimiter.routes')),
		]);
	}
}
