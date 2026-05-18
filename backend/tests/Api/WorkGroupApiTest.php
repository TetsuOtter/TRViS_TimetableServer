<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * WorkGroup (WorkGroupApi -> WorkGroupsService). WorkGroupsService is
 * NOT a MyServiceBase subclass; privilege resolves via
 * WorkGroupsPrivilegesRepo (WG -> project -> projects_privileges, with
 * legacy work_groups_privileges fallback). Driven at the service layer
 * against the real test MySQL.
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\WorkGroupApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'createWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getWorkGroupList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'updateWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'deleteWorkGroup')]
class WorkGroupApiTest extends IntegrationTestCase
{
	private function svc(): WorkGroupsService
	{
		return new WorkGroupsService($this->db, $this->logger);
	}

	private function createOne(string $name = 'WG', string $desc = 'desc'): UuidInterface
	{
		$r = $this->svc()->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			$name,
			$desc,
		);
		$this->assertOk($r, 'createWorkGroupInProject');
		$wgId = $r->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);
		return $wgId;
	}

	public function testCreateWorkGroup()
	{
		$wgId = $this->createOne('WG-create');
		$this->assertTrue(Uuid::isValid((string)$wgId));
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertOk($g, 'selectWorkGroupOne');
		$this->assertSame('WG-create', $g->value->name);
	}

	public function testGetWorkGroup()
	{
		$wgId = $this->createOne();
		// admin -> 200
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertOk($g, 'selectWorkGroupOne (admin)');
		$this->assertSame((string)$wgId, (string)$g->value->work_groups_id);
		// non-member -> 404 (must not disclose)
		$nm = $this->svc()->selectWorkGroupOne($wgId, $this->nonMemberId);
		$this->assertTrue($nm->isError, 'non-member must be rejected');
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetWorkGroupList()
	{
		$a = $this->createOne('WG-A');
		$b = $this->createOne('WG-B');
		$list = $this->svc()->selectWorkGroupListByProject(
			$this->projectId,
			$this->userId,
			1,
			50,
			null,
		);
		$this->assertOk($list, 'selectWorkGroupListByProject');
		$ids = array_map(fn($x) => (string)$x->work_groups_id, $list->value);
		$this->assertContains((string)$a, $ids);
		$this->assertContains((string)$b, $ids);
	}

	public function testUpdateWorkGroup()
	{
		$wgId = $this->createOne('before');
		$before = $this->fetchUpdatedAt((string)$wgId);
		sleep(1);
		$u = $this->svc()->updateWorkGroup($wgId, $this->userId, 'after', null);
		$this->assertOk($u, 'updateWorkGroup');
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$wgId),
			'updated_at must advance',
		);
	}

	public function testDeleteWorkGroup()
	{
		$wgId = $this->createOne();
		$d = $this->svc()->deleteWorkGroup($wgId, $this->userId);
		$this->assertOk($d, 'deleteWorkGroup');
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertTrue($g->isError, 'deleted WG must not be retrievable');
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM work_groups WHERE work_groups_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
