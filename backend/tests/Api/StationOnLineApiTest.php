<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: DB integration tests for StationOnLine
 * (StationOnLineApi -> StationsOnLineService -> StationsOnLineRepo).
 * Covers projects_id derived via subquery from project_lines, and
 * Line-rooted privilege resolution (create/getPage: LineRepo; getOne/update/delete:
 * StationsOnLineRepo -> LineRepo).
 * @see tests/Integration/IntegrationTestCase.php
 *
 * Translated from legacy StationOnLineApiTest (CONTRIBUTING-P3.md §5): same
 * scenarios and assertions, call sites rewritten against the new bespoke
 * service signatures. Legacy used generic MyServiceBase::create/selectList/etc.;
 * new backend has standalone StationsOnLineService with its own methods.
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\model\StationRecordType;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\StationsService;
use dev_t0r\trvis_backend\service\StationsOnLineService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(\dev_t0r\trvis_backend\api\StationOnLineApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'createStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'getStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'getStationOnLineList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'updateStationOnLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\StationOnLineApi::class, 'deleteStationOnLine')]
class StationOnLineApiTest extends IntegrationTestCase
{
	private function svc(): StationsOnLineService
	{
		return new StationsOnLineService($this->db, $this->logger);
	}

	private function newLine(): UuidInterface
	{
		$r = (new LineService($this->db, $this->logger))->create(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: 'L',
			description: 'd',
		);
		$this->assertOk($r, 'newLine');
		$line = $r->value;
		$this->register('project_lines', 'project_lines_id', (string)$line->lines_id);
		return $line->lines_id;
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
		$ps = $r->value;
		$this->register('stations', 'stations_id', (string)$ps->stations_id);
		return $ps->stations_id;
	}

	private function createOne(UuidInterface $lineId, UuidInterface $psId, array $over = []): StationOnLine
	{
		$data = array_merge([
			'project_stations_id' => $psId,
			'location_m' => 12345.6,
			'track_hidden_by_default' => false,
		], $over);
		$r = $this->svc()->create(
			$lineId,
			$this->userId,
			[$this->makeModel(StationOnLine::class, $data)],
		);
		$this->assertOk($r, 'createStationOnLine');
		$o = $r->value[0];
		$this->register('stations_on_line', 'stations_on_line_id', (string)$o->stations_on_line_id);
		return $o;
	}

	public function testCreateStationOnLine(): void
	{
		$line = $this->newLine();
		$ps = $this->newProjectStation();
		$o = $this->createOne($line, $ps);
		// projects_id is NOT a request field -> derived via subquery from project_lines
		$this->assertSame((string)$this->projectId, (string)$o->projects_id, 'projects_id derived from line');
		$this->assertSame((string)$line, (string)$o->lines_id);
		$this->assertSame((string)$ps, (string)$o->project_stations_id);
		$this->assertEqualsWithDelta(12345.6, $o->location_m, 1e-6);
	}

	public function testGetStationOnLine(): void
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->stations_on_line_id, (string)$g->value->stations_on_line_id);
		// parentRepo=LineRepo path resolves to projects_privileges -> non-member 404
		$nm = $this->svc()->getOne($this->nonMemberId, $o->stations_on_line_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetStationOnLineList(): void
	{
		$line = $this->newLine();
		$ps = $this->newProjectStation();
		$a = $this->createOne($line, $ps);
		$b = $this->createOne($line, $ps, ['location_m' => 999.0]);
		$list = $this->svc()->getPage($this->userId, $line, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn ($x) => (string)$x->stations_on_line_id, $list->value);
		$this->assertContains((string)$a->stations_on_line_id, $ids);
		$this->assertContains((string)$b->stations_on_line_id, $ids);
	}

	public function testUpdateStationOnLine(): void
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$before = $this->fetchUpdatedAt((string)$o->stations_on_line_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->stations_on_line_id,
			$this->makeModel(StationOnLine::class, ['location_m' => 678.9]),
			['location_m'],
		);
		$this->assertOk($u, 'updateStationOnLine');
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertEqualsWithDelta(678.9, $g->value->location_m, 1e-6);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->stations_on_line_id),
			'updated_at must advance',
		);
	}

	public function testDeleteStationOnLine(): void
	{
		$o = $this->createOne($this->newLine(), $this->newProjectStation());
		$d = $this->svc()->delete($this->userId, $o->stations_on_line_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * Tombstone read contract (line editor): a StationOnLine read embeds the
	 * referenced project_station's resolved name + is_deleted flag, and the
	 * resolving LEFT JOIN does NOT filter deleted_at — so when the referenced
	 * station is soft-deleted, the StationOnLine STILL surfaces its name (with
	 * project_stations_is_deleted = true) for the line editor's "(削除済み)"
	 * tombstone. Verified on both getOne and the paged list path.
	 */
	public function testReferencedStationTombstoneSurfacesOnReadAndList(): void
	{
		$line = $this->newLine();
		$ps = $this->newProjectStation(); // name 'PS'
		$o = $this->createOne($line, $ps);

		// before: name resolves, not flagged deleted.
		$g = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertOk($g, 'getOne (live ref)');
		$this->assertSame('PS', $g->value->project_stations_name);
		$this->assertFalse((bool)$g->value->project_stations_is_deleted);

		// soft-delete the referenced station.
		$del = (new StationsService($this->db, $this->logger))->deleteStation($ps, $this->userId);
		$this->assertOk($del, 'soft-delete station');

		// after (getOne): name still surfaces, is_deleted flips true.
		$g2 = $this->svc()->getOne($this->userId, $o->stations_on_line_id);
		$this->assertOk($g2, 'getOne (tombstoned ref)');
		$this->assertSame('PS', $g2->value->project_stations_name, 'deleted station name still surfaces');
		$this->assertTrue((bool)$g2->value->project_stations_is_deleted);

		// after (paged list): same tombstone surfaces.
		$list = $this->svc()->getPage($this->userId, $line, 1, 50, null);
		$this->assertOk($list, 'getPage (tombstoned ref)');
		$match = null;
		foreach ($list->value as $x) {
			if ((string)$x->stations_on_line_id === (string)$o->stations_on_line_id) {
				$match = $x;
				break;
			}
		}
		$this->assertNotNull($match, 'station_on_line still listed after its station is soft-deleted');
		$this->assertSame('PS', $match->project_stations_name);
		$this->assertTrue((bool)$match->project_stations_is_deleted);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM stations_on_line WHERE stations_on_line_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
