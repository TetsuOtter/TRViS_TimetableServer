<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * Train (TrainApi -> TrainsService). Train is keyed to a Work (-> WG);
 * privilege resolves through the project's projects_privileges via the
 * Work parent chain. TrainsService extends MyServiceBase, so
 * getOne/update/delete go through TrainsRepo (MyRepoBase)
 * selectPrivilegeType -> this regresses the project-root
 * privilege-resolution fix (admin -> 200, not 404).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\TrainApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'createTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'getTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'getTrainList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'updateTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'deleteTrain')]
class TrainApiTest extends IntegrationTestCase
{
	private function svc(): TrainsService
	{
		return new TrainsService($this->db, $this->logger);
	}

	/** Create Project-rooted WG -> Work; return the Work id (Train parent). */
	private function newWork(): UuidInterface
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
		return $worksId;
	}

	private function createOne(UuidInterface $worksId, array $over = []): Train
	{
		$data = array_merge([
			'description' => 'd',
			'train_number' => 'T1',
			'direction' => 1,
			'day_count' => 0,
			// is_ride_on_moving is optional (BOOLEAN NOT NULL DEFAULT FALSE);
			// the repo coalesces null -> false. Passed explicitly here for
			// determinism; testCreateTrainDefaults covers the omitted path.
			'is_ride_on_moving' => false,
		], $over);
		$r = $this->svc()->create($worksId, $this->userId, [$this->makeModel(Train::class, $data)]);
		$this->assertOk($r, 'createTrain');
		$o = $r->value[0];
		$this->register('trains', 'trains_id', (string)$o->trains_id);
		return $o;
	}

	public function testCreateTrain()
	{
		$work = $this->newWork();
		$o = $this->createOne($work);
		$this->assertTrue(Uuid::isValid((string)$o->trains_id));
		$this->assertSame((string)$work, (string)$o->works_id);
		$this->assertSame('T1', $o->train_number);
	}

	/**
     * Recommended-spec regression: is_ride_on_moving may be omitted by the
     * client; the repo must coalesce null -> DB DEFAULT (false), not 500.
     */
    public function testCreateTrainDefaults()
	{
		$work = $this->newWork();
		$data = [
			'description' => 'd',
			'train_number' => 'T-default',
			'direction' => 1,
			'day_count' => 0,
			// is_ride_on_moving intentionally omitted
		];
		$r = $this->svc()->create($work, $this->userId, [$this->makeModel(Train::class, $data)]);
		$this->assertOk($r, 'createTrain (is_ride_on_moving omitted)');
		$trainsId = $r->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$trainsId);

		$g = $this->svc()->getOne($this->userId, $trainsId);
		$this->assertOk($g, 'getOne');
		// MySQL BOOLEAN is TINYINT(1): getOne maps it back as int 0/1.
		// assertSame(0, ...) (not (bool) cast) so a future null-mapping bug
		// is not masked by null->false coercion.
		$this->assertSame(0, (int)$g->value->is_ride_on_moving, 'omitted is_ride_on_moving must persist as DB DEFAULT false');
	}

	public function testGetTrain()
	{
		$o = $this->createOne($this->newWork());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->trains_id, (string)$g->value->trains_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->trains_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetTrainList()
	{
		$work = $this->newWork();
		$a = $this->createOne($work, ['train_number' => 'A']);
		$b = $this->createOne($work, ['train_number' => 'B']);
		$list = $this->svc()->getPage($this->userId, $work, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->trains_id, $list->value);
		$this->assertContains((string)$a->trains_id, $ids);
		$this->assertContains((string)$b->trains_id, $ids);
	}

	public function testUpdateTrain()
	{
		$o = $this->createOne($this->newWork(), ['train_number' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->trains_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->trains_id,
			$this->makeModel(Train::class, ['train_number' => 'after']),
			['train_number' => 'after'],
		);
		$this->assertOk($u, 'updateTrain');
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertSame('after', $g->value->train_number);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->trains_id),
			'updated_at must advance',
		);
	}

	public function testDeleteTrain()
	{
		$o = $this->createOne($this->newWork());
		$d = $this->svc()->delete($this->userId, $o->trains_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM trains WHERE trains_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
