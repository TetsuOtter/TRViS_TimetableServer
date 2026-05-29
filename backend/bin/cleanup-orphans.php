#!/usr/bin/env php
<?php

/**
 * M6 out-of-band orphan cleanup script.
 *
 * Purpose: Reclaim child rows (trains, timetable_rows) whose parents were
 *          soft-deleted by TrainsService::delete / WorksService::delete without
 *          cascading. This is the out-of-band approach chosen over in-request
 *          cascade (M6 design decision).
 *
 * Usage:   php bin/cleanup-orphans.php [--dry-run] [--help]
 *
 * Flags:
 *   --dry-run   Report counts of rows that WOULD be soft-deleted; write nothing.
 *   --help      Print this usage text and exit 0.
 *
 * Environment:
 *   APP_ENV     'dev' or 'prod' (default: prod). Selects config/{env}/ directory.
 *               The script honours config/{env}/config.inc.php if it exists, exactly
 *               as public/index.php does.
 *
 * Suitable for cron. Direct invocation is required when passing --dry-run because
 * composer script arg-forwarding is not reliable across platforms:
 *   php bin/cleanup-orphans.php --dry-run
 */

if (\PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

// ---------------------------------------------------------------------------
// Arg parsing
// ---------------------------------------------------------------------------
$args = array_slice($argv, 1);
$dryRun = false;

foreach ($args as $arg) {
	switch ($arg) {
		case '--help':
		case '-h':
			echo <<<USAGE
				Usage: php bin/cleanup-orphans.php [--dry-run] [--help]

				  --dry-run   Report counts of orphaned rows; write nothing to DB.
				  --help      Print this help and exit.

				Environment:
				  APP_ENV     'dev' or 'prod' (default: prod)

				USAGE;
			exit(0);

		case '--dry-run':
			$dryRun = true;
			break;

		default:
			fwrite(STDERR, "Unknown argument: $arg\n");
			exit(1);
	}
}

// ---------------------------------------------------------------------------
// Bootstrap (mirrors public/index.php lines 11-54, no Slim/routes/middleware)
// ---------------------------------------------------------------------------
$DOCUMENT_ROOT_DIR = __DIR__ . '/..';
require_once $DOCUMENT_ROOT_DIR . '/vendor/autoload.php';

use DI\ContainerBuilder;
use dev_t0r\App\RegisterDependencies;
use dev_t0r\trvis_backend\maintenance\OrphanCleanupService;

$builder = new ContainerBuilder();

$env = 'prod';
switch (strtolower((string)($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod'))) {
	case 'development':
	case 'dev':
		$env = 'dev';
		break;
	case 'production':
	case 'prod':
	default:
		$env = 'prod';
}

$builder->addDefinitions($DOCUMENT_ROOT_DIR . "/config/{$env}/default.inc.php");

$userConfig = $DOCUMENT_ROOT_DIR . "/config/{$env}/config.inc.php";
if (file_exists($userConfig)) {
	$builder->addDefinitions($userConfig);
}

$dependencies = new RegisterDependencies();
$dependencies($builder);

$container = $builder->build();

$db = $container->get(\PDO::class);
$logger = $container->get(\Psr\Log\LoggerInterface::class);

// ---------------------------------------------------------------------------
// Run cleanup
// ---------------------------------------------------------------------------
$svc = new OrphanCleanupService($db, $logger);
$result = $svc->run($dryRun);

if ($result->isError) {
	fwrite(STDERR, "ERROR [{$result->statusCode}]: {$result->errorMsg}\n");
	exit(1);
}

$counts = $result->value;
$prefix = $dryRun ? '[DRY-RUN] Would soft-delete' : 'Soft-deleted';

echo $prefix . ": {$counts['trains']} orphaned train(s), {$counts['timetable_rows']} orphaned timetable_row(s)\n";
exit(0);
