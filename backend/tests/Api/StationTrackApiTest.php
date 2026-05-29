<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the StationTrack entity
 * (StationTrackApi -> StationTracksService). StationTrack is keyed to a Station
 * (-> WG); privilege resolves through the station's owning work_group's
 * project's projects_privileges (parentRepo=StationsRepo).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\StationTrackApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationTrackApi::class, 'createStationTrack')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationTrackApi::class, 'getStationTrack')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationTrackApi::class, 'getStationTrackList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationTrackApi::class, 'updateStationTrack')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationTrackApi::class, 'deleteStationTrack')]
class StationTrackApiTest extends IntegrationTestCase
{
	private function svc(): StationTracksService
	{
		return new StationTracksService($this->db, $this->logger);
	}

	/** Create a Project-rooted Station; return the Station id. */
	private function newStation(): UuidInterface
	{
		$s = (new StationsService($this->db, $this->logger))->createStation(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: 'S',
			fullName: null,
			locationKm: 12.3,
			locationLonlat: null,
			onStationDetectRadiusM: 100.0,
			recordType: StationRecordType::normal,
			alwaysShowHh: false,
		);
		$this->assertOk($s, 'createStation');
		$stationsId = $s->value->stations_id;
		$this->register('stations', 'stations_id', (string)$stationsId);
		return $stationsId;
	}

	private function createOne(UuidInterface $stationsId, array $over = []): StationTrack
	{
		$data = array_merge([
			'name' => 'TR',
			'description' => 'd',
		], $over);
		/** @var StationTrack $model */
		$model = $this->makeModel(StationTrack::class, $data);
		$r = $this->svc()->create($stationsId, $this->userId, [$model]);
		$this->assertOk($r, 'createStationTrack');
		$o = $r->value[0];
		$this->register('station_tracks', 'station_tracks_id', (string)$o->station_tracks_id);
		return $o;
	}

	public function testCreateStationTrack(): void
	{
		$station = $this->newStation();
		$o = $this->createOne($station);
		$this->assertTrue(Uuid::isValid((string)$o->station_tracks_id));
		$this->assertSame((string)$station, (string)$o->stations_id);
		$this->assertSame('TR', $o->name);
	}

	public function testGetStationTrack(): void
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

	public function testGetStationTrackList(): void
	{
		$station = $this->newStation();
		$a = $this->createOne($station, ['name' => 'A']);
		$b = $this->createOne($station, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $station, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn ($x) => (string)$x->station_tracks_id, $list->value);
		$this->assertContains((string)$a->station_tracks_id, $ids);
		$this->assertContains((string)$b->station_tracks_id, $ids);
	}

	public function testUpdateStationTrack(): void
	{
		$o = $this->createOne($this->newStation(), ['name' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->station_tracks_id);
		sleep(1);
		/** @var StationTrack $updateModel */
		$updateModel = $this->makeModel(StationTrack::class, ['name' => 'after']);
		$u = $this->svc()->update(
			$this->userId,
			$o->station_tracks_id,
			$updateModel,
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

	public function testDeleteStationTrack(): void
	{
		$o = $this->createOne($this->newStation());
		$d = $this->svc()->delete($this->userId, $o->station_tracks_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->station_tracks_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * End-to-end invariant: a StationTrack whose owning (Project-rooted) Station
	 * is soft-deleted is a non-cascading orphan (OrphanCleanupService never
	 * sweeps station_tracks) and MUST NOT be visible.
	 *
	 * Where it is enforced: the StationTracksService privilege pre-check —
	 * selectStationsIdByStationTracksId -> StationsRepo::selectPrivilegeType,
	 * which resolves `SELECT projects_id FROM stations WHERE ... deleted_at IS
	 * NULL` and returns errStationNotFound when the parent station is gone — is
	 * the load-bearing guard here, so this test stays green whether or not the
	 * StationTracksRepo read JOINs filter deleted_at. This test guards the
	 * INVARIANT: it would fail if a future change dropped the service pre-check.
	 */
	public function testTrackUnderSoftDeletedStationIsHidden(): void
	{
		$stationsId = $this->newStation();

		$o = $this->createOne($stationsId);
		$this->assertOk($this->svc()->getOne($this->userId, $o->station_tracks_id), 'pre-delete getOne');

		// Soft-delete the parent Station only (track stays live).
		$st = $this->db->prepare(
			"UPDATE stations SET deleted_at = UTC_TIMESTAMP() WHERE stations_id = :id"
		);
		$st->execute([':id' => $stationsId->getBytes()]);

		$g = $this->svc()->getOne($this->userId, $o->station_tracks_id);
		$this->assertTrue($g->isError, 'orphan getOne should error');
		$this->assertSame(404, $g->statusCode);

		$list = $this->svc()->getPage($this->userId, $stationsId, 1, 50, null);
		if (!$list->isError) {
			$ids = array_map(fn ($x) => (string)$x->station_tracks_id, $list->value);
			$this->assertNotContains((string)$o->station_tracks_id, $ids, 'orphan must not appear in list');
		}
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
