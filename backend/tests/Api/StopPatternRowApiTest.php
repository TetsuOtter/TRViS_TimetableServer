<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * StopPatternRow (StopPatternRowApi -> StopPatternRowsService).
 * Covers projects_id derived via subquery from stop_patterns, and
 * parentRepo=StopPatternsRepo privilege resolution (parentId = stopPatternId).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\ProjectStationsService;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\service\StopPatternRowsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\StopPatternRowApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'createStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'getStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'getStopPatternRowList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'updateStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'deleteStopPatternRow')]
class StopPatternRowApiTest extends IntegrationTestCase
{
	private function svc(): StopPatternRowsService
	{
		return new StopPatternRowsService($this->db, $this->logger);
	}

	private function newStopPattern(): UuidInterface
	{
		$lr = (new LineService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(Line::class, ['name' => 'L', 'description' => 'd'])],
		);
		$this->assertOk($lr, 'newLine');
		$lineId = $lr->value[0]->lines_id;
		$this->register('project_lines', 'project_lines_id', (string)$lineId);

		$sr = (new StopPatternsService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(StopPattern::class, ['name' => 'SP', 'lines_id' => $lineId, 'direction' => 1])],
		);
		$this->assertOk($sr, 'newStopPattern');
		$id = $sr->value[0]->stop_patterns_id;
		$this->register('stop_patterns', 'stop_patterns_id', (string)$id);
		return $id;
	}

	private function newProjectStation(): UuidInterface
	{
		$r = (new ProjectStationsService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(ProjectStation::class, ['name' => 'PS'])],
		);
		$this->assertOk($r, 'newProjectStation');
		$id = $r->value[0]->project_stations_id;
		$this->register('project_stations', 'project_stations_id', (string)$id);
		return $id;
	}

	private function createOne(UuidInterface $spId, UuidInterface $psId, array $over = []): StopPatternRow
	{
		$data = array_merge([
			'project_stations_id' => $psId,
			'sort_key' => 0,
			'is_pass' => true,
			'show_arrive' => false,
			'track_name' => '1',
		], $over);
		$r = $this->svc()->create($spId, $this->userId, [$this->makeModel(StopPatternRow::class, $data)]);
		$this->assertOk($r, 'createStopPatternRow');
		$o = $r->value[0];
		$this->register('stop_pattern_rows', 'stop_pattern_rows_id', (string)$o->stop_pattern_rows_id);
		return $o;
	}

	public function testCreateStopPatternRow()
	{
		$sp = $this->newStopPattern();
		$ps = $this->newProjectStation();
		$o = $this->createOne($sp, $ps);
		// projects_id derived via subquery from stop_patterns
		$this->assertSame((string)$this->projectId, (string)$o->projects_id, 'projects_id derived from stop_pattern');
		$this->assertSame((string)$sp, (string)$o->stop_patterns_id);
		$this->assertSame((string)$ps, (string)$o->project_stations_id);
		$this->assertTrue($o->is_pass);
		// explicit override of NOT-NULL-default(1) column
		$this->assertFalse($o->show_arrive);
		$this->assertSame('1', $o->track_name);
	}

	public function testGetStopPatternRow()
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stop_pattern_rows_id, (string)$g->value->stop_pattern_rows_id);
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stop_pattern_rows_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStopPatternRowList()
	{
		$sp = $this->newStopPattern();
		$ps = $this->newProjectStation();
		$a = $this->createOne($sp, $ps, ['sort_key' => 0]);
		$b = $this->createOne($sp, $ps, ['sort_key' => 1]);
		$list = $this->svc()->getPage($this->userId, $sp, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->stop_pattern_rows_id, $list->value);
		$this->assertContains((string)$a->stop_pattern_rows_id, $ids);
		$this->assertContains((string)$b->stop_pattern_rows_id, $ids);
	}

	public function testUpdateStopPatternRow()
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$before = $this->fetchUpdatedAt((string)$o->stop_pattern_rows_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stop_pattern_rows_id,
			$this->makeModel(StopPatternRow::class, ['sort_key' => 5, 'remarks' => 'note']),
			['sort_key' => 5, 'remarks' => 'note'],
		);
		$this->assertOk($u, 'updateStopPatternRow');
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertSame(5, $g->value->sort_key);
		$this->assertSame('note', $g->value->remarks);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stop_pattern_rows_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStopPatternRow()
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$d = $this->svc()->delete($this->userId, $o->stop_pattern_rows_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stop_pattern_rows WHERE stop_pattern_rows_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
