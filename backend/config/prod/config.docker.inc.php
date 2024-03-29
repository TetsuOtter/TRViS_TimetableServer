<?php

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
