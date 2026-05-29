<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * H4 integration-flavoured test (no MySQL, so it belongs in tests/Unit, NOT
 * the DB-bound "Apis" suite): exercises the REAL production storage path —
 * CacheStorage over a FilesystemAdapter, plus a FlockStore-backed
 * LockFactory — and proves it actually counts/persists across sequential
 * consume() calls (i.e. the limiter state survives a round-trip through the
 * filesystem cache, which InMemoryStorage cannot prove).
 *
 * Uses a unique temp dir under sys_get_temp_dir(), removed in tearDown.
 */
class RateLimitFilesystemStorageTest extends TestCase
{
	private string $tmpDir;

	protected function setUp(): void
	{
		$this->tmpDir = \sys_get_temp_dir() . '/trvis-ratelimiter-test-' . \bin2hex(\random_bytes(8));
		\mkdir($this->tmpDir, 0700, true);
	}

	protected function tearDown(): void
	{
		if (\is_dir($this->tmpDir)) {
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST,
			);
			foreach ($it as $f) {
				$f->isDir() ? \rmdir($f->getPathname()) : \unlink($f->getPathname());
			}
			\rmdir($this->tmpDir);
		}
	}

	public function testFilesystemStorageCountsAndPersistsAcrossConsumes(): void
	{
		$storage = new CacheStorage(new FilesystemAdapter(
			namespace: '',
			defaultLifetime: 0,
			directory: $this->tmpDir,
		));
		$lockFactory = new LockFactory(new FlockStore($this->tmpDir));

		$config = ['id' => 'fs-test', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 minute'];
		$factory = new RateLimiterFactory($config, $storage, $lockFactory);
		$ip = '198.51.100.42';

		// First 3 consumes accepted, remaining tokens strictly decreasing.
		$prevRemaining = PHP_INT_MAX;
		for ($i = 1; $i <= 3; $i++) {
			$rl = $factory->create($ip)->consume(1);
			$this->assertTrue($rl->isAccepted(), "consume #{$i} must be accepted");
			$this->assertLessThan($prevRemaining, $rl->getRemainingTokens(), 'remaining tokens must decrease — proves persistence across calls');
			$prevRemaining = $rl->getRemainingTokens();
		}

		// 4th consume rejected — state persisted through the filesystem cache.
		$rl = $factory->create($ip)->consume(1);
		$this->assertFalse($rl->isAccepted(), 'consume #4 must be rejected (state persisted on disk)');
		$this->assertSame(3, $rl->getLimit());
		$this->assertGreaterThanOrEqual(1, $rl->getRetryAfter()->getTimestamp() - \time());

		// Prove it really hit the disk: a freshly constructed factory over the
		// SAME temp dir sees the exhausted bucket (no shared in-process state).
		$factory2 = new RateLimiterFactory(
			$config,
			new CacheStorage(new FilesystemAdapter(namespace: '', defaultLifetime: 0, directory: $this->tmpDir)),
			new LockFactory(new FlockStore($this->tmpDir)),
		);
		$rl2 = $factory2->create($ip)->consume(1);
		$this->assertFalse($rl2->isAccepted(), 'a new factory over the same dir must see the persisted exhausted bucket');

		// A different IP over the persisted store is independent.
		$rlOther = $factory2->create('203.0.113.99')->consume(1);
		$this->assertTrue($rlOther->isAccepted(), 'distinct IP must have its own persisted bucket');
	}
}
