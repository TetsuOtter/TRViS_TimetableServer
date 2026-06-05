<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for the InviteKey entity. InviteKey is keyed to a
 * WorkGroup (under a Project); privilege resolves through the project's
 * projects_privileges. These tests also regress the L-2 fix: selectInviteKey
 * must gate disclosure behind WorkGroup `admin` (read-not-admin -> 403,
 * non-member -> 404). Driven at the InviteKeysService layer against the real
 * test MySQL, mirroring tests/Api/WorkGroupApiTest.php.
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\InviteKey;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\service\InviteKeysService;
use dev_t0r\trvis_backend\service\ProjectsService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(\dev_t0r\trvis_backend\api\InviteKeyApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'createInviteKey')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'getInviteKey')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'deleteInviteKey')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'getInviteKeyList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'getMyInviteKeyList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'updateInviteKey')]
#[CoversMethod(\dev_t0r\trvis_backend\api\InviteKeyApi::class, 'useInviteKey')]
class InviteKeyApiTest extends IntegrationTestCase
{
	private function ikSvc(): InviteKeysService
	{
		return new InviteKeysService($this->db, $this->logger);
	}

	private function wgSvc(): WorkGroupsService
	{
		return new WorkGroupsService($this->db, $this->logger);
	}

	/** Create a WorkGroup under the fixture project; register for teardown. */
	private function newWorkGroup(): UuidInterface
	{
		$r = $this->wgSvc()->createWorkGroupInProject(
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

	/**
	 * Create an InviteKey owned by the admin fixture user.
	 *
	 * Also (re)registers a projects_privileges cleanup keyed by projects_id:
	 * useInviteKey writes a projects_privileges row referencing invite_keys,
	 * and the FK is RESTRICT, so projects_privileges must be deleted before
	 * invite_keys. Registering it last makes teardown (reverse order) delete
	 * it first; the delete is idempotent (by projects_id).
	 */
	private function newInviteKey(
		UuidInterface $wgId,
		InviteKeyPrivilegeType $priv = InviteKeyPrivilegeType::write,
	): InviteKey {
		$r = $this->ikSvc()->createInviteKey(
			$wgId,
			$this->userId,
			$this->makeModel(InviteKey::class, [
				'description' => 'k',
				// valid_from is optional (NOT NULL DEFAULT CURRENT_TIMESTAMP);
				// the repo coalesces null -> "now". Passed explicitly here for
				// determinism; testCreateInviteKeyDefaults covers the omitted path.
				'valid_from' => new \DateTime('2020-01-01T00:00:00+00:00'),
				'privilege_type' => $priv,
			]),
		);
		$this->assertOk($r, 'createInviteKey');
		$key = $r->value;
		$this->register('invite_keys', 'invite_keys_id', (string)$key->invite_keys_id);
		$this->register('projects_privileges', 'projects_id', (string)$this->projectId);
		return $key;
	}

	/** Grant a brand-new uid exactly `read` on the fixture project. */
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

	public function testCreateInviteKey(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId, InviteKeyPrivilegeType::write);
		$this->assertTrue(Uuid::isValid((string)$key->invite_keys_id));
		$this->assertSame((string)$wgId, (string)$key->work_groups_id);
		$this->assertSame(InviteKeyPrivilegeType::write, $key->privilege_type);

		// read-only user must NOT be able to create an InviteKey
		$readUser = $this->grantReadOnlyUser();
		$c = $this->ikSvc()->createInviteKey(
			$wgId,
			$readUser,
			$this->makeModel(InviteKey::class, [
				'description' => 'nope',
				'valid_from' => new \DateTime('2020-01-01T00:00:00+00:00'),
				'privilege_type' => InviteKeyPrivilegeType::read,
			]),
		);
		$this->assertTrue($c->isError, 'read-only createInviteKey must be rejected');
		$this->assertSame(403, $c->statusCode);
	}

	/**
	 * Recommended-spec regression: valid_from may be omitted by the client;
	 * the repo must coalesce null -> "now" (DB DEFAULT CURRENT_TIMESTAMP),
	 * not bind explicit NULL into the NOT NULL column (23000 / 500).
	 */
	public function testCreateInviteKeyDefaults(): void
	{
		$wgId = $this->newWorkGroup();

		$before = new \DateTime('now', new \DateTimeZone('UTC'));
		$before->modify('-5 seconds');
		$r = $this->ikSvc()->createInviteKey(
			$wgId,
			$this->userId,
			$this->makeModel(InviteKey::class, [
				'description' => 'no-valid-from',
				// valid_from intentionally omitted
				'privilege_type' => InviteKeyPrivilegeType::read,
			]),
		);
		$this->assertOk($r, 'createInviteKey (valid_from omitted)');
		$keyId = $r->value->invite_keys_id;
		$this->register('invite_keys', 'invite_keys_id', (string)$keyId);
		$this->register('projects_privileges', 'projects_id', (string)$this->projectId);
		$after = new \DateTime('now', new \DateTimeZone('UTC'));
		$after->modify('+5 seconds');

		$st = $this->db->prepare(
			"SELECT valid_from FROM invite_keys WHERE invite_keys_id = :id"
		);
		$st->execute([':id' => $keyId->getBytes()]);
		$persisted = (string)$st->fetchColumn();
		$this->assertNotSame('', $persisted, 'valid_from must be persisted (NOT NULL)');
		$vf = new \DateTime($persisted . ' UTC');
		$this->assertGreaterThanOrEqual($before, $vf, 'omitted valid_from must default to ~now');
		$this->assertLessThanOrEqual($after, $vf, 'omitted valid_from must default to ~now');
	}

	/**
	 * Security regression (L-2): selectInviteKey discloses privilege_type /
	 * work_groups_id and must therefore be gated behind WorkGroup `admin`.
	 */
	public function testGetInviteKey(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId);

		// admin -> OK, full disclosure
		$g = $this->ikSvc()->selectInviteKey($key->invite_keys_id, $this->userId);
		$this->assertOk($g, 'admin selectInviteKey');
		$this->assertSame((string)$key->invite_keys_id, (string)$g->value->invite_keys_id);

		// has `read` but not `admin` -> 404. selectInviteKey is a GET; per the
		// unified rule GET privilege errors hide existence (404), so a
		// read-only member is answered exactly like a non-member.
		$readUser = $this->grantReadOnlyUser();
		$r = $this->ikSvc()->selectInviteKey($key->invite_keys_id, $readUser);
		$this->assertTrue($r->isError, 'read-not-admin must be rejected');
		$this->assertSame(404, $r->statusCode);

		// no privilege at all -> 404 (do not even reveal existence)
		$nm = $this->ikSvc()->selectInviteKey($key->invite_keys_id, $this->nonMemberId);
		$this->assertTrue($nm->isError, 'non-member must be rejected');
		$this->assertSame(404, $nm->statusCode);

		// unknown key id -> 404
		$unknown = $this->ikSvc()->selectInviteKey(Uuid::uuid7(), $this->userId);
		$this->assertTrue($unknown->isError, 'unknown id must be 404');
		$this->assertSame(404, $unknown->statusCode);
	}

