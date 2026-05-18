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
use dev_t0r\trvis_backend\service\LineService;
use dev_t0r\trvis_backend\service\ProjectStationsService;
use dev_t0r\trvis_backend\service\StopPatternsService;
use dev_t0r\trvis_backend\service\StationsOnLineService;
use dev_t0r\trvis_backend\service\StopPatternRowsService;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\repo\StopPatternsRepo;
use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\model\ProjectStationLocationLonlat;
use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\model\StopPatternRow;
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
$lineIds = [];
$psIds = [];   // project_stations
$spIds = [];   // stop_patterns
$solIds = [];  // stations_on_line
$sprIds = [];  // stop_pattern_rows

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

	echo "== Scenario 8 (Phase 7): Line CRUD + project-rooted privilege ==\n";
	$lineSvc = new LineService($db, $logger);
	$lineRepo = new LineRepo($db, $logger);
	$lm = new Line();
	$lm->setData(['name' => "P7 Line $suffix", 'description' => 'line desc']);
	$c8 = $lineSvc->create(Uuid::fromString($projId), $U, [$lm]);
	check('8 createLine not error', !$c8->isError, $c8->isError ? "[{$c8->statusCode}] {$c8->errorMsg}" : '');
	if (!$c8->isError) {
		check('8 create returns 1 Line', is_array($c8->value) && count($c8->value) === 1,
			'count=' . (is_array($c8->value) ? count($c8->value) : gettype($c8->value)));
		$line = $c8->value[0];
		$lineId = (string)$line->lines_id;
		$lineIds[] = $lineId;
		check('8 Line.projects_id == this project (reserved-word alias OK)',
			(string)$line->projects_id === $projId, "got " . (string)$line->projects_id);
		check('8 Line.name round-trips', $line->name === "P7 Line $suffix");
		// DB: row is in project_lines with project_lines_id, not a "lines" table
		check('8 row exists in project_lines', cntOwned($db, 'project_lines', 'owner', $U) === 1);

		// getOne (privilege via LineRepo::selectPrivilegeType project-rooted override)
		$g8 = $lineSvc->getOne($U, Uuid::fromString($lineId));
		check('8 getOne(admin) not error', !$g8->isError, $g8->isError ? "[{$g8->statusCode}] {$g8->errorMsg}" : '');
		check('8 getOne returns same line', !$g8->isError && (string)$g8->value->lines_id === $lineId);

		// getList by project
		$l8 = $lineSvc->getPage($U, Uuid::fromString($projId), 1, 10, null);
		check('8 getLineList not error', !$l8->isError, $l8->isError ? "[{$l8->statusCode}] {$l8->errorMsg}" : '');
		check('8 getLineList returns the line', !$l8->isError && is_array($l8->value)
			&& count(array_filter($l8->value, fn($x) => (string)$x->lines_id === $lineId)) === 1);

		// project-rooted privilege override: admin sees admin, non-member -> 404
		$p8a = $lineRepo->selectPrivilegeType(id: Uuid::fromString($lineId), userId: $U, includeAnonymous: true);
		check('8 selectPrivilegeType(owner) == admin', !$p8a->isError && $p8a->value === InviteKeyPrivilegeType::admin,
			$p8a->isError ? "[{$p8a->statusCode}] {$p8a->errorMsg}" : 'got ' . var_export($p8a->value, true));
		$p8b = $lineRepo->selectPrivilegeType(id: Uuid::fromString($lineId), userId: $X, includeAnonymous: true);
		check('8 selectPrivilegeType(non-member) -> 404', $p8b->isError && $p8b->statusCode === 404,
			'isError=' . var_export($p8b->isError, true) . " status={$p8b->statusCode}");

		// update name -> reflected
		$lm2 = new Line();
		$lm2->setData(['name' => "P7 Line UPDATED $suffix"]);
		$u8 = $lineSvc->update($U, Uuid::fromString($lineId), $lm2, ['name' => "P7 Line UPDATED $suffix"]);
		check('8 updateLine not error', !$u8->isError, $u8->isError ? "[{$u8->statusCode}] {$u8->errorMsg}" : '');
		$g8b = $lineSvc->getOne($U, Uuid::fromString($lineId));
		check('8 update reflected', !$g8b->isError && $g8b->value->name === "P7 Line UPDATED $suffix",
			$g8b->isError ? '' : 'got ' . $g8b->value->name);

		// soft-delete -> gone from getOne and list
		$d8 = $lineSvc->delete($U, Uuid::fromString($lineId));
		check('8 deleteLine not error', !$d8->isError, $d8->isError ? "[{$d8->statusCode}] {$d8->errorMsg}" : '');
		$g8c = $lineSvc->getOne($U, Uuid::fromString($lineId));
		check('8 deleted Line -> 404', $g8c->isError && $g8c->statusCode === 404,
			'isError=' . var_export($g8c->isError, true) . " status={$g8c->statusCode}");
	}

	// shared fixtures for S10-S12: a live Line + a ProjectStation under $projId
	$lineSvc = new LineService($db, $logger);
	$fxLine = new Line();
	$fxLine->setData(['name' => "FX Line $suffix", 'description' => 'fx']);
	$fxLineR = $lineSvc->create(Uuid::fromString($projId), $U, [$fxLine]);
	$fxLineId = $fxLineR->isError ? null : (string)$fxLineR->value[0]->lines_id;
	if ($fxLineId !== null) { $lineIds[] = $fxLineId; }

	echo "== Scenario 9 (Phase 7): ProjectStation CRUD + lonlat round-trip ==\n";
	$psSvc = new ProjectStationsService($db, $logger);
	$ll = new ProjectStationLocationLonlat();
	$ll->setData(['longitude' => 139.766944, 'latitude' => 35.681111]);
	$ps = new ProjectStation();
	$ps->setData(['name' => "PS $suffix", 'always_show_hh' => true, 'location_lonlat' => $ll]);
	$c9 = $psSvc->create(Uuid::fromString($projId), $U, [$ps]);
	check('9 createProjectStation not error', !$c9->isError, $c9->isError ? "[{$c9->statusCode}] {$c9->errorMsg}" : '');
	$psId = null;
	if (!$c9->isError) {
		$psObj = $c9->value[0];
		$psId = (string)$psObj->project_stations_id;
		$psIds[] = $psId;
		check('9 PS.projects_id == project', (string)$psObj->projects_id === $projId);
		check('9 PS.always_show_hh round-trips true', $psObj->always_show_hh === true);
		check('9 PS lonlat round-trips (ST_PointFromText/ST_X)',
			$psObj->location_lonlat !== null
			&& abs($psObj->location_lonlat->longitude - 139.766944) < 1e-6
			&& abs($psObj->location_lonlat->latitude - 35.681111) < 1e-6,
			'got ' . json_encode($psObj->location_lonlat));
		$g9 = $psSvc->getOne($U, Uuid::fromString($psId));
		check('9 getOne not error', !$g9->isError);
		$u9 = $psSvc->update($U, Uuid::fromString($psId),
			(function(){ $m = new ProjectStation(); $m->setData(['name' => 'PS RENAMED']); return $m; })(),
			['name' => 'PS RENAMED']);
		check('9 update not error', !$u9->isError, $u9->isError ? "[{$u9->statusCode}] {$u9->errorMsg}" : '');
		$g9b = $psSvc->getOne($U, Uuid::fromString($psId));
		check('9 update reflected', !$g9b->isError && $g9b->value->name === 'PS RENAMED');
	}

	echo "== Scenario 10 (Phase 7): StopPattern (lines_id alias + direction int update) ==\n";
	$spSvc = new StopPatternsService($db, $logger);
	$sp = new StopPattern();
	// mimic post-UuidValidationRule state: uuid fields are Uuid objects, not strings
	$sp->setData(['name' => "SP $suffix", 'lines_id' => Uuid::fromString($fxLineId), 'direction' => 1]);
	$c10 = $spSvc->create(Uuid::fromString($projId), $U, [$sp]);
	check('10 createStopPattern not error', !$c10->isError, $c10->isError ? "[{$c10->statusCode}] {$c10->errorMsg}" : '');
	$spId = null;
	if (!$c10->isError) {
		$spObj = $c10->value[0];
		$spId = (string)$spObj->stop_patterns_id;
		$spIds[] = $spId;
		check('10 SP.projects_id == project', (string)$spObj->projects_id === $projId);
		check('10 SP.lines_id == fixture line (project_lines alias)', (string)$spObj->lines_id === $fxLineId,
			"got " . (string)$spObj->lines_id);
		check('10 SP.direction round-trips 1', $spObj->direction === 1, 'got ' . var_export($spObj->direction, true));
		// update direction -> -1 (exercises MyRepoBase update int path, post-bugfix)
		$u10 = $spSvc->update($U, Uuid::fromString($spId),
			(function(){ $m = new StopPattern(); $m->setData(['direction' => -1]); return $m; })(),
			['direction' => -1]);
		check('10 update direction not error', !$u10->isError, $u10->isError ? "[{$u10->statusCode}] {$u10->errorMsg}" : '');
		$g10 = $spSvc->getOne($U, Uuid::fromString($spId));
		check('10 direction updated to -1', !$g10->isError && $g10->value->direction === -1,
			$g10->isError ? '' : 'got ' . var_export($g10->value->direction, true));
	}

	echo "== Scenario 11 (Phase 7): StationOnLine (projects_id via subquery, parentRepo=LineRepo) ==\n";
	$solSvc = new StationsOnLineService($db, $logger);
	if ($fxLineId !== null && $psId !== null) {
		$sol = new StationOnLine();
		$sol->setData(['project_stations_id' => Uuid::fromString($psId), 'location_m' => 12345.6, 'track_hidden_by_default' => false]);
		$c11 = $solSvc->create(Uuid::fromString($fxLineId), $U, [$sol]);
		check('11 createStationOnLine not error', !$c11->isError, $c11->isError ? "[{$c11->statusCode}] {$c11->errorMsg}" : '');
		if (!$c11->isError) {
			$solObj = $c11->value[0];
			$solId = (string)$solObj->stations_on_line_id;
			$solIds[] = $solId;
			check('11 SOL.projects_id derived from line == project (subquery insert)',
				(string)$solObj->projects_id === $projId, "got " . (string)$solObj->projects_id);
			check('11 SOL.lines_id == fixture line', (string)$solObj->lines_id === $fxLineId);
			check('11 SOL.project_stations_id == PS', (string)$solObj->project_stations_id === $psId);
			check('11 SOL.location_m round-trips', abs($solObj->location_m - 12345.6) < 1e-6);
			// parentRepo=LineRepo privilege: admin via line, non-member -> 404
			$lr = new LineRepo($db, $logger);
			$p11a = $lr->selectPrivilegeType(id: Uuid::fromString($fxLineId), userId: $U, includeAnonymous: true);
			check('11 LineRepo priv(owner)==admin', !$p11a->isError && $p11a->value === InviteKeyPrivilegeType::admin);
			$p11b = $solSvc->getOne($X, Uuid::fromString($solId));
			check('11 non-member getOne(SOL) -> 404', $p11b->isError && $p11b->statusCode === 404,
				"status={$p11b->statusCode}");
			$d11 = $solSvc->delete($U, Uuid::fromString($solId));
			check('11 delete not error', !$d11->isError);
		}
	} else {
		check('11 fixtures available', false, 'missing fxLine or PS');
	}

	echo "== Scenario 12 (Phase 7): StopPatternRow (projects_id via subquery, parentRepo=StopPatternsRepo) ==\n";
	$sprSvc = new StopPatternRowsService($db, $logger);
	if ($spId !== null && $psId !== null) {
		$spr = new StopPatternRow();
		$spr->setData([
			'project_stations_id' => Uuid::fromString($psId),
			'sort_key' => 0,
			'is_pass' => true,
			'show_arrive' => false,
			'track_name' => '1',
		]);
		$c12 = $sprSvc->create(Uuid::fromString($spId), $U, [$spr]);
		check('12 createStopPatternRow not error', !$c12->isError, $c12->isError ? "[{$c12->statusCode}] {$c12->errorMsg}" : '');
		if (!$c12->isError) {
			$sprObj = $c12->value[0];
			$sprId = (string)$sprObj->stop_pattern_rows_id;
			$sprIds[] = $sprId;
			check('12 SPR.projects_id derived from stop_pattern == project (subquery insert)',
				(string)$sprObj->projects_id === $projId, "got " . (string)$sprObj->projects_id);
			check('12 SPR.stop_patterns_id == parent', (string)$sprObj->stop_patterns_id === $spId);
			check('12 SPR.is_pass round-trips true', $sprObj->is_pass === true);
			check('12 SPR.show_arrive round-trips false (explicit override of default 1)',
				$sprObj->show_arrive === false, 'got ' . var_export($sprObj->show_arrive, true));
			check('12 SPR.track_name round-trips', $sprObj->track_name === '1');
			// parentRepo=StopPatternsRepo privilege resolution
			$spr2 = new StopPatternsRepo($db, $logger);
			$p12 = $spr2->selectPrivilegeType(id: Uuid::fromString($spId), userId: $U, includeAnonymous: true);
			check('12 StopPatternsRepo priv(owner)==admin', !$p12->isError && $p12->value === InviteKeyPrivilegeType::admin);
			$p12b = $sprSvc->getOne($X, Uuid::fromString($sprId));
			check('12 non-member getOne(SPR) -> 404', $p12b->isError && $p12b->statusCode === 404);
			$u12 = $sprSvc->update($U, Uuid::fromString($sprId),
				(function(){ $m = new StopPatternRow(); $m->setData(['sort_key' => 5]); return $m; })(),
				['sort_key' => 5]);
			check('12 update sort_key not error', !$u12->isError, $u12->isError ? "[{$u12->statusCode}] {$u12->errorMsg}" : '');
			$g12 = $sprSvc->getOne($U, Uuid::fromString($sprId));
			check('12 sort_key updated to 5', !$g12->isError && $g12->value->sort_key === 5);
		}
	} else {
		check('12 fixtures available', false, 'missing stop_pattern or PS');
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
		foreach ($sprIds as $id) {
			$db->prepare("DELETE FROM stop_pattern_rows WHERE stop_pattern_rows_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($solIds as $id) {
			$db->prepare("DELETE FROM stations_on_line WHERE stations_on_line_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($spIds as $id) {
			$db->prepare("DELETE FROM stop_patterns WHERE stop_patterns_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($psIds as $id) {
			$db->prepare("DELETE FROM project_stations WHERE project_stations_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($workGroupIds as $id) {
			$db->prepare("DELETE FROM work_groups_privileges WHERE work_groups_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($projectIds as $id) {
			$db->prepare("DELETE FROM projects_privileges WHERE projects_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($workGroupIds as $id) {
			$db->prepare("DELETE FROM work_groups WHERE work_groups_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
		}
		foreach ($lineIds as $id) {
			$db->prepare("DELETE FROM project_lines WHERE project_lines_id = UNHEX(REPLACE(:id,'-',''))")->execute([':id' => $id]);
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
