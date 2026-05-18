<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * TimetableRow (TimetableRowApi -> TimetableRowsService). TimetableRow
 * is keyed to a Train (-> Work -> WG) and additionally references a
 * Station (stations_id is NOT NULL in schema). Privilege resolves
 * through the project's projects_privileges via the Train parent chain.
 * TimetableRowsService extends MyServiceBase, so getOne/update/delete
 * go through TimetableRowsRepo (MyRepoBase) selectPrivilegeType ->
 * this regresses the project-root privilege-resolution fix
 * (admin -> 200, not 404).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\TimetableRowApi
 */
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
		$this->stationsId = $s->value[0]->stations_id;
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

	/**
	 * @covers ::createTimetableRow
	 */
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
	 *
	 * @covers ::createTimetableRow
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
		$this->assertFalse((bool)$g->value->is_operation_only_stop, 'omitted is_operation_only_stop -> DB DEFAULT false');
		$this->assertFalse((bool)$g->value->is_pass, 'omitted is_pass -> DB DEFAULT false');
		$this->assertFalse((bool)$g->value->has_bracket, 'omitted has_bracket -> DB DEFAULT false');
		$this->assertFalse((bool)$g->value->is_last_stop, 'omitted is_last_stop -> DB DEFAULT false');
	}

	/**
	 * @covers ::getTimetableRow
	 */
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

	/**
	 * @covers ::getTimetableRowList
	 */
	public function testGetTimetableRowList()
	{
		$train = $this->newTrain();
		$a = $this->createOne($train, ['description' => 'A']);
		$b = $this->createOne($train, ['description' => 'B']);
		$list = $this->svc()->getPage($this->userId, $train, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->timetable_rows_id, $list->value);
		$this->assertContains((string)$a->timetable_rows_id, $ids);
		$this->assertContains((string)$b->timetable_rows_id, $ids);
	}

	/**
	 * @covers ::updateTimetableRow
	 */
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

	/**
	 * @covers ::deleteTimetableRow
	 */
	public function testDeleteTimetableRow()
	{
		$o = $this->createOne($this->newTrain());
		$d = $this->svc()->delete($this->userId, $o->timetable_rows_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->timetable_rows_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
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