	public function testDeleteInviteKey(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId);

		// read-only user cannot disable
		$readUser = $this->grantReadOnlyUser();
		$ro = $this->ikSvc()->disableInviteKey($key->invite_keys_id, $readUser);
		$this->assertTrue($ro->isError, 'read-only disable must be rejected');
		$this->assertSame(403, $ro->statusCode);

		// non-member -> 404
		$nm = $this->ikSvc()->disableInviteKey($key->invite_keys_id, $this->nonMemberId);
		$this->assertTrue($nm->isError, 'non-member disable must be rejected');
		$this->assertSame(404, $nm->statusCode);

		// admin disables -> OK
		$d = $this->ikSvc()->disableInviteKey($key->invite_keys_id, $this->userId);
		$this->assertOk($d, 'admin disableInviteKey');

		// second disable -> 400 (already disabled)
		$d2 = $this->ikSvc()->disableInviteKey($key->invite_keys_id, $this->userId);
		$this->assertTrue($d2->isError, 'double disable must be rejected');
		$this->assertSame(400, $d2->statusCode);

		// unknown key id -> 404
		$unknown = $this->ikSvc()->disableInviteKey(Uuid::uuid7(), $this->userId);
		$this->assertTrue($unknown->isError, 'unknown id must be 404');
		$this->assertSame(404, $unknown->statusCode);
	}

	public function testGetInviteKeyList(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId);

		// admin -> list contains the key.
		// page is 1-based (the API passes PagingQueryValidator::pageFrom1); this
		// must be 1, not 0 — passing 0 previously masked the repo's off-by-one
		// OFFSET so the first page silently vanished over HTTP.
		$list = $this->ikSvc()->selectInviteKeyListWithWorkGroupsId(
			$wgId,
			$this->userId,
			1,
			50,
			null,
		);
		$this->assertOk($list, 'admin selectInviteKeyListWithWorkGroupsId');
		$ids = array_map(fn ($x) => (string)$x->invite_keys_id, $list->value);
		$this->assertContains((string)$key->invite_keys_id, $ids);

		// read-not-admin -> 404 (GET privilege errors hide existence, same as a
		// non-member; see the unified GET=404 / mutation-tier=403 rule).
		$readUser = $this->grantReadOnlyUser();
		$ro = $this->ikSvc()->selectInviteKeyListWithWorkGroupsId(
			$wgId,
			$readUser,
			1,
			50,
			null,
		);
		$this->assertTrue($ro->isError, 'read-not-admin list must be rejected');
		$this->assertSame(404, $ro->statusCode);
	}

	public function testGetMyInviteKeyList(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId);

		// owner sees own key (listing is by owner uid, no privilege gate).
		// page is 1-based — see the off-by-one note in testGetInviteKeyList.
		$mine = $this->ikSvc()->selectInviteKeyListWithOwnerUid(
			$this->userId,
			1,
			50,
			null,
		);
		$this->assertOk($mine, 'selectInviteKeyListWithOwnerUid (owner)');
		$ids = array_map(fn ($x) => (string)$x->invite_keys_id, $mine->value);
		$this->assertContains((string)$key->invite_keys_id, $ids);

		// a different user's "my list" must not include this key
		$other = $this->ikSvc()->selectInviteKeyListWithOwnerUid(
			$this->nonMemberId,
			1,
			50,
			null,
		);
		$this->assertOk($other, 'selectInviteKeyListWithOwnerUid (other)');
		$otherIds = array_map(fn ($x) => (string)$x->invite_keys_id, $other->value);
		$this->assertNotContains((string)$key->invite_keys_id, $otherIds);
	}

	/**
	 * updateInviteKey is intentionally not implemented: InviteKeyApi
	 * returns 501 Not Implemented and InviteKeysService has no
	 * corresponding method. Documented as a deliberate contract.
	 */
	public function testUpdateInviteKey(): void
	{
		$this->assertFalse(
			method_exists(InviteKeysService::class, 'updateInviteKey'),
			'updateInviteKey is intentionally unimplemented (API returns 501)',
		);
	}

	public function testUseInviteKey(): void
	{
		$wgId = $this->newWorkGroup();
		$key = $this->newInviteKey($wgId, InviteKeyPrivilegeType::write);

		$newUser = 'it-N-' . bin2hex(random_bytes(4));
		$u = $this->ikSvc()->useInviteKey($key->invite_keys_id, $newUser);
		$this->assertOk($u, 'useInviteKey grants privilege');

		// the user now holds `write` on the project (Project = privilege root)
		$st = $this->db->prepare(
			"SELECT privilege_type FROM projects_privileges "
			. "WHERE projects_id = :pid AND uid = :uid"
		);
		$st->execute([
			':pid' => $this->projectId->getBytes(),
			':uid' => $newUser,
		]);
		$this->assertSame(
			InviteKeyPrivilegeType::write->value,
			(int)$st->fetchColumn(),
			'useInviteKey must write projects_privileges',
		);

		// H8(b) (a96e610): redeeming a key whose privilege you already hold is
		// an idempotent success, not an error. The contract is 200 -> WorkGroup;
		// useInviteKey rolls back and re-reads the target WorkGroup as the body.
		$again = $this->ikSvc()->useInviteKey($key->invite_keys_id, $newUser);
		$this->assertOk($again, 'redundant useInviteKey is idempotent success');
		$this->assertSame(200, $again->statusCode);
		$this->assertSame(
			(string)$wgId,
			(string)$again->value->work_groups_id,
			'redundant use must return the target WorkGroup',
		);
	}
}
