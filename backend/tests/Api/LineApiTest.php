<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the Line entity (Phase 3.4 port). Drives the
 * Service/Repo layer against the real test MySQL; same behavioural contract
 * as backend_legacy/tests/Api/LineApiTest.php (the RED contract), rewritten
 * against the new bespoke LineService signatures.
 * @see tests/Integration/IntegrationTestCase.php
 * @see tests/Unit/RouteSmokeTest.php — proves every LineApi routes() entry
 *      is actually mounted (name/pattern/methods, no collision).
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\LineApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\LineApi::class, 'createLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\LineApi::class, 'getLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\LineApi::class, 'getLineList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\LineApi::class, 'updateLine')]
#[CoversMethod(\dev_t0r\trvis_backend\api\LineApi::class, 'deleteLine')]
class LineApiTest extends IntegrationTestCase
{
	private function svc(): LineService
	{
		return new LineService($this->db, $this->logger);
	}

	private function createOne(string $name = 'L', string $desc = 'd'): Line
	{
		$r = $this->svc()->create(
			projectsId: $this->projectId,
			userId: $this->userId,
			name: $name,
			description: $desc,
		);
		$this->assertOk($r, 'createLine');
		$line = $r->value;
		$this->register('project_lines', 'project_lines_id', (string)$line->lines_id);
		return $line;
	}

	/**
	 * Grant a brand-new user exactly `read` privilege on the fixture project
	 * and return its uid.
	 */
	private function grantReadOnlyUser(): string
	{
		$uid = 'it-R-' . bin2hex(random_bytes(4));
		$r = (new ProjectsService($this->db, $this->logger))->updatePrivilege(
			projectsId: $this->projectId,
			senderUserId: $this->userId,
			targetUserId: $uid,
			newPrivilegeType: InviteKeyPrivilegeType::read,
		);
		$this->assertOk($r, 'grant read privilege');
		return $uid;
	}

	public function testCreateLine(): void
	{
		$line = $this->createOne('東海道本線', 'desc');
		$this->assertTrue(Uuid::isValid((string)$line->lines_id));
		$this->assertSame((string)$this->projectId, (string)$line->projects_id);
		$this->assertSame('東海道本線', $line->name);
		// row lives in project_lines (reserved-word table)
		$st = $this->db->prepare(
			"SELECT COUNT(*) FROM project_lines WHERE project_lines_id = :id AND deleted_at IS NULL"
		);
		$st->execute([':id' => $line->lines_id->getBytes()]);
		$this->assertSame(1, (int)$st->fetchColumn());
	}

