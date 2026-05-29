<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the WorkGroup entity (WorkGroupApi ->
 * WorkGroupsService). WorkGroupsService is NOT a privilege-root itself;
 * privilege resolves via WorkGroupsPrivilegesRepo (WG -> project ->
 * projects_privileges, with legacy work_groups_privileges fallback).
 * Drives the service directly; route registration / OA parsing / container
 * glue are covered separately (drift tests + byte-stable openapi.json regen
 * + route-wiring smoke).
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php — proves every WorkGroupApi routes()
 *      entry is actually mounted (name/pattern/methods, no collision).
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\WorkGroupApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'createWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'createWorkGroupInProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getWorkGroupList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getWorkGroupListByProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'updateWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'deleteWorkGroup')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'getPrivilege')]
#[CoversMethod(\dev_t0r\trvis_backend\api\WorkGroupApi::class, 'updatePrivilege')]
class WorkGroupApiTest extends IntegrationTestCase
{
	private function svc(): WorkGroupsService
	{
		return new WorkGroupsService($this->db, $this->logger);
	}

	/**
	 * Create a WG under the IntegrationTestCase fixture project (where
	 * $this->userId is admin) and register it for FK-safe teardown.
	 */
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
		// createWorkGroup provisions its own implicit Project +
		// projects_privileges(admin) row.
		$r = $this->svc()->createWorkGroup($this->userId, 'WG-create', 'd');
		$this->assertOk($r, 'createWorkGroup');
		$wg = $r->value;
		$this->register('work_groups', 'work_groups_id', (string)$wg->work_groups_id);
		$this->register('projects_privileges', 'projects_id', (string)$wg->projects_id);
		$this->register('projects', 'projects_id', (string)$wg->projects_id);
		$this->assertTrue(Uuid::isValid((string)$wg->work_groups_id));
		$this->assertSame('WG-create', $wg->name);
		// creator gets admin via the implicit project's projects_privileges
		$st = $this->db->prepare(
			"SELECT privilege_type FROM projects_privileges "
			. "WHERE projects_id = :id AND uid = :u"
		);
		$st->execute([':id' => $wg->projects_id->getBytes(), ':u' => $this->userId]);
		$this->assertSame(3, (int)$st->fetchColumn());
	}

	public function testGetWorkGroup()
	{
		$wgId = $this->createOne();
		// admin -> 200
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertOk($g, 'selectWorkGroupOne (admin)');
		$this->assertSame((string)$wgId, (string)$g->value->work_groups_id);
		$this->assertSame(InviteKeyPrivilegeType::admin, $g->value->privilege_type);

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
		$before = $this->fetchUpdatedAt($wgId);
		sleep(1);
		$u = $this->svc()->updateWorkGroup($wgId, $this->userId, 'after', null);
		$this->assertOk($u, 'updateWorkGroup');
		$g = $this->svc()->selectWorkGroupOne($wgId, $this->userId);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt($wgId),
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

	public function testGetWorkGroupPrivilege()
	{
		$wgId = $this->createOne();
		$g = $this->svc()->getPrivileges($wgId, $this->userId, $this->userId);
		$this->assertOk($g, 'getPrivileges(self)');
		$this->assertSame(InviteKeyPrivilegeType::admin, $g->value->privilege_type);
		$this->assertSame((string)$wgId, (string)$g->value->work_groups_id);
	}

	public function testGetWorkGroupPrivilegeOfOthersHiddenFromNonAdminAs404()
	{
		$wgId = $this->createOne();
		// grant a member only `read` on this work group
		$readUser = 'it-R-' . bin2hex(random_bytes(4));
		$gr = $this->svc()->updatePrivilege($wgId, $this->userId, $readUser, InviteKeyPrivilegeType::read);
		$this->assertOk($gr, 'grant read');

		// read-capable-but-not-admin member asking for ANOTHER user's privilege:
		// getPrivileges is a GET, so the privilege error hides existence (404),
		// exactly like a non-member would be answered.
		$ro = $this->svc()->getPrivileges($wgId, $readUser, $this->userId);
		$this->assertTrue($ro->isError, 'read-not-admin getPrivileges(other) must be rejected');
		$this->assertSame(404, $ro->statusCode);

		// non-member is also 404 (parity)
		$nm = $this->svc()->getPrivileges($wgId, $this->nonMemberId, $this->userId);
		$this->assertTrue($nm->isError, 'non-member getPrivileges(other) must be rejected');
		$this->assertSame(404, $nm->statusCode);
	}

	public function testUpdateWorkGroupPrivilege()
	{
		$wgId = $this->createOne();
		$up = $this->svc()->updatePrivilege(
			$wgId,
			$this->userId,
			$this->nonMemberId,
			InviteKeyPrivilegeType::write,
		);
		$this->assertOk($up, 'updatePrivilege');
		$g = $this->svc()->getPrivileges($wgId, $this->userId, $this->nonMemberId);
		$this->assertOk($g, 'getPrivileges(target)');
		$this->assertSame(InviteKeyPrivilegeType::write, $g->value->privilege_type);
	}

	private function fetchUpdatedAt(UuidInterface $wgId): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM work_groups WHERE work_groups_id = :id"
		);
		$st->execute([':id' => $wgId->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
