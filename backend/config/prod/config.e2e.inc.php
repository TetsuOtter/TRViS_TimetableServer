<?php

/**
 * E2E test environment config — TRACKED (unlike the operator-local config.inc.php).
 *
 * Purpose: give CI and local E2E one reviewed source of truth for the
 * dev/emulator-only workarounds that UNIMPLEMENTED.md §0 previously kept
 * un-tracked (a gitignored config.inc.php + a `SET PERSIST` on the running DB).
 * On a CLEAN database those workarounds are mandatory — without them
 * `createProject` 500s and the whole E2E suite dies in setup (see §0-2 / §0-3).
 *
 * Scope / safety: this file is selected ONLY by docker-compose.e2e.yaml, which
 * bind-mounts it over config/prod/config.inc.php inside the E2E php container.
 * Production is untouched: it ships its own un-tracked config.inc.php, and even
 * the shared dev stack is untouched because we do NOT edit config.docker.inc.php
 * or mysql/conf.d. This is "disclosure, not masking" — but note it only un-blocks
 * the emulator E2E run. The §0-3 prod-correctness question (prod default is
 * EMULATE_PREPARES=false, so prod createProject may genuinely be broken) stays
 * OPEN and is not resolved here.
 *
 * Built by reusing the tracked docker template and overlaying exactly two things:
 *   1. EMULATE_PREPARES=true (§0-3) so duplicate same-named placeholders work.
 *   2. The rate limiter effectively disabled so localhost request volume from the
 *      suite does not trip the per-IP caps (429 flakes unrelated to the SUT).
 * The §0-2 ONLY_FULL_GROUP_BY relaxation is a MySQL-server setting and lives in
 * docker-compose.e2e.yaml's mysql `command:` (also commented there).
 */

// Reuse the docker emulator template: PDO→webmon-db, Firebase Auth Emulator
// wiring + runtime-materialized synthetic SA, logger path. Requiring it also runs
// its top-level side effects (putenv FIREBASE_AUTH_EMULATOR_HOST, materialize SA).
$docker = require __DIR__ . '/config.docker.inc.php';

return array_merge($docker, [
	// §0-3: native prepared statements reject reuse of a same-named placeholder
	// (e.g. ProjectsRepo::selectProjectOne binds :projects_id twice in one query)
	// → SQLSTATE[HY093] → 500 on createProject and most create flows. Emulating
	// prepares makes the E2E suite's create path work. array_merge on the parent
	// replaces 'pdo.options' wholesale, so rebuild it from the docker options and
	// flip only this one flag (keep FOUND_ROWS etc.).
	'pdo.options' => array_merge($docker['pdo.options'], [
		\PDO::ATTR_EMULATE_PREPARES => true,
	]),

	// H4 rate limiting — effectively disabled for E2E. The suite drives many
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