	public function testGetLine(): void
	{
		$line = $this->createOne();
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertOk($g, 'getLine');
		$this->assertSame((string)$line->lines_id, (string)$g->value->lines_id);

		// project-rooted privilege: non-member -> 404
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

	public function testGetLineList(): void
	{
		$a = $this->createOne('A');
		$b = $this->createOne('B');
		$list = $this->svc()->getPage($this->userId, $this->projectId, 1, 50, null);
		$this->assertOk($list, 'getLineList');
		$ids = array_map(fn ($x) => (string)$x->lines_id, $list->value);
		$this->assertContains((string)$a->lines_id, $ids);
		$this->assertContains((string)$b->lines_id, $ids);
		foreach ($list->value as $l) {
			$this->assertSame((string)$this->projectId, (string)$l->projects_id);
		}
	}

	public function testUpdateLine(): void
	{
		$line = $this->createOne('before');
		$before = $this->fetchUpdatedAt($line->lines_id);
		sleep(1); // datetime column has 1s resolution; ensure a tick passes
		$patch = (object)['name' => 'after'];
		$u = $this->svc()->update(
			userId: $this->userId,
			lineId: $line->lines_id,
			patch: $patch,
			keys: ['name'],
		);
		$this->assertOk($u, 'updateLine');
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertSame('after', $g->value->name);
		$after = $this->fetchUpdatedAt($line->lines_id);
		$this->assertGreaterThan(
			$before,
			$after,
			'updated_at must advance on update (ON UPDATE CURRENT_TIMESTAMP)',
		);
	}

	public function testDeleteLine(): void
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
	 * be able to create / update / delete. Per the unified privilege-error rule
	 * a read-capable member who lacks the write tier gets 403 on mutations (the
	 * member CAN see the resource, so 404 would be a lie); non-members and GETs
	 * stay 404 to hide existence.
	 */
	public function testReadOnlyUserCannotWrite(): void
	{
		$readUser = $this->grantReadOnlyUser();

		// create as read-only user -> 403 (read-capable member, lacks write)
		$c = $this->svc()->create(
			projectsId: $this->projectId,
			userId: $readUser,
			name: 'X',
			description: 'd',
		);
		$this->assertTrue($c->isError, 'read-only create must be rejected');
		$this->assertSame(403, $c->statusCode);

		// admin-owned row that the read-only user will try to tamper with
		$line = $this->createOne('owned');

		$u = $this->svc()->update(
			userId: $readUser,
			lineId: $line->lines_id,
			patch: (object)['name' => 'hacked'],
			keys: ['name'],
		);
		$this->assertTrue($u->isError, 'read-only update must be rejected');
		$this->assertSame(403, $u->statusCode);

		$d = $this->svc()->delete($readUser, $line->lines_id);
		$this->assertTrue($d->isError, 'read-only delete must be rejected');
		$this->assertSame(403, $d->statusCode);

		// the row must remain intact and unmodified
		$g = $this->svc()->getOne($this->userId, $line->lines_id);
		$this->assertOk($g, 'line survived the rejected writes');
		$this->assertSame('owned', $g->value->name);
	}

	/**
	 * Existence-disclosure regression: a NON-member (no privilege row at all)
	 * must get 404 on every mutation, never 403. The unified rule reserves 403
	 * for read-capable members; a non-member must not be able to tell an
	 * existing resource apart from a missing one. LineService relies solely on
	 * selectPrivilegeType()'s isError(404) for this (it has no explicit read
	 * gate), so this guards that the no-row path really returns 404 and never
	 * falls through to the now-403 write gate.
	 */
	public function testNonMemberCannotWriteAndGets404(): void
	{
		// create under a project the non-member has no privilege on
		$c = $this->svc()->create(
			projectsId: $this->projectId,
			userId: $this->nonMemberId,
			name: 'X',
			description: 'd',
		);
		$this->assertTrue($c->isError, 'non-member create must be rejected');
		$this->assertSame(404, $c->statusCode);

		$line = $this->createOne('owned');

		$u = $this->svc()->update(
			userId: $this->nonMemberId,
			lineId: $line->lines_id,
			patch: (object)['name' => 'hacked'],
			keys: ['name'],
		);
		$this->assertTrue($u->isError, 'non-member update must be rejected');
		$this->assertSame(404, $u->statusCode);

		$d = $this->svc()->delete($this->nonMemberId, $line->lines_id);
		$this->assertTrue($d->isError, 'non-member delete must be rejected');
		$this->assertSame(404, $d->statusCode);
	}

	/**
	 * Security regression: getOne / getPage use privilege checks, so a
	 * read-only user (denied writes) can still read.
	 */
	public function testReadOnlyUserCanRead(): void
	{
		$readUser = $this->grantReadOnlyUser();
		$line = $this->createOne('readable');

		$g = $this->svc()->getOne($readUser, $line->lines_id);
		$this->assertOk($g, 'read-only user getOne must succeed');
		$this->assertSame((string)$line->lines_id, (string)$g->value->lines_id);

		$p = $this->svc()->getPage($readUser, $this->projectId, 1, 50, null);
		$this->assertOk($p, 'read-only user getPage must succeed');
		$ids = array_map(fn ($x) => (string)$x->lines_id, $p->value);
		$this->assertContains((string)$line->lines_id, $ids);
	}

	private function fetchUpdatedAt(UuidInterface $lineId): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM project_lines WHERE project_lines_id = :id"
		);
		$st->execute([':id' => $lineId->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
