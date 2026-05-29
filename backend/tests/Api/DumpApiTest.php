<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for DumpApi -> DumpService::dump. dump() is
 * read-gated via checkPrivilegeToRead (WorkGroupsPrivilegesRepo ->
 * project -> projects_privileges). The populated WG (Work -> Train +
 * Station -> TimetableRow) exercises the full works/trains/
 * timetable_rows dump chain; testDumpEmptyWorkGroup covers the
 * empty-WG path (previously 500 / SQLSTATE 42000 from
 * `WHERE works_id IN ()`, fixed by the empty-parentIdList guard in
 * Works/Trains/TimetableRowsRepo::dump).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\DumpService;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\DumpApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\DumpApi::class, 'dumpTimetable')]
class DumpApiTest extends IntegrationTestCase
{
	private ?UuidInterface $seededStationsId = null;

	private function svc(): DumpService
	{
		return new DumpService($this->db, $this->logger);
	}

	/** Build a project-rooted WG populated with Work -> Train + Station -> TimetableRow. */
	private function newPopulatedWorkGroup(): UuidInterface
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
		$stationsId = $s->value->stations_id;
		$this->register('stations', 'stations_id', (string)$stationsId);
		$this->seededStationsId = $stationsId;

		$tr = (new TimetableRowsService($this->db, $this->logger))->create(
			$trainsId,
			$this->userId,
			[$this->makeModel(TimetableRow::class, [
				'description' => 'd',
				'stations_id' => $stationsId,
				'is_operation_only_stop' => false,
				'is_pass' => false,
				'has_bracket' => false,
				'is_last_stop' => false,
			])],
		);
		$this->assertOk($tr, 'createTimetableRow');
		$this->register('timetable_rows', 'timetable_rows_id', (string)$tr->value[0]->timetable_rows_id);

		return $wgId;
	}

	public function testDumpTimetable()
	{
		$wgId = $this->newPopulatedWorkGroup();

		// admin (read privilege via project) -> OK, full dump chain
		$d = $this->svc()->dump($wgId, $this->userId);
		$this->assertOk($d, 'dump (admin)');

		// non-member -> 404 (rejected at checkPrivilegeToRead, must not disclose)
		$nm = $this->svc()->dump($wgId, $this->nonMemberId);
		$this->assertTrue($nm->isError, 'non-member dump must be rejected');
		$this->assertSame(404, $nm->statusCode);

		// unknown WG -> 404
		$unknown = $this->svc()->dump(Uuid::uuid7(), $this->userId);
		$this->assertTrue($unknown->isError, 'unknown WG must be 404');
		$this->assertSame(404, $unknown->statusCode);
	}

	/**
	 * Regression: an empty WorkGroup (no Work) must dump cleanly.
	 * Previously worksIdList=[] made trainsRepo->dump() emit
	 * `WHERE works_id IN ()` -> SQLSTATE 42000 / HTTP 500.
	 */
	public function testDumpEmptyWorkGroup()
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'Empty WG',
			'desc',
		);
		$this->assertOk($wg, 'createWorkGroupInProject');
		$wgId = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);

		$d = $this->svc()->dump($wgId, $this->userId);
		$this->assertOk($d, 'dump (empty WG)');
		$this->assertSame('Empty WG', $d->value->Name);
		$this->assertSame([], $d->value->Works, 'empty WG must dump zero Works');
	}

	/**
	 * A dumped TimetableRow whose Station was soft-deleted must vanish from the
	 * dump: the dump is the published timetable consumed by the TRViS app, and
	 * a stop at a no-longer-existing station is meaningless there. Stations is
	 * INNER-joined, so the `stations.deleted_at IS NULL` filter drops the whole
	 * row (the editor, by contrast, will keep showing it as a tombstone).
	 */
	public function testDumpHidesRowWhoseStationIsSoftDeleted()
	{
		$wgId = $this->newPopulatedWorkGroup();
		$stationsId = $this->seededStationsId;
		$this->assertNotNull($stationsId);

		// Assert on the serialized TRViS-JSON contract (what the client receives)
		// rather than the internal wrapper objects.
		$dumpRows = function (UuidInterface $wg): array {
			$d = $this->svc()->dump($wg, $this->userId);
			$this->assertOk($d, 'dump');
			$json = json_decode((string)json_encode($d->value), true);
			return $json['Works'][0]['Trains'][0]['TimetableRows'];
		};

		// before: the train carries its one timetable row
		$this->assertCount(1, $dumpRows($wgId), 'row present before station soft-delete');

		// soft-delete the station the row points at
		$del = (new StationsService($this->db, $this->logger))->deleteStation($stationsId, $this->userId);
		$this->assertOk($del, 'soft-delete station');

		// after: the row is gone (Train still dumps, just with zero rows)
		$this->assertCount(
			0,
			$dumpRows($wgId),
			'row referencing a soft-deleted station must be hidden from the dump',
		);
	}
}
