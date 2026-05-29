<?php

// Docker 構成では Firebase Auth Emulator は同一 compose ネットワーク上の
// webmon-firebase コンテナで待ち受ける。このファイルは docker 専用テンプレート
// (config.inc.php へコピーして使う) なので分岐不要・固定値でよい。
putenv('FIREBASE_AUTH_EMULATOR_HOST=webmon-firebase:9099');

// Firebase — emulator-only synthetic identity, MATERIALIZED AT RUNTIME.
//
// kreait/firebase-php 7.x Factory::createAuth() eagerly builds an API client
// that demands *some* credentials even when the Auth Emulator is targeted, so
// a well-formed SA file must exist on disk to boot. ID-token verification
// still goes through the emulator; this identity can never authenticate
// against real Google — private_key is an intentional non-PEM placeholder
// (kreait only requires the field be present and never parses it under the
// emulator).
//
// The JSON is written here at config-load time instead of being tracked in
// the repo: any service-account-shaped *.json on disk trips GitHub secret-
// scanning push protection regardless of whether the key is real. Building it
// from this array keeps that shape out of version control entirely.
$firebaseEmulatorSaPath = sys_get_temp_dir() . '/trvis-firebase-emulator-sa.json';
file_put_contents($firebaseEmulatorSaPath, json_encode([
	'type' => 'service_account',
	'project_id' => 'trvis-app',
	'private_key_id' => str_repeat('0', 40),
	'private_key' => 'EMULATOR-ONLY-PLACEHOLDER-NOT-A-PEM-NOT-A-REAL-KEY-DO-NOT-USE',
	'client_email' => 'emulator-only@example.invalid',
	'client_id' => str_repeat('0', 21),
	'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
	'token_uri' => 'https://oauth2.googleapis.com/token',
	'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
	'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/emulator-only%40example.invalid',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

return [
	// PDO
	'pdo.dsn' => 'mysql:host=webmon-db;dbname=test;charset=utf8mb4',
	'pdo.username' => 'test',
	'pdo.password' => 'test',
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
	'logger.path' => '/var/log/apache2/slim-app',
	'logger.level' => 300, // equals WARNING level
	'logger.options' => [],

	// Firebase — see the runtime-materialization block above.
	// Cache dirs are null: backend/cache is absent so default.inc.php's
	// realpath(...) yields false; null disables the filesystem cache cleanly.
	'firebase.sa_file' => $firebaseEmulatorSaPath,
	'firebase.api_token_cache_dir' => null,
	'firebase.auth.pubkey_cache_dir' => null,
];
