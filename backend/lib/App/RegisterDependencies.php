<?php

/**
 * TRViS用 時刻表管理用API
 * PHP version 7.4
 *
 * @package dev_t0r
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */

/**
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://github.com/openapitools/openapi-generator.git
 */

declare(strict_types=1);

/**
 * NOTE: This class is auto generated by the openapi generator program.
 * https://github.com/openapitools/openapi-generator
 * Do not edit the class manually.
 */
namespace dev_t0r\App;

/**
 * RegisterDependencies
 *
 * Recommendations from template creator:
 *
 * I don't use imports(eg. use Slim\Middleware\ErrorMiddleware) here because each package unlikely
 * be used in code twice. It helps to keep that file short and make Git history cleaner.
 *
 * This class declared as final because two classes with dependency injections can cause confusion. Edit
 * template of this class or use your own implementation instead(overwrite index.php to import your
 * custom class).
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

			// DataMocker
			// @see https://github.com/ybelenko/openapi-data-mocker-server-middleware
			\OpenAPIServer\Mock\OpenApiDataMockerInterface::class => \DI\create(\OpenAPIServer\Mock\OpenApiDataMocker::class)
				->method('setModelsNamespace', 'dev_t0r\trvis_backend\model\\'),

			\OpenAPIServer\Mock\OpenApiDataMockerRouteMiddlewareFactory::class => \DI\autowire()
				->constructorParameter('getMockStatusCodeCallback', \DI\get('mocker.getMockStatusCodeCallback'))
				->constructorParameter('afterCallback', \DI\get('mocker.afterCallback')),

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

				$logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor);
				$logger->pushProcessor(new \Monolog\Processor\WebProcessor);
				$logger->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor);
				$logger->pushProcessor(new \Monolog\Processor\IntrospectionProcessor);

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
		]);
	}
}
