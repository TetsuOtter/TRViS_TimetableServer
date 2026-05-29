<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the Project entity / privilege-root
 * (ProjectApi -> ProjectsService). Drives the service directly; route
 * registration / OA parsing / container glue are covered separately
 * (drift tests + byte-stable openapi.json regen + route-wiring smoke).
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php — proves every ProjectApi routes()
 *      entry is actually mounted (name/pattern/methods, no collision).
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\ProjectApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'createProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'getProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'getProjectList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'updateProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'deleteProject')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'getProjectPrivilege')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ProjectApi::class, 'updateProjectPrivilege')]
class ProjectApiTest extends IntegrationTestCase
{
	private function svc(): ProjectsService
	{
		return new ProjectsService($this->db, $this->logger);
	}

	/** create a project via the service and register it for teardown */
	private function newProject(string $name = 'P', string $desc = 'd'): UuidInterface
	{
		$r = $this->svc()->createProject($this->userId, $name, $desc);
		$this->assertOk($r, 'createProject');
		$pid = $r->value->projects_id;
		$this->register('projects_privileges', 'projects_id', (string)$pid);
		$this->register('projects', 'projects_id', (string)$pid);
		return $pid;
	}

	public function testCreateProject()
	{
		$r = $this->svc()->createProject($this->userId, 'My Project', 'desc');
		$this->assertOk($r, 'createProject');
		$p = $r->value;
		$this->register('projects_privileges', 'projects_id', (string)$p->projects_id);
		$this->register('projects', 'projects_id', (string)$p->projects_id);
		$this->assertTrue(Uuid::isValid((string)$p->projects_id));
		$this->assertSame('My Project', $p->name);
		// creator gets admin (Phase 5: projects_privileges row)
		$st = $this->db->prepare(
			"SELECT privilege_type FROM projects_privileges WHERE projects_id = :id AND uid = :u"
		);
		$st->execute([':id' => $p->projects_id->getBytes(), ':u' => $this->userId]);
		$this->assertSame(3, (int)$st->fetchColumn());
	}

