<?php

/**
 * L4: dump() per-table cap tests (DB integration, no Firebase).
 *
 * Strategy: inject a tiny cap (= 1) via the optional constructor parameter
 * added to WorksRepo, TrainsRepo, and TimetableRowsRepo.  The fixture
 * creates exactly 2 rows for the target table (cap+1), which is the
 * minimum needed to trigger the overflow path without seeding 100 000
 * rows.  SQL `LIMIT (cap+1)` prevents the DB from materialising more, and
 * the post-fetch `$rowCount > $cap` guard returns HTTP 413.
 *
 * Three independent test methods — one per repo — so a TimetableRows
 * overflow cannot mask a Works/Trains pass-through (per-table independence
 * requirement from CODE_REVIEW.md L4).
 *
 * Note: $dst may be empty (the overflow check fires *before* the while
 * loop); 413 is the authoritative signal.
 *
 * @see tests/Integration/IntegrationTestCase.php
 */

declare(strict_types=1);

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\repo\TimetableRowsRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\TimetableRowsService;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\UuidInterface;

class DumpCapTest extends IntegrationTestCase
{
	/** Tiny cap used in every overflow test; must be >= 1. */
	private const TEST_CAP = 1;

	// ---------------------------------------------------------------
	// Fixture helpers
	// ---------------------------------------------------------------

