<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for TimetableRow (TimetableRowApi ->
 * TimetableRowsService). TimetableRow is keyed to a Train (-> Work -> WG)
 * and additionally references a Station (stations_id is NOT NULL in
 * schema). Privilege resolves through the project's projects_privileges
 * via the Train parent chain. getOne/update/delete resolve through
 * TimetableRowsRepo::selectPrivilegeType (target); create/getPage through
 * TrainsRepo (parent) — admin -> 200, not 404.
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\model\ColorReal;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\ColorsService;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\StationTracksService;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\TimetableRowApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\TimetableRowApi::class, 'createTimetableRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TimetableRowApi::class, 'getTimetableRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TimetableRowApi::class, 'getTimetableRowList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TimetableRowApi::class, 'updateTimetableRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TimetableRowApi::class, 'deleteTimetableRow')]
class TimetableRowApiTest extends IntegrationTestCase
{
	private function svc(): TimetableRowsService
	{
		return new TimetableRowsService($this->db, $this->logger);
	}

	private UuidInterface $stationsId;

	/**
	 * Create Project-rooted WG -> Work -> Train (TimetableRow parent),
	 * plus a Station under the same WG (TimetableRow.stations_id is
	 * NOT NULL). The Station id is stashed for createOne().
	 */
	private function newTrain(): UuidInterface
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

		$w = (new WorksService($this->db, $this->logger))->create(
			$wgId,
			$this->userId,
			[$this->makeModel(Work::class, ['name' => 'W', 'description' => 'd'])],
		);
		$this->assertOk($w, 'createWork');
		$worksId = $w->value[0]->works_id;
		$this->register('works', 'works_id', (string)$worksId);

