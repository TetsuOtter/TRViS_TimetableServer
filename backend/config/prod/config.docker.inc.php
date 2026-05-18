<?php

// Docker 構成では Firebase Auth Emulator は同一 compose ネットワーク上の
// webmon-firebase コンテナで待ち受ける。このファイルは docker 専用テンプレート
// (config.inc.php へコピーして使う) なので分岐不要・固定値でよい。
putenv('FIREBASE_AUTH_EMULATOR_HOST=webmon-firebase:9099');

return [
	// PDO
	'pdo.dsn' => 'mysql:host=webmon-db;dbname=test;charset=utf8mb4',
	'pdo.username' => 'test',
	'pdo.password' => 'test',
	'pdo.options' => [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	],

	// logger
	'logger.name' => 'App',
	'logger.path' => '/var/log/apache2/slim-app',
	'logger.level' => 300, // equals WARNING level
	'logger.options' => [],
];
