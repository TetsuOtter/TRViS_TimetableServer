<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * ProjectStation (ProjectStationApi -> ProjectStationsService).
 * Covers the lonlat UPDATE path (ST_PointFromText round-trip).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\model\ProjectStationLocationLonlat;
use dev_t0r\trvis_backend\service\ProjectStationsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\ProjectStationApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectStationApi::class, 'createProjectStation')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectStationApi::class, 'getProjectStation')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectStationApi::class, 'getProjectStationList')]
#[CoversMethod('\dev_t0r\trvis_backend\api\ProjectStationApi::class::updateProjectStation
Covers the lonlat UPDATE path (harness gap): _keyToUpdateQuerySetLine
+ _kvpToValueToBind overrides -> ST_PointFromText, re-read via ST_X/ST_Y.::class', 'updateProjectStation
Covers the lonlat UPDATE path (harness gap): _keyToUpdateQuerySetLine
+ _kvpToValueToBind overrides -> ST_PointFromText, re-read via ST_X/ST_Y.')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectStationApi::class, 'deleteProjectStation')]
class ProjectStationApiTest extends IntegrationTestCase
{
	private function svc(): ProjectStationsService
	{
		return new ProjectStationsService($this->db, $this->logger);
	}

	private function lonlat(float $lon, float $lat): ProjectStationLocationLonlat
	{
		return $this->makeModel(ProjectStationLocationLonlat::class, [
			'longitude' => $lon,
			'latitude' => $lat,
		]);
	}

	private function createOne(array $over = []): ProjectStation
	{
		$data = array_merge([
			'name' => 'Tokyo',
			'full_name' => 'Tokyo Station',
			'location_lonlat' => $this->lonlat(139.766944, 35.681111),
			'on_station_detect_radius_m' => 123.45,
			'always_show_hh' => true,
		], $over);
		$r = $this->svc()->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(ProjectStation::class, $data)],
		);
		$this->assertOk($r, 'createProjectStation');
		$o = $r->value[0];
		$this->register('project_stations', 'project_stations_id', (string)$o->project_stations_id);
		return $o;
	}

	public function testCreateProjectStation()
	{
		$o = $this->createOne();
		$this->assertSame((string)$this->projectId, (string)$o->projects_id);
		$this->assertSame('Tokyo', $o->name);
		$this->assertTrue($o->always_show_hh);
		$this->assertNotNull($o->location_lonlat);
		$this->assertEqualsWithDelta(139.766944, $o->location_lonlat->longitude, 1e-6);
		$this->assertEqualsWithDelta(35.681111, $o->location_lonlat->latitude, 1e-6);
	}

	public function testGetProjectStation()
	{
		$o = $this->createOne();
		$g = $this->svc()->getOne($this->userId, $o->project_stations_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->project_stations_id, (string)$g->value->project_stations_id);
		$nm = $this->svc()->getOne($this->nonMemberId, $o->project_stations_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetProjectStationList()
	{
		$a = $this->createOne(['name' => 'A']);
		$b = $this->createOne(['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $this->projectId, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->project_stations_id, $list->value);
		$this->assertContains((string)$a->project_stations_id, $ids);
		$this->assertContains((string)$b->project_stations_id, $ids);
	}

	public function testUpdateProjectStation()
	{
		$o = $this->createOne();
		$before = $this->fetchUpdatedAt((string)$o->project_stations_id);
		sleep(1);
		$newLL = $this->lonlat(135.501111, 34.702222); // Osaka-ish
		$u = $this->svc()->update(
			$this->userId,
			$o->project_stations_id,
			$this->makeModel(ProjectStation::class, ['name' => 'Renamed', 'location_lonlat' => $newLL]),
			['name' => 'Renamed', 'location_lonlat' => $newLL],
		);
		$this->assertOk($u, 'updateProjectStation');
		$g = $this->svc()->getOne($this->userId, $o->project_stations_id);
		$this->assertSame('Renamed', $g->value->name);
		$this->assertEqualsWithDelta(135.501111, $g->value->location_lonlat->longitude, 1e-6);
		$this->assertEqualsWithDelta(34.702222, $g->value->location_lonlat->latitude, 1e-6);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->project_stations_id),
			'updated_at must advance',
		);
	}

	public function testDeleteProjectStation()
	{
		$o = $this->createOne();
		$d = $this->svc()->delete($this->userId, $o->project_stations_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->project_stations_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM project_stations WHERE project_stations_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
