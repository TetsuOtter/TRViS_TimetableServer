<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for StopPattern (StopPatternApi -> StopPatternsService).
 * Drives the service directly; route registration / OA parsing / container
 * glue are covered separately (drift tests + byte-stable openapi.json regen
 * + route-wiring smoke).
 *
 * Covers the lines_id UPDATE path: StopPatternsRepo::updateStopPattern maps
 * 'lines_id' -> "project_lines_id = :lines_id" in the SET clause.
 *
 * Line rows are inserted directly via PDO (LineService is not yet ported to
 * the new backend — scope rule §0), registered for FK-safe teardown.
 *
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\StopPatternApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternApi::class, 'createStopPattern')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternApi::class, 'getStopPattern')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternApi::class, 'getStopPatternList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternApi::class, 'updateStopPattern')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternApi::class, 'deleteStopPattern')]
class StopPatternApiTest extends IntegrationTestCase
{
	private function svc(): StopPatternsService
	{
		return new StopPatternsService($this->db, $this->logger);
	}

	/**
	 * Insert a project_lines row directly via PDO (LineService is not yet
	 * ported to the new backend — §0 scope rule). Register for FK-safe
	 * teardown via register().
	 */
	private function newLine(string $name = 'L'): UuidInterface
	{
		$id = Uuid::uuid7();
		$this->db->prepare(
			"INSERT INTO project_lines (project_lines_id, projects_id, description, owner, name)"
			. " VALUES (:id, :pid, '', :owner, :name)"
		)->execute([
			':id' => $id->getBytes(),
			':pid' => $this->projectId->getBytes(),
			':owner' => $this->userId,
			':name' => $name,
		]);
		$this->register('project_lines', 'project_lines_id', (string)$id);
		return $id;
	}

	private function createOne(UuidInterface $lineId, array $over = []): StopPattern
	{
		$data = array_merge([
			'name' => 'Local',
			'lines_id' => $lineId,
			'direction' => 1,
		], $over);
		$r = $this->svc()->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(StopPattern::class, $data)],
		);
		$this->assertOk($r, 'createStopPattern');
		$o = $r->value[0];
		$this->register('stop_patterns', 'stop_patterns_id', (string)$o->stop_patterns_id);
		return $o;
	}

	public function testCreateStopPattern(): void
	{
		$line = $this->newLine();
		$o = $this->createOne($line);
		$this->assertSame((string)$this->projectId, (string)$o->projects_id);
		$this->assertSame((string)$line, (string)$o->lines_id, 'lines_id <-> project_lines_id alias');
		$this->assertSame(1, $o->direction);
	}

	public function testGetStopPattern(): void
	{
		$o = $this->createOne($this->newLine());
		$g = $this->svc()->getOne($this->userId, $o->stop_patterns_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stop_patterns_id, (string)$g->value->stop_patterns_id);

		// non-member -> 404
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stop_patterns_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStopPatternList(): void
	{
		$line = $this->newLine();
		$a = $this->createOne($line, ['name' => 'A']);
		$b = $this->createOne($line, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $this->projectId, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->stop_patterns_id, $list->value);
		$this->assertContains((string)$a->stop_patterns_id, $ids);
		$this->assertContains((string)$b->stop_patterns_id, $ids);
	}

	public function testUpdateStopPattern(): void
	{
		$lineA = $this->newLine('A');
		$lineB = $this->newLine('B');
		$o = $this->createOne($lineA, ['name' => 'before']);
		$this->assertSame((string)$lineA, (string)$o->lines_id);
		$before = $this->fetchUpdatedAt((string)$o->stop_patterns_id);
		sleep(1);
		$bodyModel = $this->makeModel(
			StopPattern::class,
			['name' => 'after', 'lines_id' => $lineB, 'direction' => -1],
		);
		$u = $this->svc()->update(
			$this->userId,
			$o->stop_patterns_id,
			$bodyModel,
			['name', 'lines_id', 'direction'],
		);
		$this->assertOk($u, 'updateStopPattern');
		$g = $this->svc()->getOne($this->userId, $o->stop_patterns_id);
		$this->assertSame('after', $g->value->name);
		$this->assertSame(
			(string)$lineB,
			(string)$g->value->lines_id,
			'lines_id update via project_lines_id alias',
		);
		$this->assertSame(-1, $g->value->direction);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stop_patterns_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStopPattern(): void
	{
		$o = $this->createOne($this->newLine());
		$d = $this->svc()->delete($this->userId, $o->stop_patterns_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stop_patterns_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stop_patterns WHERE stop_patterns_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
