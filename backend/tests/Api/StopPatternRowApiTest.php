<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the StopPatternRow entity
 * (StopPatternRowApi -> StopPatternRowsService). Drives the service directly;
 * route registration / OA parsing / container glue are covered separately
 * (drift tests + byte-stable openapi.json regen + route-wiring smoke).
 *
 * Covers projects_id derived via subquery from stop_patterns, and
 * privilege resolution via stop_pattern_rows.projects_id ->
 * ProjectsPrivilegesRepo (parentId = stopPatternId, project-rooted).
 *
 * Line rows are inserted directly via PDO (LineService::create has a
 * different signature from the legacy model-list form used in the legacy RED;
 * direct PDO insert is the established pattern for this — see StopPatternApiTest).
 *
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\StopPatternRowsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\StopPatternRowApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'createStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'getStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'getStopPatternRowList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'updateStopPatternRow')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StopPatternRowApi::class, 'deleteStopPatternRow')]
class StopPatternRowApiTest extends IntegrationTestCase
{
	private function svc(): StopPatternRowsService
	{
		return new StopPatternRowsService($this->db, $this->logger);
	}

	/**
	 * Insert a project_lines row directly via PDO (LineService::create signature
	 * changed from the legacy model-list form; direct PDO insert is the
	 * established pattern — see StopPatternApiTest). Register for FK-safe teardown.
	 */
	private function newLine(string $name = 'L'): UuidInterface
	{
		$id = Uuid::uuid7();
		$this->db->prepare(
			"INSERT INTO project_lines (project_lines_id, projects_id, description, owner, name)"
			. " VALUES (:id, :pid, '', :owner, :name)"
		)->execute([
			':id' => $id->getBytes(),
			':pid' => $this->projectId->getBytes(),
			':owner' => $this->userId,
			':name' => $name,
		]);
		$this->register('project_lines', 'project_lines_id', (string)$id);
		return $id;
	}

	private function newStopPattern(): UuidInterface
	{
		$lineId = $this->newLine();
		$sr = (new StopPatternsService($this->db, $this->logger))->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(StopPattern::class, ['name' => 'SP', 'lines_id' => $lineId, 'direction' => 1])],
		);
		$this->assertOk($sr, 'newStopPattern');
		$id = $sr->value[0]->stop_patterns_id;
		$this->register('stop_patterns', 'stop_patterns_id', (string)$id);
		return $id;
	}

	private function newProjectStation(): UuidInterface
	{
		$r = (new StationsService($this->db, $this->logger))->createStation(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: 'PS',
			fullName: null,
			locationKm: 0.0,
			locationLonlat: null,
			onStationDetectRadiusM: null,
			recordType: StationRecordType::normal,
			alwaysShowHh: false,
		);
		$this->assertOk($r, 'newProjectStation');
		$id = $r->value->stations_id;
		$this->register('stations', 'stations_id', (string)$id);
		return $id;
	}

	private function createOne(UuidInterface $spId, UuidInterface $psId, array $over = []): StopPatternRow
	{
		$data = array_merge([
			'project_stations_id' => $psId,
			'sort_key' => 0,
			'is_pass' => true,
			'show_arrive' => false,
			'track_name' => '1',
		], $over);
		$r = $this->svc()->create($spId, $this->userId, [$this->makeModel(StopPatternRow::class, $data)]);
		$this->assertOk($r, 'createStopPatternRow');
		$o = $r->value[0];
		$this->register('stop_pattern_rows', 'stop_pattern_rows_id', (string)$o->stop_pattern_rows_id);
		return $o;
	}

	public function testCreateStopPatternRow(): void
	{
		$sp = $this->newStopPattern();
		$ps = $this->newProjectStation();
		$o = $this->createOne($sp, $ps);
		// projects_id derived via subquery from stop_patterns
		$this->assertSame((string)$this->projectId, (string)$o->projects_id, 'projects_id derived from stop_pattern');
		$this->assertSame((string)$sp, (string)$o->stop_patterns_id);
		$this->assertSame((string)$ps, (string)$o->project_stations_id);
		$this->assertTrue($o->is_pass);
		// explicit override of NOT-NULL-default(1) column
		$this->assertFalse($o->show_arrive);
		$this->assertSame('1', $o->track_name);
	}

	public function testGetStopPatternRow(): void
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stop_pattern_rows_id, (string)$g->value->stop_pattern_rows_id);
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stop_pattern_rows_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStopPatternRowList(): void
	{
		$sp = $this->newStopPattern();
		$ps = $this->newProjectStation();
		$a = $this->createOne($sp, $ps, ['sort_key' => 0]);
		$b = $this->createOne($sp, $ps, ['sort_key' => 1]);
		$list = $this->svc()->getPage($this->userId, $sp, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn ($x) => (string)$x->stop_pattern_rows_id, $list->value);
		$this->assertContains((string)$a->stop_pattern_rows_id, $ids);
		$this->assertContains((string)$b->stop_pattern_rows_id, $ids);
	}

	public function testUpdateStopPatternRow(): void
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$before = $this->fetchUpdatedAt((string)$o->stop_pattern_rows_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stop_pattern_rows_id,
			$this->makeModel(StopPatternRow::class, ['sort_key' => 5, 'remarks' => 'note']),
			['sort_key' => 5, 'remarks' => 'note'],
		);
		$this->assertOk($u, 'updateStopPatternRow');
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertSame(5, $g->value->sort_key);
		$this->assertSame('note', $g->value->remarks);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stop_pattern_rows_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStopPatternRow(): void
	{
		$o = $this->createOne($this->newStopPattern(), $this->newProjectStation());
		$d = $this->svc()->delete($this->userId, $o->stop_pattern_rows_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stop_pattern_rows_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stop_pattern_rows WHERE stop_pattern_rows_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
