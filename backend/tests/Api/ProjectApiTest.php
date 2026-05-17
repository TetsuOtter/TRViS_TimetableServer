<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for the
 * Project entity / Phase 5 privilege-root (ProjectApi -> ProjectsService).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\ProjectApi
 */
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

	/**
	 * @covers ::createProject
	 */
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

	/**
	 * @covers ::getProject
	 */
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

	/**
	 * @covers ::getProjectList
	 */
	public function testGetProjectList()
	{
		$pid = $this->newProject('listed');
		$list = $this->svc()->selectProjectPage($this->userId, 1, 100, null);
		$this->assertOk($list, 'selectProjectPage');
		$ids = array_map(fn($x) => (string)$x->projects_id, $list->value);
		$this->assertContains((string)$pid, $ids);
	}

	/**
	 * @covers ::updateProject
	 */
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
	 * @covers ::deleteProject
	 */
	public function testDeleteProject()
	{
		$pid = $this->newProject();
		$d = $this->svc()->deleteProject($pid, $this->userId);
		$this->assertOk($d, 'deleteProject');
		$g = $this->svc()->selectProjectOne($pid, $this->userId);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * @covers ::getProjectPrivilege
	 */
	public function testGetProjectPrivilege()
	{
		$pid = $this->newProject();
		$g = $this->svc()->getPrivileges($pid, $this->userId, $this->userId);
		$this->assertOk($g, 'getPrivileges(self)');
		$this->assertSame(InviteKeyPrivilegeType::admin, $g->value->privilege_type);
	}

	/**
	 * @covers ::updateProjectPrivilege
	 */
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

	private function fetchUpdatedAt(UuidInterface $pid): string
	{
		$st = $this->db->prepare("SELECT updated_at FROM projects WHERE projects_id = :id");
		$st->execute([':id' => $pid->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