	/** Create a WG with `$workCount` Works (no Trains/TimetableRows). */
	private function makeWgWithWorks(int $workCount): UuidInterface
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'Cap WG',
			'cap test',
		);
		$this->assertOk($wg, 'createWorkGroupInProject');
		$wgId = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);

		$models = [];
		for ($i = 0; $i < $workCount; $i++) {
			$models[] = $this->makeModel(Work::class, ['name' => "W$i", 'description' => 'd']);
		}
		$w = (new WorksService($this->db, $this->logger))->create(
			$wgId,
			$this->userId,
			$models,
		);
		$this->assertOk($w, 'createWorks');
		foreach ($w->value as $work) {
			$this->register('works', 'works_id', (string)$work->works_id);
		}
		return $wgId;
	}

	/** Create a WG with 1 Work containing `$trainCount` Trains. */
	private function makeWgWithTrains(int $trainCount): array
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'Cap Trains WG',
			'cap test',
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

		$models = [];
		for ($i = 0; $i < $trainCount; $i++) {
			$models[] = $this->makeModel(Train::class, [
				'description' => 'd',
				'train_number' => "T$i",
				'direction' => 1,
				'day_count' => 0,
				'is_ride_on_moving' => false,
			]);
		}
		$t = (new TrainsService($this->db, $this->logger))->create(
			$worksId,
			$this->userId,
			$models,
		);
		$this->assertOk($t, 'createTrains');
		foreach ($t->value as $train) {
			$this->register('trains', 'trains_id', (string)$train->trains_id);
		}
		return [$wgId, $worksId];
	}

	/**
	 * Create a WG with 1 Work, 1 Train, and `$rowCount` TimetableRows
	 * all referencing the same Station.
	 */
	private function makeWgWithTimetableRows(int $rowCount): array
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'Cap TR WG',
			'cap test',
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
				'train_number' => 'T0',
				'direction' => 1,
				'day_count' => 0,
				'is_ride_on_moving' => false,
			])],
		);
		$this->assertOk($t, 'createTrain');
		$trainsId = $t->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$trainsId);

		// Create stations for each timetable row (location_km must differ so
		// `ORDER BY Location_m` produces a deterministic order and each station
		// is distinct).
		$stationsService = new StationsService($this->db, $this->logger);
		$stationIds = [];
		for ($i = 0; $i < $rowCount; $i++) {
			$s = $stationsService->createStation(
				projectsId: $this->projectId,
				userId: $this->userId,
				name: "S$i",
				fullName: null,
				locationKm: (float)($i + 1),
				locationLonlat: null,
				onStationDetectRadiusM: 100.0,
				recordType: StationRecordType::normal,
				alwaysShowHh: false,
			);
			$this->assertOk($s, "createStation $i");
			$stationId = $s->value->stations_id;
			$this->register('stations', 'stations_id', (string)$stationId);
			$stationIds[] = $stationId;
		}

		$trModels = [];
		foreach ($stationIds as $stId) {
			$trModels[] = $this->makeModel(TimetableRow::class, [
				'description' => 'd',
				'stations_id' => $stId,
				'is_operation_only_stop' => false,
				'is_pass' => false,
				'has_bracket' => false,
				'is_last_stop' => false,
			]);
		}
		$tr = (new TimetableRowsService($this->db, $this->logger))->create(
			$trainsId,
			$this->userId,
			$trModels,
		);
		$this->assertOk($tr, 'createTimetableRows');
		foreach ($tr->value as $row) {
			$this->register('timetable_rows', 'timetable_rows_id', (string)$row->timetable_rows_id);
		}
		return [$wgId, $worksId, $trainsId];
	}

	// ---------------------------------------------------------------
	// Tests
	// ---------------------------------------------------------------

	/**
	 * WorksRepo::dump() with cap=1 and 2 Works seeded (cap+1) must return
	 * HTTP 413 — forcing the overflow path independently of Trains/TimetableRows.
	 */
	public function testWorksRepoCapExceededReturns413(): void
	{
		$wgId = $this->makeWgWithWorks(self::TEST_CAP + 1);

		$repo = new WorksRepo($this->db, $this->logger, self::TEST_CAP);
		$dst = [];
		$result = $repo->dump([$wgId], $dst);

		$this->assertTrue($result->isError, 'dump() must be an error when cap is exceeded');
		$this->assertSame(
			Constants::HTTP_PAYLOAD_TOO_LARGE,
			$result->statusCode,
			'HTTP status must be 413 PAYLOAD_TOO_LARGE',
		);
		$this->assertStringContainsString(
			'works',
			strtolower($result->errorMsg),
			'Error message must mention works',
		);
	}

	/**
	 * TrainsRepo::dump() with cap=1 and 2 Trains seeded must return HTTP 413.
	 */
	public function testTrainsRepoCapExceededReturns413(): void
	{
		[, $worksId] = $this->makeWgWithTrains(self::TEST_CAP + 1);

		$repo = new TrainsRepo($this->db, $this->logger, self::TEST_CAP);
		$dst = [];
		$worksIdList = [$worksId];
		$result = $repo->dump($worksIdList, $dst);

		$this->assertTrue($result->isError, 'dump() must be an error when cap is exceeded');
		$this->assertSame(
			Constants::HTTP_PAYLOAD_TOO_LARGE,
			$result->statusCode,
			'HTTP status must be 413 PAYLOAD_TOO_LARGE',
		);
		$this->assertStringContainsString(
			'trains',
			strtolower($result->errorMsg),
			'Error message must mention trains',
		);
	}

	/**
	 * TimetableRowsRepo::dump() with cap=1 and 2 TimetableRows seeded must
	 * return HTTP 413.
	 */
	public function testTimetableRowsRepoCapExceededReturns413(): void
	{
		[, , $trainsId] = $this->makeWgWithTimetableRows(self::TEST_CAP + 1);

		$repo = new TimetableRowsRepo($this->db, $this->logger, self::TEST_CAP);
		$dst = [];
		$trainsIdList = [$trainsId];
		$result = $repo->dump($trainsIdList, $dst);

		$this->assertTrue($result->isError, 'dump() must be an error when cap is exceeded');
		$this->assertSame(
			Constants::HTTP_PAYLOAD_TOO_LARGE,
			$result->statusCode,
			'HTTP status must be 413 PAYLOAD_TOO_LARGE',
		);
		$this->assertStringContainsString(
			'timetable',
			strtolower($result->errorMsg),
			'Error message must mention timetable',
		);
	}

	/**
	 * Smoke: with cap=2 and 1 Work seeded (below cap), dump() must succeed.
	 * Guards against off-by-one errors in the cap check.
	 */
	public function testWorksRepoBelowCapSucceeds(): void
	{
		$wgId = $this->makeWgWithWorks(self::TEST_CAP);

		$repo = new WorksRepo($this->db, $this->logger, self::TEST_CAP);
		$dst = [];
		$result = $repo->dump([$wgId], $dst);

		$this->assertFalse($result->isError, 'dump() must succeed when row count <= cap');
	}
}
