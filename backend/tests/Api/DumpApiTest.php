<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * DumpApi -> DumpService::dump. dump() is read-gated via
 * checkPrivilegeToRead (WorkGroupsPrivilegesRepo -> project ->
 * projects_privileges). The WG is populated (Work -> Train +
 * Station -> TimetableRow) so the admin happy path exercises the full
 * works/trains/timetable_rows dump chain.
 *
 * KNOWN PRE-EXISTING BUG (out of scope here, documented for follow-up):
 * dumping an EMPTY WorkGroup 500s with SQLSTATE 42000 -- worksIdList
 * is [] so trainsRepo->dump() builds `WHERE works_id IN ()`, which is
 * a MySQL syntax error. This test therefore populates the WG; the
 * empty-WG path is intentionally not asserted as success.
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

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

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\DumpApi
 */
class DumpApiTest extends IntegrationTestCase
{
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

	/**
	 * @covers ::dumpTimetable
	 */
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
}
