<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * Work (WorkApi -> WorksService). Work is keyed to a WorkGroup;
 * privilege resolves through the parent project's projects_privileges.
 * WorksService extends MyServiceBase, so getOne/update/delete go
 * through WorksRepo (MyRepoBase) selectPrivilegeType -> this regresses
 * the project-root privilege-resolution fix (admin -> 200, not 404).
 * Mirrors tests/Api/StationApiTest.php (single hop under WG).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\WorkApi
 */
class WorkApiTest extends IntegrationTestCase
{
	private function svc(): WorksService
	{
		return new WorksService($this->db, $this->logger);
	}

	private function newWorkGroup(): UuidInterface
	{
		$r = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'IT WG',
			'desc',
		);
		$this->assertOk($r, 'createWorkGroupInProject');
		$wgId = $r->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);
		return $wgId;
	}

	private function createOne(UuidInterface $wgId, array $over = []): Work
	{
		$data = array_merge([
			'name' => 'W',
			'description' => 'd',
		], $over);
		$r = $this->svc()->create($wgId, $this->userId, [$this->makeModel(Work::class, $data)]);
		$this->assertOk($r, 'createWork');
		$o = $r->value[0];
		$this->register('works', 'works_id', (string)$o->works_id);
		return $o;
	}

	/**
	 * @covers ::createWork
	 */
	public function testCreateWork()
	{
		$wg = $this->newWorkGroup();
		$o = $this->createOne($wg);
		$this->assertTrue(Uuid::isValid((string)$o->works_id));
		$this->assertSame((string)$wg, (string)$o->work_groups_id);
		$this->assertSame('W', $o->name);
	}

	/**
	 * @covers ::getWork
	 */
	public function testGetWork()
	{
		$o = $this->createOne($this->newWorkGroup());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->works_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->works_id, (string)$g->value->works_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->works_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	/**
	 * @covers ::getWorkList
	 */
	public function testGetWorkList()
	{
		$wg = $this->newWorkGroup();
		$a = $this->createOne($wg, ['name' => 'A']);
		$b = $this->createOne($wg, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $wg, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->works_id, $list->value);
		$this->assertContains((string)$a->works_id, $ids);
		$this->assertContains((string)$b->works_id, $ids);
	}

	/**
	 * @covers ::updateWork
	 */
	public function testUpdateWork()
	{
		$o = $this->createOne($this->newWorkGroup(), ['name' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->works_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->works_id,
			$this->makeModel(Work::class, ['name' => 'after']),
			['name' => 'after'],
		);
		$this->assertOk($u, 'updateWork');
		$g = $this->svc()->getOne($this->userId, $o->works_id);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->works_id),
			'updated_at must advance',
		);
	}

	/**
	 * @covers ::deleteWork
	 */
	public function testDeleteWork()
	{
		$o = $this->createOne($this->newWorkGroup());
		$d = $this->svc()->delete($this->userId, $o->works_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->works_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM works WHERE works_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
