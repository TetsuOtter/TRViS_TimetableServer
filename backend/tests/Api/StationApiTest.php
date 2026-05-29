<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the consolidated Station entity
 * (StationApi -> StationsService). Project-rooted (the former work-group
 * `stations` table was abolished and `project_stations` renamed to `stations`).
 * Drives the service directly; route registration / OA parsing / container
 * glue are covered separately (drift tests + byte-stable openapi.json regen +
 * route-wiring smoke).
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\StationApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationApi::class, 'createStation')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationApi::class, 'getStation')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationApi::class, 'getStationList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationApi::class, 'updateStation')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationApi::class, 'deleteStation')]
class StationApiTest extends IntegrationTestCase
{
	private function svc(): StationsService
	{
		return new StationsService($this->db, $this->logger);
	}

	private function lonlat(float $lon, float $lat): StationLocationLonlat
	{
		/** @var StationLocationLonlat $ll */
		$ll = $this->makeModel(StationLocationLonlat::class, [
			'longitude' => $lon,
			'latitude' => $lat,
		]);
		return $ll;
	}

	/** create one station and register it for teardown */
	private function createOne(
		string $name = 'Tokyo',
		?string $fullName = 'Tokyo Station',
		?StationLocationLonlat $lonlat = null,
		float $locationKm = 12.3,
		?float $radius = 123.45,
		StationRecordType $recordType = StationRecordType::normal,
		bool $alwaysShowHh = true,
	): Station {
		$lonlat ??= $this->lonlat(139.766944, 35.681111);
		$r = $this->svc()->createStation(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: $name,
			fullName: $fullName,
			locationKm: $locationKm,
			locationLonlat: $lonlat,
			onStationDetectRadiusM: $radius,
			recordType: $recordType,
			alwaysShowHh: $alwaysShowHh,
		);
		$this->assertOk($r, 'createStation');
		/** @var Station $o */
		$o = $r->value;
		$this->register('stations', 'stations_id', (string)$o->stations_id);
		return $o;
	}

	public function testCreateStation(): void
	{
		$o = $this->createOne(locationKm: 42.5, recordType: StationRecordType::info);
		$this->assertSame((string)$this->projectId, (string)$o->projects_id);
		$this->assertSame('Tokyo', $o->name);
		$this->assertTrue($o->always_show_hh);
		$this->assertEqualsWithDelta(42.5, $o->location_km, 1e-9);
		$this->assertSame(StationRecordType::info, $o->record_type);
		$this->assertNotNull($o->location_lonlat);
		$this->assertEqualsWithDelta(139.766944, $o->location_lonlat->longitude, 1e-6);
		$this->assertEqualsWithDelta(35.681111, $o->location_lonlat->latitude, 1e-6);
	}

	public function testCreateStationDefaultsLocationKmAndRecordType(): void
	{
		// createStation always receives locationKm/recordType from the API layer,
		// but the DB-level defaults (0 / normal) are what a minimal create yields.
		$o = $this->createOne(locationKm: 0.0, recordType: StationRecordType::normal);
		$this->assertEqualsWithDelta(0.0, $o->location_km, 1e-9);
		$this->assertSame(StationRecordType::normal, $o->record_type);
	}

	public function testGetStation(): void
	{
		$o = $this->createOne();
		$g = $this->svc()->selectStationOne(
			stationsId: $o->stations_id,
			userId: $this->userId,
		);
		$this->assertOk($g, 'selectStationOne');
		$this->assertSame((string)$o->stations_id, (string)$g->value->stations_id);

		// non-member -> 404
		$nm = $this->svc()->selectStationOne(
			stationsId: $o->stations_id,
			userId: $this->nonMemberId,
		);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStationList(): void
	{
		$a = $this->createOne('A');
		$b = $this->createOne('B');
		$list = $this->svc()->selectStationPage(
			projectsId: $this->projectId,
			userId: $this->userId,
			pageFrom1: 1,
			perPage: 50,
			topId: null,
		);
		$this->assertOk($list, 'selectStationPage');
		$ids = array_map(fn ($x) => (string)$x->stations_id, $list->value);
		$this->assertContains((string)$a->stations_id, $ids);
		$this->assertContains((string)$b->stations_id, $ids);
	}

	public function testUpdateStation(): void
	{
		$o = $this->createOne();
		$before = $this->fetchUpdatedAt($o->stations_id);
		sleep(1);
		$newLL = $this->lonlat(135.501111, 34.702222); // Osaka-ish
		$u = $this->svc()->updateStation(
			stationsId: $o->stations_id,
			userId: $this->userId,
			name: 'Renamed',
			fullName: null,
			locationKm: 99.9,
			hasLocationKm: true,
			locationLonlat: $newLL,
			hasLocationLonlat: true,
			onStationDetectRadiusM: null,
			hasOnStationDetectRadiusM: false,
			recordType: StationRecordType::info_ex,
			alwaysShowHh: null,
		);
		$this->assertOk($u, 'updateStation');
		$g = $this->svc()->selectStationOne(
			stationsId: $o->stations_id,
			userId: $this->userId,
		);
		$this->assertSame('Renamed', $g->value->name);
		$this->assertEqualsWithDelta(99.9, $g->value->location_km, 1e-9);
		$this->assertSame(StationRecordType::info_ex, $g->value->record_type);
		$this->assertEqualsWithDelta(135.501111, $g->value->location_lonlat->longitude, 1e-6);
		$this->assertEqualsWithDelta(34.702222, $g->value->location_lonlat->latitude, 1e-6);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt($o->stations_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStation(): void
	{
		$o = $this->createOne();
		$d = $this->svc()->deleteStation(
			stationsId: $o->stations_id,
			userId: $this->userId,
		);
		$this->assertOk($d, 'deleteStation');
		$g = $this->svc()->selectStationOne(
			stationsId: $o->stations_id,
			userId: $this->userId,
		);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * Existence-disclosure regression guard: a non-member must get 404 (not 403)
	 * when attempting to create under a project they cannot see.
	 */
	public function testNonMemberCannotWriteAndGets404(): void
	{
		$c = $this->svc()->createStation(
			projectsId: $this->projectId,
			userId: $this->nonMemberId,
			name: 'NoAccess',
			fullName: null,
			locationKm: 1.0,
			locationLonlat: null,
			onStationDetectRadiusM: null,
			recordType: StationRecordType::normal,
			alwaysShowHh: false,
		);
		$this->assertTrue($c->isError, 'non-member create must be rejected');
		$this->assertSame(404, $c->statusCode, 'non-member must get 404, not 403');
	}

	private function fetchUpdatedAt(UuidInterface $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stations WHERE stations_id = :id"
		);
		$st->execute([':id' => $id->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
