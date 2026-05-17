<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * StopPattern (StopPatternApi -> StopPatternsService).
 * Covers the lines_id UPDATE path (DB alias project_lines_id = :lines_id).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\StopPatternApi
 */
class StopPatternApiTest extends IntegrationTestCase
{
	private function svc(): StopPatternsService
	{
		return new StopPatternsService($this->db, $this->logger);
	}

	private function newLine(string $name = 'L'): UuidInterface
	{
		$r = (new LineService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(Line::class, ['name' => $name, 'description' => 'd'])],
		);
		$this->assertOk($r, 'newLine');
		$id = $r->value[0]->lines_id;
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

	/**
	 * @covers ::createStopPattern
	 */
	public function testCreateStopPattern()
	{
		$line = $this->newLine();
		$o = $this->createOne($line);
		$this->assertSame((string)$this->projectId, (string)$o->projects_id);
		$this->assertSame((string)$line, (string)$o->lines_id, 'lines_id <-> project_lines_id alias');
		$this->assertSame(1, $o->direction);
	}

	/**
	 * @covers ::getStopPattern
	 */
	public function testGetStopPattern()
	{
		$o = $this->createOne($this->newLine());
		$g = $this->svc()->getOne($this->userId, $o->stop_patterns_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stop_patterns_id, (string)$g->value->stop_patterns_id);
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stop_patterns_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	/**
	 * @covers ::getStopPatternList
	 */
	public function testGetStopPatternList()
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

	/**
	 * @covers ::updateStopPattern
	 * Covers the lines_id UPDATE alias (harness gap): _keyToUpdateQuerySetLine
	 * maps 'lines_id' -> "project_lines_id = :lines_id".
	 */
	public function testUpdateStopPattern()
	{
		$lineA = $this->newLine('A');
		$lineB = $this->newLine('B');
		$o = $this->createOne($lineA, ['name' => 'before']);
		$this->assertSame((string)$lineA, (string)$o->lines_id);
		$before = $this->fetchUpdatedAt((string)$o->stop_patterns_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stop_patterns_id,
			$this->makeModel(StopPattern::class, ['name' => 'after', 'lines_id' => $lineB, 'direction' => -1]),
			['name' => 'after', 'lines_id' => $lineB, 'direction' => -1],
		);
		$this->assertOk($u, 'updateStopPattern');
		$g = $this->svc()->getOne($this->userId, $o->stop_patterns_id);
		$this->assertSame('after', $g->value->name);
		$this->assertSame((string)$lineB, (string)$g->value->lines_id, 'lines_id update via project_lines_id alias');
		$this->assertSame(-1, $g->value->direction);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stop_patterns_id),
			'updated_at must advance',
		);
	}

	/**
	 * @covers ::deleteStopPattern
	 */
	public function testDeleteStopPattern()
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
