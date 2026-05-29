<?php

namespace dev_t0r\trvis_backend\tests\integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * DB統合テストの共通基底。
 *
 * - skip-guard: テスト用MySQLに繋げない環境では markTestSkipped
 *   ($ composer test をDB無しでもgreenに保つ)。
 * - setUp で Project + adminユーザ権限のフィクスチャを作成、tearDown で
 *   登録された行をFK安全順に明示削除 (サービスが自前でtxを張るので外側tx
 *   ラップはしない)。
 * - makeModel(): OASスキーマの全プロパティを埋めてから setData する。
 *   phpunit.xml.dist の failOnWarning="true" 下で、Repoが未設定の任意
 *   プロパティを参照した際の "Undefined array key" 警告がテストを落とす
 *   のを防ぐ (モデル読み取り側の防御)。なお Repo の SELECT が列を出し
 *   忘れた場合の同警告は makeModel では防げず、failOnWarning が検出する。
 */
abstract class IntegrationTestCase extends TestCase
{
	protected static ?PDO $pdo = null;
	protected static ?string $skipReason = null;

	protected PDO $db;
	protected NullLogger $logger;
	protected string $userId;       // admin on the fixture project
	protected string $nonMemberId;  // no privilege anywhere
	protected UuidInterface $projectId;

	/** @var array<array{table:string,idCol:string,id:string}> children-first reverse order */
	private array $cleanup = [];

	public static function setUpBeforeClass(): void
	{
		$dsn = getenv('TEST_DB_DSN') ?: 'mysql:host=webmon-db;dbname=test;charset=utf8mb4';
		$user = getenv('TEST_DB_USER') ?: 'test';
		$pass = getenv('TEST_DB_PASS') ?: 'test';
		try {
			self::$pdo = new PDO($dsn, $user, $pass, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				// H7: mirror the dev/prod connection so update* rowCount()
				// reports matched (not changed) rows — a no-op PATCH on a
				// live row must not be a false 404. (dev config = ERRMODE +
				// FOUND_ROWS; AUTOCOMMIT=false is prod-only and orthogonal to
				// H7, so it is intentionally not set here.)
				PDO::MYSQL_ATTR_FOUND_ROWS => true,
			]);
		} catch (\PDOException $e) {
			self::$skipReason = 'Test DB unavailable (' . $e->getMessage() . ')';
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::$pdo = null;
	}

	protected function setUp(): void
	{
		if (self::$skipReason !== null) {
			$this->markTestSkipped(self::$skipReason);
		}
		$this->db = self::$pdo;
		$this->logger = new NullLogger();
		$suffix = bin2hex(random_bytes(5));
		$this->userId = "it-U-$suffix";
		$this->nonMemberId = "it-X-$suffix";
		$this->projectId = Uuid::uuid7();

		// Project + admin privilege fixture
		$this->db->prepare(
			"INSERT INTO projects (projects_id, owner, description, name) "
			. "VALUES (:id, :owner, '', :name)"
		)->execute([
			':id' => $this->projectId->getBytes(),
			':owner' => $this->userId,
			':name' => "IT Project $suffix",
		]);
		$this->register('projects', 'projects_id', (string)$this->projectId);
		$this->db->prepare(
			"INSERT INTO projects_privileges (uid, projects_id, privilege_type) "
			. "VALUES (:uid, :pid, 3)"
		)->execute([
			':uid' => $this->userId,
			':pid' => $this->projectId->getBytes(),
		]);
		// privileges row removed implicitly when the project row is deleted in
		// tearDown? No FK cascade -> delete explicitly first.
		$this->cleanup[] = [
			'table' => 'projects_privileges',
			'idCol' => 'projects_id',
			'id' => (string)$this->projectId,
		];
	}

	protected function tearDown(): void
	{
		if (self::$skipReason !== null) {
			return;
		}
		if ($this->db->inTransaction()) {
			$this->db->rollBack();
		}
		// children-first: entries were appended in creation order, so reverse.
		foreach (array_reverse($this->cleanup) as $c) {
			try {
				$this->db->prepare(
					"DELETE FROM `{$c['table']}` WHERE `{$c['idCol']}` = :id"
				)->execute([':id' => $this->hexToBin($c['id'], $c['table'])]);
			} catch (\Throwable $e) {
				// best-effort cleanup
			}
		}
		$this->cleanup = [];
	}

	/**
	 * Register a row for FK-safe teardown. Call right after creating it
	 * (creation order; tearDown deletes in reverse = children first).
	 */
	protected function register(string $table, string $idCol, string $uuid): void
	{
		$this->cleanup[] = ['table' => $table, 'idCol' => $idCol, 'id' => $uuid];
	}

	private function hexToBin(string $uuid, string $table): string
	{
		// projects_privileges keyed by projects_id (a UUID); all our id values
		// are UUID strings -> BINARY(16)
		return Uuid::fromString($uuid)->getBytes();
	}

	/**
	 * Build a model with every OAS property set (provided value or null),
	 * so Repo reads of optional props don't raise undefined-key warnings
	 * (which phpunit converts to exceptions).
	 *
	 * @param class-string $cls
	 * @param array<string,mixed> $data
	 */
	protected function makeModel(string $cls, array $data): object
	{
		$schema = $cls::getOpenApiSchema();
		$props = array_keys((array)($schema['properties'] ?? []));
		$full = array_fill_keys($props, null);
		foreach ($data as $k => $v) {
			$full[$k] = $v;
		}
		$m = new $cls();
		$m->setData($full);
		return $m;
	}

	/** assert a RetValueOrError is not an error, with a helpful message */
	protected function assertOk(object $r, string $ctx = ''): void
	{
		$this->assertFalse(
			$r->isError,
			$ctx . ($r->isError ? " -> [{$r->statusCode}] {$r->errorMsg}" : ''),
		);
	}
}
