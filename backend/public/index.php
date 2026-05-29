<?php

/**
 * TRViS Timetable Server backend — front controller.
 *
 * Code-first: the PHP implementation is the API source of truth. swagger-php
 * generates openapi.json from #[OA\*] attributes; openapi-typescript turns that
 * into the frontend client. This file only boots the Slim app.
 */

$DOCUMENT_ROOT_DIR = __DIR__ . '/..';
require_once $DOCUMENT_ROOT_DIR . '/vendor/autoload.php';

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use dev_t0r\App\RegisterDependencies;
use dev_t0r\App\RegisterRoutes;
use dev_t0r\App\RegisterMiddlewares;
use dev_t0r\App\ResponseEmitter;
use Neomerx\Cors\Contracts\AnalyzerInterface;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Middleware\ErrorMiddleware;

// Instantiate PHP-DI ContainerBuilder
$builder = new ContainerBuilder();

// consider prod by default
$env = 'prod';
switch (strtolower($_SERVER['APP_ENV'] ?? 'prod')) {
    case 'development':
    case 'dev':
        $env = 'dev';
        break;
    case 'production':
    case 'prod':
    default:
        $env = 'prod';
}

// Main configuration
$builder->addDefinitions($DOCUMENT_ROOT_DIR . "/config/{$env}/default.inc.php");

// Config file for the environment if exists
$userConfig = $DOCUMENT_ROOT_DIR . "/config/{$env}/config.inc.php";
if (file_exists($userConfig)) {
    $builder->addDefinitions($userConfig);
}

// Set up dependencies
$dependencies = new RegisterDependencies();
$dependencies($builder);

// Build PHP-DI Container instance
$container = $builder->build();

// Instantiate the app
$app = Bridge::create($container);

// Register middleware
$middleware = new RegisterMiddlewares();
$middleware($app);

// Register routes
// yes, it's anti-pattern you shouldn't get deps from container directly
$routes = $container->get(RegisterRoutes::class);
$routes($app);

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Get error middleware from container
// also anti-pattern, of course we know
$errorMiddleware = $container->get(ErrorMiddleware::class);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = (new ResponseEmitter())
    ->setRequest($request)
    ->setErrorMiddleware($errorMiddleware)
    ->setAnalyzer($container->get(AnalyzerInterface::class));

$responseEmitter->emit($response);
