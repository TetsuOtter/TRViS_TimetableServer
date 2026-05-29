<?php

/**
 * Integration tests for OrphanCleanupService (M6 out-of-band orphan cleanup).
 *
 * Seeds three independent Work→Train→TimetableRow chains under the fixture
 * project/work_group, manually soft-deletes certain parents to create orphan
 * states, then exercises dry-run + real-run + idempotency of OrphanCleanupService.
 *
 * Chains:
 *   A (all live)       – Wa/Ta/Ra: must remain untouched (deleted_at IS NULL).
 *   B (dead work)      – Wb soft-deleted; Tb/Rb live (orphaned). After run: Tb+Rb deleted.
 *   C (live work, dead train, live row) – Wc live; Tc soft-deleted; Rc live (orphaned).
 *                                          After run: Rc deleted; Wc+Tc unchanged.
 *
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\maintenance;

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

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

class OrphanCleanupServiceTest extends IntegrationTestCase
{
	private function svc(): OrphanCleanupService
	{
		return new OrphanCleanupService($this->db, $this->logger);
	}

	/**
	 * Create a WG → Work → Train → TimetableRow chain.
	 * Returns ['work' => UuidInterface, 'train' => UuidInterface, 'row' => UuidInterface].
	 * Registers all created rows for FK-safe teardown (children first in cleanup order).
	 *
	 * @return array{work:UuidInterface,train:UuidInterface,row:UuidInterface,station:UuidInterface}
	 */
	private function createChain(string $suffix): array
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			"WG-$suffix",
			'd',
		);
		$this->assertOk($wg, "createWorkGroup-$suffix");
		$wgId = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);

		$w = (new WorksService($this->db, $this->logger))->create(
			$wgId,
			$this->userId,
			[$this->makeModel(Work::class, ['name' => "Work-$suffix", 'description' => 'd'])],
		);
		$this->assertOk($w, "createWork-$suffix");
		$worksId = $w->value[0]->works_id;
		$this->register('works', 'works_id', (string)$worksId);

		$t = (new TrainsService($this->db, $this->logger))->create(
			$worksId,
			$this->userId,
			[$this->makeModel(Train::class, [
				'description' => 'd',
				'train_number' => "T-$suffix",
				'direction' => 1,
				'day_count' => 0,
				'is_ride_on_moving' => false,
			])],
		);
		$this->assertOk($t, "createTrain-$suffix");
		$trainsId = $t->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$trainsId);

		$s = (new StationsService($this->db, $this->logger))->createStation(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: "St-$suffix",
			fullName: null,
			locationKm: 1.0,
			locationLonlat: null,
			onStationDetectRadiusM: 100.0,
			recordType: StationRecordType::normal,
			alwaysShowHh: false,
		);
		$this->assertOk($s, "createStation-$suffix");
		$stationsId = $s->value->stations_id;
		$this->register('stations', 'stations_id', (string)$stationsId);

		$r = (new TimetableRowsService($this->db, $this->logger))->create(
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
		$this->assertOk($r, "createTimetableRow-$suffix");
		$rowId = $r->value[0]->timetable_rows_id;
		$this->register('timetable_rows', 'timetable_rows_id', (string)$rowId);

		return [
			'work' => $worksId,
			'train' => $trainsId,
			'row' => $rowId,
			'station' => $stationsId,
		];
	}

	/** Soft-delete a row directly (simulates what the service does without cascading). */
	private function softDelete(string $table, string $idCol, UuidInterface $id): void
	{
		$st = $this->db->prepare(
			"UPDATE `$table` SET deleted_at = UTC_TIMESTAMP() WHERE `$idCol` = :id AND deleted_at IS NULL"
		);
		$st->execute([':id' => $id->getBytes()]);
	}

	/** Return whether deleted_at IS NULL for a given row. */
	private function isLive(string $table, string $idCol, UuidInterface $id): bool
	{
		$st = $this->db->prepare(
			"SELECT deleted_at IS NULL FROM `$table` WHERE `$idCol` = :id"
		);
		$st->execute([':id' => $id->getBytes()]);
		return (bool)$st->fetchColumn();
	}

	/**
	 * Seed all three chains and create orphan state.
	 *
	 * @return array{a:array,b:array,c:array}
	 */
	private function seedFixtures(): array
	{
		$a = $this->createChain('A');  // all live — control; must not be touched
		$b = $this->createChain('B');  // dead work, live descendants
		$c = $this->createChain('C');  // live work, dead train, live row

		// Chain B: soft-delete the work only (simulates WorksService::delete without cascade)
		$this->softDelete('works', 'works_id', $b['work']);

		// Chain C: soft-delete the train only (simulates TrainsService::delete without cascade)
		$this->softDelete('trains', 'trains_id', $c['train']);

		return ['a' => $a, 'b' => $b, 'c' => $c];
	}

	/**
	 * (1) Dry-run returns correct counts AND persists nothing.
	 */
	public function testDryRunReturnsCounts(): void
	{
		['a' => $a, 'b' => $b, 'c' => $c] = $this->seedFixtures();

		$result = $this->svc()->run(true);

		$this->assertOk($result, 'dry-run should succeed');
		$this->assertSame(1, $result->value['trains'], 'dry-run trains count');
		// timetable_rows: Rb (orphaned via dead Wb) + Rc (orphaned via dead Tc) = 2
		$this->assertSame(2, $result->value['timetable_rows'], 'dry-run timetable_rows count');

		// Nothing should have been persisted
		$this->assertTrue($this->isLive('trains', 'trains_id', $b['train']), 'Tb still live after dry-run');
		$this->assertTrue($this->isLive('timetable_rows', 'timetable_rows_id', $b['row']), 'Rb still live after dry-run');
		$this->assertTrue($this->isLive('timetable_rows', 'timetable_rows_id', $c['row']), 'Rc still live after dry-run');
	}

	/**
	 * (2) Real run returns correct counts and actually soft-deletes orphans.
	 */
	public function testRealRunDeletesOrphans(): void
	{
		['a' => $a, 'b' => $b, 'c' => $c] = $this->seedFixtures();

		$result = $this->svc()->run(false);

		$this->assertOk($result, 'real run should succeed');
		$this->assertSame(1, $result->value['trains'], 'real run trains count');
		$this->assertSame(2, $result->value['timetable_rows'], 'real run timetable_rows count');

		// Chain B: Tb (orphaned train) and Rb (orphaned row) must now be soft-deleted
		$this->assertFalse($this->isLive('trains', 'trains_id', $b['train']), 'Tb must be deleted');
		$this->assertFalse($this->isLive('timetable_rows', 'timetable_rows_id', $b['row']), 'Rb must be deleted');

		// Chain C: Rc (orphaned row) must now be soft-deleted; Wc must remain live
		$this->assertFalse($this->isLive('timetable_rows', 'timetable_rows_id', $c['row']), 'Rc must be deleted');
		$this->assertTrue($this->isLive('works', 'works_id', $c['work']), 'Wc must remain live');

		// Chain A: all rows must remain untouched
		$this->assertTrue($this->isLive('works', 'works_id', $a['work']), 'Wa must remain live');
		$this->assertTrue($this->isLive('trains', 'trains_id', $a['train']), 'Ta must remain live');
		$this->assertTrue($this->isLive('timetable_rows', 'timetable_rows_id', $a['row']), 'Ra must remain live');

		// Chain C: Tc was already soft-deleted before the run; deleted_at is still set
		$this->assertFalse($this->isLive('trains', 'trains_id', $c['train']), 'Tc must remain deleted');
	}

	/**
	 * (3) Idempotent: a second real run returns zero counts.
	 */
	public function testIdempotent(): void
	{
		$this->seedFixtures();

		// First run cleans up
		$first = $this->svc()->run(false);
		$this->assertOk($first, 'first run');

		// Second run should find nothing to do
		$second = $this->svc()->run(false);
		$this->assertOk($second, 'second run (idempotent)');
		$this->assertSame(0, $second->value['trains'], 'idempotent trains count');
		$this->assertSame(0, $second->value['timetable_rows'], 'idempotent timetable_rows count');
	}
}
