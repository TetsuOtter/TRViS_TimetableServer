<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * StationOnLine (StationOnLineApi -> StationsOnLineService).
 * Covers projects_id derived via subquery from project_lines, and
 * parentRepo=LineRepo privilege resolution (parentId = lineId).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\ProjectStationsService;
use dev_t0r\trvis_backend\service\StationsOnLineService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\StationOnLineApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'createStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'getStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'getStationOnLineList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'updateStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'deleteStationOnLine')]
class StationOnLineApiTest extends IntegrationTestCase
{
	private function svc(): StationsOnLineService
	{
		return new StationsOnLineService($this->db, $this->logger);
	}

	private function newLine(): UuidInterface
	{
		$r = (new LineService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(Line::class, ['name' => 'L', 'description' => 'd'])],
		);
		$this->assertOk($r, 'newLine');
		$id = $r->value[0]->lines_id;
		$this->register('project_lines', 'project_lines_id', (string)$id);
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

	private function createOne(UuidInterface $lineId, UuidInterface $psId, array $over = []): StationOnLine
	{
		$data = array_merge([
			'project_stations_id' => $psId,
			'location_m' => 12345.6,
			'track_hidden_by_default' => false,
		], $over);
		$r = $this->svc()->create($lineId, $this->userId, [$this->makeModel(StationOnLine::class, $data)]);
		$this->assertOk($r, 'createStationOnLine');
		$o = $r->value[0];
		$this->register('stations_on_line', 'stations_on_line_id', (string)$o->stations_on_line_id);
		return $o;
	}

	public function testCreateStationOnLine()
	{
		$line = $this->newLine();
		$ps = $this->newProjectStation();
		$o = $this->createOne($line, $ps);
		// projects_id is NOT a request field -> derived via subquery from project_lines
		$this->assertSame((string)$this->projectId, (string)$o->projects_id, 'projects_id derived from line');
		$this->assertSame((string)$line, (string)$o->lines_id);
		$this->assertSame((string)$ps, (string)$o->project_stations_id);
		$this->assertEqualsWithDelta(12345.6, $o->location_m, 1e-6);
	}

	public function testGetStationOnLine()
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stations_on_line_id, (string)$g->value->stations_on_line_id);
		// parentRepo=LineRepo path resolves to projects_privileges -> non-member 404
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stations_on_line_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStationOnLineList()
	{
		$line = $this->newLine();
		$ps = $this->newProjectStation();
		$a = $this->createOne($line, $ps);
		$b = $this->createOne($line, $ps, ['location_m' => 999.0]);
		$list = $this->svc()->getPage($this->userId, $line, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->stations_on_line_id, $list->value);
		$this->assertContains((string)$a->stations_on_line_id, $ids);
		$this->assertContains((string)$b->stations_on_line_id, $ids);
	}

	public function testUpdateStationOnLine()
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$before = $this->fetchUpdatedAt((string)$o->stations_on_line_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stations_on_line_id,
			$this->makeModel(StationOnLine::class, ['location_m' => 678.9]),
			['location_m' => 678.9],
		);
		$this->assertOk($u, 'updateStationOnLine');
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertEqualsWithDelta(678.9, $g->value->location_m, 1e-6);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stations_on_line_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStationOnLine()
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$d = $this->svc()->delete($this->userId, $o->stations_on_line_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stations_on_line WHERE stations_on_line_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
