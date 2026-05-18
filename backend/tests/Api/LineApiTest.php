<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: The OpenAPI-Generator stub for this file has been filled with
 * DB integration tests for the Line entity (Phase 7/8). The Line endpoint
 * is a thin delegate (LineApi -> MyApiHandler -> LineService -> LineRepo);
 * these tests drive the Service/Repo layer against the real test MySQL.
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

/**
 * @coversDefaultClass \dev_t0r\trvis_backend\api\LineApi
 */
class LineApiTest extends IntegrationTestCase
{
	private function svc(): LineService
	{
		return new LineService($this->db, $this->logger);
	}

	private function createOne(string $name = 'L', string $desc = 'd'): Line
	{
		$r = $this->svc()->create(
			$this->projectId,
			$this->userId,
			[$this->makeModel(Line::class, ['name' => $name, 'description' => $desc])],
		);
		$this->assertOk($r, 'createLine');
		$line = $r->value[0];
		$this->register('project_lines', 'project_lines_id', (string)$line->lines_id);
		return $line;
	}

	/**
	 * Grant a brand-new user exactly `read` privilege on the fixture project
	 * and return its uid. The projects_privileges row is keyed by projects_id,
	 * which IntegrationTestCase::setUp already registered for teardown.
	 */
	private function grantReadOnlyUser(): string
	{
		$uid = 'it-R-' . bin2hex(random_bytes(4));
		$r = (new ProjectsService($this->db, $this->logger))->updatePrivilege(
			$this->projectId,
			$this->userId,
			$uid,
			InviteKeyPrivilegeType::read,
		);
		$this->assertOk($r, 'grant read privilege');
		return $uid;
	}

	/**
	 * @covers ::createLine
	 */
	public function testCreateLine()
	{
		$line = $this->createOne('東海道本線', 'desc');
		$this->assertTrue(Uuid::isValid((string)$line->lines_id));
		$this->assertSame((string)$this->projectId, (string)$line->projects_id);
		$this->assertSame('東海道本線', $line->name);
		// row really lives in project_lines (reserved-word table), not "lines"
		$st = $this->db->prepare(
			"SELECT COUNT(*) FROM project_lines WHERE project_lines_id = :id AND deleted_at IS NULL"
		);
		$st->execute([':id' => $line->lines_id->getBytes()]);
		$this->assertSame(1, (int)$st->fetchColumn());
	}

	/**
	 * @covers ::getLine
	 */
	public function testGetLine()
	{
		$line = $this->createOne();
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertOk($g, 'getLine');
		$this->assertSame((string)$line->lines_id, (string)$g->value->lines_id);

		// project-rooted privilege override: non-member -> 404 (not `none`)
		$nm = $this->svc()->getOne($this->nonMemberId, $line->lines_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);

		// LineRepo::selectPrivilegeType resolves through to projects_privileges
		$priv = (new LineRepo($this->db, $this->logger))->selectPrivilegeType(
			id: $line->lines_id,
			userId: $this->userId,
			includeAnonymous: true,
		);
		$this->assertOk($priv, 'selectPrivilegeType');
		$this->assertSame(InviteKeyPrivilegeType::admin, $priv->value);
	}

	/**
	 * @covers ::getLineList
	 */
	public function testGetLineList()
	{
		$a = $this->createOne('A');
		$b = $this->createOne('B');
		$list = $this->svc()->getPage($this->userId, $this->projectId, 1, 50, null);
		$this->assertOk($list, 'getLineList');
		$ids = array_map(fn($x) => (string)$x->lines_id, $list->value);
		$this->assertContains((string)$a->lines_id, $ids);
		$this->assertContains((string)$b->lines_id, $ids);
		foreach ($list->value as $l) {
			$this->assertSame((string)$this->projectId, (string)$l->projects_id);
		}
	}

	/**
	 * @covers ::updateLine
	 */
	public function testUpdateLine()
	{
		$line = $this->createOne('before');
		$before = $this->fetchUpdatedAt((string)$line->lines_id);
		sleep(1); // datetime column has 1s resolution; ensure a tick passes
		$u = $this->svc()->update(
			$this->userId,
			$line->lines_id,
			$this->makeModel(Line::class, ['name' => 'after']),
			['name' => 'after'],
		);
		$this->assertOk($u, 'updateLine');
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertSame('after', $g->value->name);
		$after = $this->fetchUpdatedAt((string)$line->lines_id);
		$this->assertGreaterThan(
			$before,
			$after,
			'updated_at must advance on update (ON UPDATE CURRENT_TIMESTAMP)',
		);
	}

	/**
	 * @covers ::deleteLine
	 */
	public function testDeleteLine()
	{
		$line = $this->createOne();
		$d = $this->svc()->delete($this->userId, $line->lines_id);
		$this->assertOk($d, 'deleteLine');
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * Security regression: a principal holding only `read` privilege must NOT
	 * be able to create / update / delete. Before the fix,
	 * MyServiceBase::checkPrivilegeToWrite only logged a warning for the
	 * read-but-not-write case and fell through to success (write bypass).
	 * Driven via LineService, this covers every MyServiceBase subclass.
	 *
	 * @covers ::createLine
	 * @covers ::updateLine
	 * @covers ::deleteLine
	 */
	public function testReadOnlyUserCannotWrite()
	{
		$readUser = $this->grantReadOnlyUser();

		// create as read-only user -> 404 errContentNotFound (not success)
		$c = $this->svc()->create(
			$this->projectId,
			$readUser,
			[$this->makeModel(Line::class, ['name' => 'X', 'description' => 'd'])],
		);
		$this->assertTrue($c->isError, 'read-only create must be rejected');
		$this->assertSame(404, $c->statusCode);

		// admin-owned row that the read-only user will try to tamper with
		$line = $this->createOne('owned');

		$u = $this->svc()->update(
			$readUser,
			$line->lines_id,
			$this->makeModel(Line::class, ['name' => 'hacked']),
			['name' => 'hacked'],
		);
		$this->assertTrue($u->isError, 'read-only update must be rejected');
		$this->assertSame(404, $u->statusCode);

		$d = $this->svc()->delete($readUser, $line->lines_id);
		$this->assertTrue($d->isError, 'read-only delete must be rejected');
		$this->assertSame(404, $d->statusCode);

		// the row must remain intact and unmodified
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertOk($g, 'line survived the rejected writes');
		$this->assertSame('owned', $g->value->name);
	}

	/**
	 * Security regression: getOne / getPage now use checkPrivilegeToRead, so a
	 * read-only user (who must be denied writes) can still read. Guards against
	 * the getOne write->read change accidentally over-restricting reads.
	 *
	 * @covers ::getLine
	 * @covers ::getLineList
	 */
	public function testReadOnlyUserCanRead()
	{
		$readUser = $this->grantReadOnlyUser();
		$line = $this->createOne('readable');

		$g = $this->svc()->getOne($readUser, $line->lines_id);
		$this->assertOk($g, 'read-only user getOne must succeed');
		$this->assertSame((string)$line->lines_id, (string)$g->value->lines_id);

		$p = $this->svc()->getPage($readUser, $this->projectId, 1, 50, null);
		$this->assertOk($p, 'read-only user getPage must succeed');
		$ids = array_map(fn($x) => (string)$x->lines_id, $p->value);
		$this->assertContains((string)$line->lines_id, $ids);
	}

	private function fetchUpdatedAt(string $lineId): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM project_lines WHERE project_lines_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($lineId)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
