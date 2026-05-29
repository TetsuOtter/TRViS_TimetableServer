#!/bin/sh
# docker-compose.test.yaml の test-runner エントリポイント。
# CI (backend-tests.yml) と同じ三段構え:
#   1. dev 依存を vendor/ ボリュームへ install (ホストの vendor は触らない)
#   2. silent-skip ガード: IntegrationTestCase は DB 未到達時に markTestSkipped
#      するため、phpunit と同一 env で実接続しスキーマ投入を検証、不足なら落とす
#   3. PHPUnit 実行 (引数があればそれを exec、無ければ composer test)
set -e

composer install --no-interaction --prefer-dist --no-progress

php -r '
	$pdo = new PDO(getenv("TEST_DB_DSN"), getenv("TEST_DB_USER"), getenv("TEST_DB_PASS"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$n = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \"test\"")->fetchColumn();
	fwrite(STDERR, "test schema table count: {$n}\n");
	if ($n < 10) {
		fwrite(STDERR, "Smoke check FAILED: test schema not populated; integration tests would silently skip.\n");
		exit(1);
	}
	fwrite(STDERR, "Smoke check passed.\n");
'

if [ "$#" -gt 0 ]; then
	exec "$@"
else
	exec composer test
fi
