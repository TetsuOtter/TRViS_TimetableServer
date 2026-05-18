<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * StationTrack (StationTrackApi -> StationTracksService). StationTrack
 * is keyed to a Station (-> WG); privilege resolves through the
 * project's projects_privileges via the Station parent chain.
 * StationTracksService extends MyServiceBase, so getOne/update/delete
 * go through StationTracksRepo (MyRepoBase) selectPrivilegeType ->
 * this regresses the project-root privilege-resolution fix
 * (admin -> 200, not 404).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\StationTrackApi
 */
class StationTrackApiTest extends IntegrationTestCase
{
	private function svc(): StationTracksService
	{
		return new StationTracksService($this->db, $this->logger);
	}

	/** Create Project-rooted WG -> Station; return the Station id. */
	private function newStation(): UuidInterface
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'IT WG',
			'desc',
		);
		$this->assertOk($wg, 'createWorkGroupInProject');
		$wgId = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);

		$s = (new StationsService($this->db, $this->logger))->create(
			$wgId,
			$this->userId,
			[$this->makeModel(Station::class, [
				'name' => 'S',
				'description' => 'd',
				'location_km' => 12.3,
				'on_station_detect_radius_m' => 100.0,
				'record_type' => StationRecordType::normal,
			])],
		);
		$this->assertOk($s, 'createStation');
		$stationsId = $s->value[0]->stations_id;
		$this->register('stations', 'stations_id', (string)$stationsId);
		return $stationsId;
	}

	private function createOne(UuidInterface $stationsId, array $over = []): StationTrack
	{
		$data = array_merge([
			'name' => 'TR',
			'description' => 'd',
		], $over);
		$r = $this->svc()->create($stationsId, $this->userId, [$this->makeModel(StationTrack::class, $data)]);
		$this->assertOk($r, 'createStationTrack');
		$o = $r->value[0];
		$this->register('station_tracks', 'station_tracks_id', (string)$o->station_tracks_id);
		return $o;
	}

	/**
	 * @covers ::createStationTrack
	 */
	public function testCreateStationTrack()
	{
		$station = $this->newStation();
		$o = $this->createOne($station);
		$this->assertTrue(Uuid::isValid((string)$o->station_tracks_id));
		$this->assertSame((string)$station, (string)$o->stations_id);
		$this->assertSame('TR', $o->name);
	}

	/**
	 * @covers ::getStationTrack
	 */
	public function testGetStationTrack()
	{
		$o = $this->createOne($this->newStation());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->station_tracks_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->station_tracks_id, (string)$g->value->station_tracks_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->station_tracks_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	/**
	 * @covers ::getStationTrackList
	 */
	public function testGetStationTrackList()
	{
		$station = $this->newStation();
		$a = $this->createOne($station, ['name' => 'A']);
		$b = $this->createOne($station, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $station, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->station_tracks_id, $list->value);
		$this->assertContains((string)$a->station_tracks_id, $ids);
		$this->assertContains((string)$b->station_tracks_id, $ids);
	}

	/**
	 * @covers ::updateStationTrack
	 */
	public function testUpdateStationTrack()
	{
		$o = $this->createOne($this->newStation(), ['name' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->station_tracks_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->station_tracks_id,
			$this->makeModel(StationTrack::class, ['name' => 'after']),
			['name' => 'after'],
		);
		$this->assertOk($u, 'updateStationTrack');
		$g = $this->svc()->getOne($this->userId, $o->station_tracks_id);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->station_tracks_id),
			'updated_at must advance',
		);
	}

	/**
	 * @covers ::deleteStationTrack
	 */
	public function testDeleteStationTrack()
	{
		$o = $this->createOne($this->newStation());
		$d = $this->svc()->delete($this->userId, $o->station_tracks_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->station_tracks_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM station_tracks WHERE station_tracks_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