		$t = (new TrainsService($this->db, $this->logger))->create(
			$worksId,
			$this->userId,
			[$this->makeModel(Train::class, [
				'description' => 'd',
				'train_number' => 'T1',
				'direction' => 1,
				'day_count' => 0,
				'is_ride_on_moving' => false,
			])],
		);
		$this->assertOk($t, 'createTrain');
		$trainsId = $t->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$trainsId);

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
		$this->stationsId = $s->value->stations_id;
		$this->register('stations', 'stations_id', (string)$this->stationsId);

		return $trainsId;
	}

	private function createOne(UuidInterface $trainsId, array $over = []): TimetableRow
	{
		$data = array_merge([
			'description' => 'd',
			// repo binds $d->stations_id->getBytes(): pass the UuidInterface
			// (NOT NULL FK), not a string
			'stations_id' => $this->stationsId,
			// these 4 are optional (BOOLEAN NOT NULL DEFAULT FALSE); the repo
			// coalesces null -> false. Passed explicitly here for determinism;
			// testCreateTimetableRowDefaults covers the omitted path.
			'is_operation_only_stop' => false,
			'is_pass' => false,
			'has_bracket' => false,
			'is_last_stop' => false,
		], $over);
		$r = $this->svc()->create($trainsId, $this->userId, [$this->makeModel(TimetableRow::class, $data)]);
		$this->assertOk($r, 'createTimetableRow');
		$o = $r->value[0];
		$this->register('timetable_rows', 'timetable_rows_id', (string)$o->timetable_rows_id);
		return $o;
	}

	public function testCreateTimetableRow()
	{
		$train = $this->newTrain();
		$o = $this->createOne($train);
		$this->assertTrue(Uuid::isValid((string)$o->timetable_rows_id));
		$this->assertSame((string)$train, (string)$o->trains_id);
		$this->assertSame((string)$this->stationsId, (string)$o->stations_id);
	}

	/**
	 * Recommended-spec regression: the 4 boolean flags may be omitted by
	 * the client; the repo must coalesce null -> DB DEFAULT (false), not 500.
	 */
	public function testCreateTimetableRowDefaults()
	{
		$train = $this->newTrain();
		$data = [
			'description' => 'd',
			'stations_id' => $this->stationsId, // required FK
			// is_operation_only_stop / is_pass / has_bracket / is_last_stop omitted
		];
		$r = $this->svc()->create($train, $this->userId, [$this->makeModel(TimetableRow::class, $data)]);
		$this->assertOk($r, 'createTimetableRow (boolean flags omitted)');
		$rowId = $r->value[0]->timetable_rows_id;
		$this->register('timetable_rows', 'timetable_rows_id', (string)$rowId);

		$g = $this->svc()->getOne($this->userId, $rowId);
		$this->assertOk($g, 'getOne');
		// MySQL BOOLEAN is TINYINT(1): getOne maps it back as int 0/1.
		// assertSame(0, ...) (not (bool) cast) so a future null-mapping bug
		// is not masked by null->false coercion.
		$this->assertSame(0, (int)$g->value->is_operation_only_stop, 'omitted is_operation_only_stop -> DB DEFAULT false');
		$this->assertSame(0, (int)$g->value->is_pass, 'omitted is_pass -> DB DEFAULT false');
		$this->assertSame(0, (int)$g->value->has_bracket, 'omitted has_bracket -> DB DEFAULT false');
		$this->assertSame(0, (int)$g->value->is_last_stop, 'omitted is_last_stop -> DB DEFAULT false');
	}

	public function testGetTimetableRow()
	{
		$o = $this->createOne($this->newTrain());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->timetable_rows_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->timetable_rows_id, (string)$g->value->timetable_rows_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->timetable_rows_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetTimetableRowList()
	{
		$train = $this->newTrain();
		$a = $this->createOne($train, ['description' => 'A']);
		$b = $this->createOne($train, ['description' => 'B']);
		$list = $this->svc()->getPage($this->userId, $train, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn ($x) => (string)$x->timetable_rows_id, $list->value);
		$this->assertContains((string)$a->timetable_rows_id, $ids);
		$this->assertContains((string)$b->timetable_rows_id, $ids);
	}

	public function testUpdateTimetableRow()
	{
		$o = $this->createOne($this->newTrain(), ['description' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->timetable_rows_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->timetable_rows_id,
			$this->makeModel(TimetableRow::class, ['description' => 'after']),
			['description' => 'after'],
		);
		$this->assertOk($u, 'updateTimetableRow');
		$g = $this->svc()->getOne($this->userId, $o->timetable_rows_id);
		$this->assertSame('after', $g->value->description);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->timetable_rows_id),
			'updated_at must advance',
		);
	}

	public function testDeleteTimetableRow()
	{
		$o = $this->createOne($this->newTrain());
		$d = $this->svc()->delete($this->userId, $o->timetable_rows_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->timetable_rows_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * Tombstone read contract: a TimetableRow read embeds resolved display
	 * names + is_deleted flags for its station / track / color FK targets, and
	 * the resolving LEFT JOINs deliberately do NOT filter deleted_at — so when
	 * a referenced station/track/color is soft-deleted, the row STILL surfaces
	 * its name (with *_is_deleted = true) for the editor's "(削除済み)"
	 * tombstone. (The dump, by contrast, hides such rows — see DumpApiTest.)
	 */
	public function testTombstoneFieldsSurfaceSoftDeletedRefs()
	{
		$train = $this->newTrain();

		// a track under the row's station + a project color, both referenced.
		$tr = (new StationTracksService($this->db, $this->logger))->create(
			$this->stationsId,
			$this->userId,
			[$this->makeModel(StationTrack::class, ['name' => '1番線', 'description' => 'd'])],
		);
		$this->assertOk($tr, 'createStationTrack');
		$trackId = $tr->value[0]->station_tracks_id;
		$this->register('station_tracks', 'station_tracks_id', (string)$trackId);

		$c = (new ColorsService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(Color::class, [
				'name' => '赤',
				'description' => 'd',
				'color_8bit' => $this->makeModel(Color8bit::class, ['red' => 255, 'green' => 0, 'blue' => 0]),
				'color_real' => $this->makeModel(ColorReal::class, ['red' => 1.0, 'green' => 0.0, 'blue' => 0.0]),
			])],
		);
		$this->assertOk($c, 'createColor');
		$colorId = $c->value[0]->colors_id;
		$this->register('colors', 'colors_id', (string)$colorId);

		$row = $this->createOne($train, [
			'station_tracks_id' => $trackId,
			'colors_id_marker' => $colorId,
		]);

		// before any delete: names resolve, nothing flagged deleted.
		$g = $this->svc()->getOne($this->userId, $row->timetable_rows_id);
		$this->assertOk($g, 'getOne (live refs)');
		$this->assertSame('S', $g->value->stations_name);
		$this->assertFalse((bool)$g->value->stations_is_deleted);
		$this->assertSame('1番線', $g->value->station_tracks_name);
		$this->assertFalse((bool)$g->value->station_tracks_is_deleted);
		$this->assertSame('赤', $g->value->colors_name);
		$this->assertFalse((bool)$g->value->colors_is_deleted);

		// soft-delete all three refs.
		(new StationTracksService($this->db, $this->logger))->delete($this->userId, $trackId);
		(new ColorsService($this->db, $this->logger))->delete($this->userId, $colorId);
		(new StationsService($this->db, $this->logger))->deleteStation($this->stationsId, $this->userId);

		// after: names STILL resolve (tombstone), is_deleted flips true.
		$g2 = $this->svc()->getOne($this->userId, $row->timetable_rows_id);
		$this->assertOk($g2, 'getOne (tombstoned refs)');
		$this->assertSame('S', $g2->value->stations_name, 'deleted station name still surfaces');
		$this->assertTrue((bool)$g2->value->stations_is_deleted);
		$this->assertSame('1番線', $g2->value->station_tracks_name, 'deleted track name still surfaces');
		$this->assertTrue((bool)$g2->value->station_tracks_is_deleted);
		$this->assertSame('赤', $g2->value->colors_name, 'deleted color name still surfaces');
		$this->assertTrue((bool)$g2->value->colors_is_deleted);

		// The editor's grid loads rows via getPage (selectTimetableRowPage), a
		// DIFFERENT read path than getOne — it must embed the tombstone too.
		$page = $this->svc()->getPage($this->userId, $train, 1, 50, null);
		$this->assertOk($page, 'getPage (tombstoned refs)');
		$pageRow = null;
		foreach ($page->value as $m) {
			if ((string)$m->timetable_rows_id === (string)$row->timetable_rows_id) {
				$pageRow = $m;
				break;
			}
		}
		$this->assertNotNull($pageRow, 'created row present in page');
		$this->assertSame('S', $pageRow->stations_name, 'page: deleted station name still surfaces');
		$this->assertTrue((bool)$pageRow->stations_is_deleted);
		$this->assertSame('1番線', $pageRow->station_tracks_name, 'page: deleted track name still surfaces');
		$this->assertTrue((bool)$pageRow->station_tracks_is_deleted);
		$this->assertSame('赤', $pageRow->colors_name, 'page: deleted color name still surfaces');
		$this->assertTrue((bool)$pageRow->colors_is_deleted);
	}

	/**
	 * A row with no track / no color must report is_deleted = false (not true)
	 * for the absent FK — the LEFT JOIN yields NULL deleted_at, and the mapper
	 * coalesces a null FK id to "not deleted" rather than leaking 0/NULL noise.
	 */
	public function testTombstoneFlagsFalseWhenNoTrackOrColor()
	{
		$row = $this->createOne($this->newTrain());
		$g = $this->svc()->getOne($this->userId, $row->timetable_rows_id);
		$this->assertOk($g, 'getOne');
		$this->assertNull($g->value->station_tracks_name);
		$this->assertFalse((bool)$g->value->station_tracks_is_deleted);
		$this->assertNull($g->value->colors_name);
		$this->assertFalse((bool)$g->value->colors_is_deleted);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM timetable_rows WHERE timetable_rows_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
