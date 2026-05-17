<?php
/**
 * Phase 5 runtime validation harness.
 *
 * Bypasses HTTP + Firebase auth (unchanged in Phase 5) and drives the
 * repo/service layer directly against the real MySQL schema to validate:
 *   - createWorkGroup auto-provisions a Project + projects_privileges(admin)
 *   - work_groups_privileges stays empty (vestigial)
 *   - selectWorkGroupOne derives privilege via the rewritten projects_privileges JOIN
 *   - the WorkGroupsPrivilegesRepo chokepoint delegates to ProjectsPrivilegesRepo
 *     and maps the result back to a WorkGroupsPrivilege shape
 *   - updatePrivilege writes to projects_privileges (not work_groups_privileges)
 *   - createWorkGroupInProject reuses the existing Project (no new project row)
 *   - anonymous-adjust ordering: non-member -> none (not error) when projectsId set
 *
 * Run: docker exec webmon-php php /var/www/html/scripts/validate_phase5.php
 */

require '/var/www/html/vendor/autoload.php';

use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use Ramsey\Uuid\Uuid;
use Psr\Log\NullLogger;

$db = new PDO('mysql:host=webmon-db;dbname=test;charset=utf8mb4', 'test', 'test', [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$logger = new NullLogger();

$pass = 0;
$fail = 0;
$failures = [];
function check(string $name, bool $cond, string $detail = ''): void {
	global $pass, $fail, $failures;
	if ($cond) {
		$pass++;
		echo "  PASS  $name\n";
	} else {
		$fail++;
		$failures[] = $name . ($detail !== '' ? "  -- $detail" : '');
		echo "  FAIL  $name" . ($detail !== '' ? "  -- $detail" : '') . "\n";
	}
}

$suffix = bin2hex(random_bytes(4));
$U = "p5-U-$suffix";          // owner / admin
$V = "p5-V-$suffix";          // target user for updatePrivilege
$X = "p5-X-$suffix";          // non-member (anonymous-adjust probe)

// rows created, for cleanup
$projectIds = [];
$workGroupIds = [];

function cntOwned(PDO $db, string $table, string $col, string $val): int {
	$st = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = :v");
	$st->execute([':v' => $val]);
	return (int)$st->fetchColumn();
}

$wgSvc = new WorkGroupsService($db, $logger);
$wgpRepo = new WorkGroupsPrivilegesRepo($db, $logger);

try {
	echo "== Scenario 1: createWorkGroup auto-provisions Project ==\n";
	$r1 = $wgSvc->createWorkGroup(userId: $U, name: "P5 WG $suffix", description: "desc");
	check('createWorkGroup not error', !$r1->isError, $r1->isError ? "[{$r1->statusCode}] {$r1->errorMsg}" : '');
	if ($r1->isError) { throw new RuntimeException('cannot continue: createWorkGroup failed'); }
	$wg = $r1->value;
	$wgId = (string)$wg->work_groups_id;
	$projId = (string)$wg->projects_id;
	$workGroupIds[] = $wgId;
	$projectIds[] = $projId;
	check('returned WG has projects_id', $projId !== '' && Uuid::isValid($projId), "projects_id=$projId");

	check('projects: exactly 1 row owned by U', cntOwned($db, 'projects', 'owner', $U) === 1);
	check('projects_privileges: 1 row for U', cntOwned($db, 'projects_privileges', 'uid', $U) === 1);
	$st = $db->prepare("SELECT privilege_type FROM projects_privileges WHERE uid = :u");
	$st->execute([':u' => $U]);
	check('projects_privileges.privilege_type == admin (3)', ((int)$st->fetchColumn()) === 3);
	check('work_groups: 1 row owned by U', cntOwned($db, 'work_groups', 'owner', $U) === 1);
	check('work_groups_privileges: 0 rows for U (vestigial)', cntOwned($db, 'work_groups_privileges', 'uid', $U) === 0);

	echo "== Scenario 2: selectWorkGroupOne derives privilege via projects_privileges JOIN ==\n";
	$r2 = $wgSvc->selectWorkGroupOne(workGroupsId: Uuid::fromString($wgId), currentUserId: $U);
	check('selectWorkGroupOne not error', !$r2->isError, $r2->isError ? "[{$r2->statusCode}] {$r2->errorMsg}" : '');
	if (!$r2->isError) {
		$one = $r2->value;
		check('selectWorkGroupOne privilege_type == admin', $one->privilege_type === InviteKeyPrivilegeType::admin,
			'got ' . var_export($one->privilege_type, true));
		check('selectWorkGroupOne projects_id matches', (string)$one->projects_id === $projId);
	}

	echo "== Scenario 3: chokepoint delegate + shape map (getPrivileges) ==\n";
	$r3 = $wgSvc->getPrivileges(workGroupsId: Uuid::fromString($wgId), senderUserId: $U, targetUserId: null);
	check('getPrivileges(self) not error', !$r3->isError, $r3->isError ? "[{$r3->statusCode}] {$r3->errorMsg}" : '');
	if (!$r3->isError) {
		$priv = $r3->value;
		check('returned object is WorkGroupsPrivilege shape', $priv instanceof \dev_t0r\trvis_backend\model\WorkGroupsPrivilege,
			'class=' . get_class($priv));
		check('privilege.work_groups_id == requested WG (not projects_id)', (string)$priv->work_groups_id === $wgId,
			"got " . (string)$priv->work_groups_id);
		check('privilege.privilege_type == admin', $priv->privilege_type === InviteKeyPrivilegeType::admin);
	}

	echo "== Scenario 4: updatePrivilege writes to projects_privileges ==\n";
	$r4 = $wgSvc->updatePrivilege(
		workGroupsId: Uuid::fromString($wgId),
		senderUserId: $U,
		targetUserId: $V,
		newPrivilegeType: InviteKeyPrivilegeType::write,
	);
	check('updatePrivilege(V=write) not error', !$r4->isError, $r4->isError ? "[{$r4->statusCode}] {$r4->errorMsg}" : '');
	check('projects_privileges: 1 row for V', cntOwned($db, 'projects_privileges', 'uid', $V) === 1);
	check('work_groups_privileges: 0 rows for V (write went to projects)', cntOwned($db, 'work_groups_privileges', 'uid', $V) === 0);
	$r4b = $wgSvc->getPrivileges(workGroupsId: Uuid::fromString($wgId), senderUserId: $U, targetUserId: $V);
	check('getPrivileges(target V) not error', !$r4b->isError, $r4b->isError ? "[{$r4b->statusCode}] {$r4b->errorMsg}" : '');
	if (!$r4b->isError) {
		check('V privilege_type == write', $r4b->value->privilege_type === InviteKeyPrivilegeType::write,
			'got ' . var_export($r4b->value->privilege_type, true));
	}

	echo "== Scenario 5: createWorkGroupInProject reuses existing Project ==\n";
	$projCountBefore = cntOwned($db, 'projects', 'owner', $U);
	$r5 = $wgSvc->createWorkGroupInProject(
		projectsId: Uuid::fromString($projId),
		userId: $U,
		name: "P5 WG2 $suffix",
		description: "desc2",
	);
	check('createWorkGroupInProject not error', !$r5->isError, $r5->isError ? "[{$r5->statusCode}] {$r5->errorMsg}" : '');
	if (!$r5->isError) {
		$wg2 = $r5->value;
		$workGroupIds[] = (string)$wg2->work_groups_id;
		check('no new project row created', cntOwned($db, 'projects', 'owner', $U) === $projCountBefore,
			"before=$projCountBefore after=" . cntOwned($db, 'projects', 'owner', $U));
		check('new WG points at existing projects_id', (string)$wg2->projects_id === $projId,
			"got " . (string)$wg2->projects_id);
		check('work_groups: now 2 rows owned by U', cntOwned($db, 'work_groups', 'owner', $U) === 2);
	}

	echo "== Scenario 6: anonymous-adjust -> resolve projectsId -> delegate ordering ==\n";
	// 6a: non-member, no anonymous row. Original contract (legacy work_groups_privileges
	// branch returned errWorkGroupNotFound on rowCount==0) => 404. Phase 5 delegates to
	// ProjectsPrivilegesRepo which returns the equivalent errProjectNotFound (also 404).
	$r6a = $wgpRepo->selectPrivilegeType(id: Uuid::fromString($wgId), userId: $X, includeAnonymous: true);
	check('6a non-member -> 404 error (contract preserved)',
		$r6a->isError && $r6a->statusCode === 404,
		'isError=' . var_export($r6a->isError, true) . " status={$r6a->statusCode}");

	// 6b: insert an anonymous (uid='') privilege row on the Project. If the
	// adjust->resolve->delegate order is correct, a non-member with includeAnonymous=true
	// must pick up the anonymous privilege through the delegated ProjectsPrivilegesRepo.
	$db->prepare("INSERT INTO projects_privileges (uid, projects_id, privilege_type) "
		. "VALUES ('', UNHEX(REPLACE(:p,'-','')), 1)")->execute([':p' => $projId]);
	$r6b = $wgpRepo->selectPrivilegeType(id: Uuid::fromString($wgId), userId: $X, includeAnonymous: true);
	check('6b non-member + includeAnonymous=true -> read (delegated anon)',
		!$r6b->isError && $r6b->value === InviteKeyPrivilegeType::read,
		$r6b->isError ? "[{$r6b->statusCode}] {$r6b->errorMsg}" : 'got ' . var_export($r6b->value, true));

	// 6c: same state, includeAnonymous=false -> anonymous row must be excluded -> 404.
	$r6c = $wgpRepo->selectPrivilegeType(id: Uuid::fromString($wgId), userId: $X, includeAnonymous: false);
	check('6c includeAnonymous=false excludes anon -> 404',
		$r6c->isError && $r6c->statusCode === 404,
		'isError=' . var_export($r6c->isError, true) . " status={$r6c->statusCode}");

	$db->prepare("DELETE FROM projects_privileges WHERE uid='' AND projects_id=UNHEX(REPLACE(:p,'-',''))")
		->execute([':p' => $projId]);

	echo "== Scenario 7: selectWorkGroupListByProject (new \$WHERE_PROJECT_ID SQL) ==\n";
	// Backs GET /projects/{id}/work_groups. We created 2 WGs in this Project
	// (Scenario 1 + Scenario 5); the project-scoped list must return both.
	$r7 = $wgSvc->selectWorkGroupListByProject(
		projectsId: Uuid::fromString($projId),
		userId: $U,
		pageFrom1: 1,
		perPage: 10,
		topId: null,
	);
	check('selectWorkGroupListByProject not error', !$r7->isError,
		$r7->isError ? "[{$r7->statusCode}] {$r7->errorMsg}" : '');
	if (!$r7->isError) {
		check('listByProject returns exactly the 2 WGs in this project',
			is_array($r7->value) && count($r7->value) === 2,
			'count=' . (is_array($r7->value) ? count($r7->value) : gettype($r7->value)));
		$allInProject = is_array($r7->value)
			&& count(array_filter($r7->value, fn($w) => (string)$w->projects_id === $projId)) === count($r7->value);
		check('every returned WG belongs to the queried project', $allInProject);
	}
} catch (\Throwable $e) {
	echo "\n!! EXCEPTION: " . get_class($e) . ': ' . $e->getMessage() . "\n";
	echo $e->getTraceAsString() . "\n";
	$fail++;
	$failures[] = 'EXCEPTION ' . $e->getMessage();
} finally {
	// hard cleanup, FK-safe order
	try {
		if ($db->inTransaction()) { $db->rollBack(); }
		foreach ($workGroupIds as $id) {
			$db->prepare("DELETE FROM work_groups_privileges WHERE work_groups_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($projectIds as $id) {
			$db->prepare("DELETE FROM projects_privileges WHERE projects_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($workGroupIds as $id) {
			$db->prepare("DELETE FROM work_groups WHERE work_groups_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($projectIds as $id) {
			$db->prepare("DELETE FROM projects WHERE projects_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		echo "\n(cleanup done)\n";
	} catch (\Throwable $e) {
		echo "\n(cleanup warning: " . $e->getMessage() . ")\n";
	}
}

echo "\n==================================================\n";
echo "RESULT: $pass passed, $fail failed\n";
if ($fail > 0) {
	echo "FAILURES:\n";
	foreach ($failures as $f) { echo "  - $f\n"; }
	exit(1);
}
echo "ALL PHASE 5 RUNTIME CHECKS PASSED\n";
exit(0);
