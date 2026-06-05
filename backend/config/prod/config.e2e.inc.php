<?php

/**
 * E2E test environment config — TRACKED (unlike the operator-local config.inc.php).
 *
 * Purpose: give CI and local E2E one reviewed source of truth, selected ONLY by
 * docker-compose.e2e.yaml (which bind-mounts it over config/prod/config.inc.php
 * in the E2E php container). Production is untouched (it ships its own un-tracked
 * config.inc.php).
 *
 * PROD-FAITHFUL query behaviour: this config inherits config.docker.inc.php's
 * `EMULATE_PREPARES=false`, and docker-compose.e2e.yaml leaves the E2E MySQL on
 * its 8.0 default sql_mode (ONLY_FULL_GROUP_BY ON). So the E2E stack exercises
 * the REAL production query path and acts as a regression guard for the issues
 * UNIMPLEMENTED.md once tracked as §0-2 (ONLY_FULL_GROUP_BY) / §0-3
 * (same-named placeholder reuse). Those were FIXED in the code-first rewrite —
 * verified by the integration suite (216 tests green under EMULATE_PREPARES=false
 * + ONLY_FULL_GROUP_BY: see tests/Integration/IntegrationTestCase.php:57 and the
 * per-repo *ApiTest's) AND by a clean-stack E2E run with these workarounds
 * removed. The E2E config therefore overrides ONLY what is genuinely test-
 * specific (not prod-correctness), namely the two items below.
 */

// Reuse the docker emulator template: PDO→webmon-db (incl. EMULATE_PREPARES=false,
// FOUND_ROWS), Firebase Auth Emulator wiring + runtime-materialized synthetic SA.
// Requiring it also runs its top-level side effects (putenv emulator host,
// materialize SA).
$docker = require __DIR__ . '/config.docker.inc.php';

return array_merge($docker, [
	// Rate limiter effectively disabled for E2E. The suite drives many
	// users/requests from a single client IP (the runner/localhost), which trips
	// the REMOTE_ADDR-keyed 60/min default and the tight per-route caps (e.g.
	// useInviteKey 10/min) as "Too many requests" (429) — flakes unrelated to the
	// behaviour under test. Production keeps default.inc.php's real limits.
	'ratelimiter.default' => [
		'policy' => 'sliding_window',
		'limit' => 100000,
		'interval' => '1 minute',
	],
	'ratelimiter.routes' => [],

	// Log under a world-writable temp dir instead of config.docker.inc.php's
	// /var/log/apache2. The E2E compose does NOT bind-mount a logs dir, so on a
	// Linux CI runner the www-data PHP worker could not create a log file under
	// the root-owned /var/log/apache2. sys_get_temp_dir() (/tmp, sticky world-
	// writable) is always writable by www-data — keeps logging working without a
	// host mount or image change.
	'logger.path' => \sys_get_temp_dir() . '/trvis-e2e-app',
]);
