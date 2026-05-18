<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * Station (StationApi -> StationsService). Station is keyed to a
 * WorkGroup; privilege resolves through the parent project's
 * projects_privileges (parentRepo=WorkGroupsPrivilegesRepo).
 * Mirrors tests/Api/StationOnLineApiTest.php (single hop under WG).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\StationApi
 */
class StationApiTest extends IntegrationTestCase
{
	private function svc(): StationsService
	{
		return new StationsService($this->db, $this->logger);
	}

	private function newWorkGroup(): UuidInterface
	{
		$r = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'IT WG',
			'desc',
		);
		$this->assertOk($r, 'createWorkGroupInProject');
		$wgId = $r->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);
		return $wgId;
	}

	private function createOne(UuidInterface $wgId, array $over = []): Station
	{
		$data = array_merge([
			'name' => 'S',
			'description' => 'd',
			'location_km' => 12.3,
			'on_station_detect_radius_m' => 100.0,
			'record_type' => StationRecordType::normal,
		], $over);
		$r = $this->svc()->create($wgId, $this->userId, [$this->makeModel(Station::class, $data)]);
		$this->assertOk($r, 'createStation');
		$o = $r->value[0];
		$this->register('stations', 'stations_id', (string)$o->stations_id);
		return $o;
	}

	/**
	 * @covers ::createStation
	 */
	public function testCreateStation()
	{
		$wg = $this->newWorkGroup();
		$o = $this->createOne($wg);
		$this->assertTrue(Uuid::isValid((string)$o->stations_id));
		$this->assertSame((string)$wg, (string)$o->work_groups_id);
		$this->assertSame('S', $o->name);
	}

	/**
	 * @covers ::getStation
	 */
	public function testGetStation()
	{
		$o = $this->createOne($this->newWorkGroup());
		$g = $this->svc()->getOne($this->userId, $o->stations_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stations_id, (string)$g->value->stations_id);
		// parentRepo=WorkGroupsPrivilegesRepo -> projects_privileges; non-member 404
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stations_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	/**
	 * @covers ::getStationList
	 */
	public function testGetStationList()
	{
		$wg = $this->newWorkGroup();
		$a = $this->createOne($wg, ['name' => 'A']);
		$b = $this->createOne($wg, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $wg, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->stations_id, $list->value);
		$this->assertContains((string)$a->stations_id, $ids);
		$this->assertContains((string)$b->stations_id, $ids);
	}

	/**
	 * @covers ::updateStation
	 */
	public function testUpdateStation()
	{
		$o = $this->createOne($this->newWorkGroup(), ['name' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->stations_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stations_id,
			$this->makeModel(Station::class, ['name' => 'after']),
			['name' => 'after'],
		);
		$this->assertOk($u, 'updateStation');
		$g = $this->svc()->getOne($this->userId, $o->stations_id);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stations_id),
			'updated_at must advance',
		);
	}

	/**
	 * @covers ::deleteStation
	 */
	public function testDeleteStation()
	{
		$o = $this->createOne($this->newWorkGroup());
		$d = $this->svc()->delete($this->userId, $o->stations_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stations_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stations WHERE stations_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