	public function testGetProject()
	{
		$pid = $this->newProject();
		$g = $this->svc()->selectProjectOne($pid, $this->userId);
		$this->assertOk($g, 'selectProjectOne');
		$this->assertSame((string)$pid, (string)$g->value->projects_id);
		$this->assertSame(InviteKeyPrivilegeType::admin, $g->value->privilege_type);

		// non-member -> 404 (Phase 5 contract preserved)
		$nm = $this->svc()->selectProjectOne($pid, $this->nonMemberId);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetProjectList()
	{
		$pid = $this->newProject('listed');
		$list = $this->svc()->selectProjectPage($this->userId, 1, 100, null);
		$this->assertOk($list, 'selectProjectPage');
		$ids = array_map(fn($x) => (string)$x->projects_id, $list->value);
		$this->assertContains((string)$pid, $ids);
	}

	public function testUpdateProject()
	{
		$pid = $this->newProject('before');
		$before = $this->fetchUpdatedAt($pid);
		sleep(1);
		$u = $this->svc()->updateProject($pid, $this->userId, 'after', 'newdesc');
		$this->assertOk($u, 'updateProject');
		$g = $this->svc()->selectProjectOne($pid, $this->userId);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan($before, $this->fetchUpdatedAt($pid), 'updated_at must advance');
	}

	/**
	 * H7 regression: a no-op PATCH (identical values) on an existing, live
	 * row must NOT be a false 404.
	 *
	 * updateProject's SQL never writes updated_at in the SET clause, and
	 * MySQL's ON UPDATE CURRENT_TIMESTAMP does not fire when no column value
	 * changes — so a same-value UPDATE changes 0 rows. With the default
	 * connection, rowCount() reports *changed* rows (0) and the repo guard
	 * `if (rowCount() === 0) return errProjectNotFound()` returns a false
	 * 404. The H7 fix sets PDO::MYSQL_ATTR_FOUND_ROWS so rowCount() reports
	 * *matched* rows (1) for a live row, while a genuinely missing or
	 * soft-deleted row still matches 0 (WHERE ... deleted_at IS NULL).
	 *
	 * This test is load-bearing: with MYSQL_ATTR_FOUND_ROWS removed from the
	 * test connection it fails on the no-op-PATCH assertion (verified). One
	 * test suffices for all six update* repos — the fix is a single global
	 * PDO attribute, not per-repo code.
	 */
	public function testNoOpPatchOnLiveRowIsNot404()
	{
		$pid = $this->newProject('h7-name', 'h7-desc');

		// no-op PATCH: identical values -> 0 changed rows, 1 matched row.
		// Pre-fix this returned errProjectNotFound() (false 404).
		$noop = $this->svc()->updateProject($pid, $this->userId, 'h7-name', 'h7-desc');
		$this->assertOk($noop, 'no-op PATCH on a live row must not be a false 404');

		// the row is intact and still reachable
		$g = $this->svc()->selectProjectOne($pid, $this->userId);
		$this->assertOk($g, 'project still reachable after no-op PATCH');
		$this->assertSame('h7-name', $g->value->name);

		// other half of the H7 claim: genuine not-found must STILL be 404
		// even with FOUND_ROWS on (WHERE deleted_at IS NULL / no row).
		$missing = $this->svc()->updateProject(Uuid::uuid7(), $this->userId, 'x', 'y');
		$this->assertTrue($missing->isError, 'never-existed project must be rejected');
		$this->assertSame(404, $missing->statusCode);

		$this->assertOk($this->svc()->deleteProject($pid, $this->userId), 'soft-delete');
		$delHit = $this->svc()->updateProject($pid, $this->userId, 'h7-name', 'h7-desc');
		$this->assertTrue($delHit->isError, 'soft-deleted project must stay 404 under FOUND_ROWS');
		$this->assertSame(404, $delHit->statusCode);
	}

	public function testDeleteProject()
	{
		$pid = $this->newProject();
		$d = $this->svc()->deleteProject($pid, $this->userId);
		$this->assertOk($d, 'deleteProject');
		$g = $this->svc()->selectProjectOne($pid, $this->userId);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	public function testGetProjectPrivilege()
	{
		$pid = $this->newProject();
		$g = $this->svc()->getPrivileges($pid, $this->userId, $this->userId);
		$this->assertOk($g, 'getPrivileges(self)');
		$this->assertSame(InviteKeyPrivilegeType::admin, $g->value->privilege_type);
	}

	public function testGetProjectPrivilegeOfOthersHiddenFromNonAdminAs404()
	{
		$pid = $this->newProject();
		// grant a member only `read` on this project
		$readUser = 'it-R-' . bin2hex(random_bytes(4));
		$gr = $this->svc()->updatePrivilege($pid, $this->userId, $readUser, InviteKeyPrivilegeType::read);
		$this->assertOk($gr, 'grant read');

		// read-capable-but-not-admin member asking for ANOTHER user's privilege:
		// getPrivileges is a GET, so the privilege error hides existence (404),
		// exactly like a non-member would be answered.
		$ro = $this->svc()->getPrivileges($pid, $readUser, $this->userId);
		$this->assertTrue($ro->isError, 'read-not-admin getPrivileges(other) must be rejected');
		$this->assertSame(404, $ro->statusCode);

		// non-member is also 404 (parity)
		$nm = $this->svc()->getPrivileges($pid, $this->nonMemberId, $this->userId);
		$this->assertTrue($nm->isError, 'non-member getPrivileges(other) must be rejected');
		$this->assertSame(404, $nm->statusCode);
	}

	public function testUpdateProjectPrivilege()
	{
		$pid = $this->newProject();
		$up = $this->svc()->updatePrivilege(
			$pid,
			$this->userId,
			$this->nonMemberId,
			InviteKeyPrivilegeType::write,
		);
		$this->assertOk($up, 'updatePrivilege');
		$g = $this->svc()->getPrivileges($pid, $this->userId, $this->nonMemberId);
		$this->assertOk($g, 'getPrivileges(target)');
		$this->assertSame(InviteKeyPrivilegeType::write, $g->value->privilege_type);
	}

	/**
	 * L2 regression: a duplicate (uid, projects_id) privilege insert must
	 * return HTTP 409 Conflict, not a generic 500. The projects_privileges table
	 * has a UNIQUE KEY on (uid, projects_id), so a second insert for the same
	 * pair triggers MySQL error 1062 (ER_DUP_ENTRY). Pre-fix the repo silently
	 * ignored the execute() return value and the exception path returned 500.
	 */
	public function testDuplicatePrivilegeInsertReturns409()
	{
		$pid = $this->newProject();
		$repo = new ProjectsPrivilegesRepo($this->db, $this->logger);

		// First insert succeeds (the fixture project already has one for
		// $this->userId, so use nonMemberId who has no row yet).
		$first = $repo->insert(
			projectsId: $pid,
			privilegeType: InviteKeyPrivilegeType::read,
			userId: $this->nonMemberId,
		);
		$this->assertFalse($first->isError, 'first insert must succeed');

		// Second insert for the same (uid, projects_id) pair must be 409.
		$second = $repo->insert(
			projectsId: $pid,
			privilegeType: InviteKeyPrivilegeType::write,
			userId: $this->nonMemberId,
		);
		$this->assertTrue($second->isError, 'duplicate insert must be an error');
		$this->assertSame(409, $second->statusCode, 'duplicate insert must yield 409, not 500');
	}

	private function fetchUpdatedAt(UuidInterface $pid): string
	{
		$st = $this->db->prepare("SELECT updated_at FROM projects WHERE projects_id = :id");
		$st->execute([':id' => $pid->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
