<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: OpenAPI-Generator stub filled with DB integration tests for
 * Color (ColorApi -> ColorsService). Color is keyed to a WorkGroup;
 * privilege resolves through the parent project's projects_privileges.
 * ColorsService extends MyServiceBase, so getOne/update/delete go
 * through ColorsRepo (MyRepoBase) selectPrivilegeType -> this regresses
 * the project-root privilege-resolution fix (admin -> 200, not 404).
 * Mirrors tests/Api/StationApiTest.php (single hop under WG).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\model\ColorReal;
use dev_t0r\trvis_backend\service\ColorsService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__ . '/../Integration/IntegrationTestCase.php';

#[CoversClass(\dev_t0r\trvis_backend\api\ColorApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\ColorApi::class, 'createColor')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ColorApi::class, 'getColor')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ColorApi::class, 'getColorList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ColorApi::class, 'updateColor')]
#[CoversMethod(\dev_t0r\trvis_backend\api\ColorApi::class, 'deleteColor')]
class ColorApiTest extends IntegrationTestCase
{
	private function svc(): ColorsService
	{
		return new ColorsService($this->db, $this->logger);
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

	private function createOne(UuidInterface $wgId, array $over = []): Color
	{
		$data = array_merge([
			'name' => 'C',
			'description' => 'd',
			'color_8bit' => $this->makeModel(Color8bit::class, [
				'red' => 10,
				'green' => 20,
				'blue' => 30,
			]),
			'color_real' => $this->makeModel(ColorReal::class, [
				'red' => 0.1,
				'green' => 0.2,
				'blue' => 0.3,
			]),
		], $over);
		$r = $this->svc()->create($wgId, $this->userId, [$this->makeModel(Color::class, $data)]);
		$this->assertOk($r, 'createColor');
		$o = $r->value[0];
		$this->register('colors', 'colors_id', (string)$o->colors_id);
		return $o;
	}

	public function testCreateColor()
	{
		$wg = $this->newWorkGroup();
		$o = $this->createOne($wg);
		$this->assertTrue(Uuid::isValid((string)$o->colors_id));
		$this->assertSame((string)$wg, (string)$o->work_groups_id);
		$this->assertSame('C', $o->name);
	}

	public function testGetColor()
	{
		$o = $this->createOne($this->newWorkGroup());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->colors_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->colors_id, (string)$g->value->colors_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->colors_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetColorList()
	{
		$wg = $this->newWorkGroup();
		$a = $this->createOne($wg, ['name' => 'A']);
		$b = $this->createOne($wg, ['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $wg, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->colors_id, $list->value);
		$this->assertContains((string)$a->colors_id, $ids);
		$this->assertContains((string)$b->colors_id, $ids);
	}

	public function testUpdateColor()
	{
		$o = $this->createOne($this->newWorkGroup(), ['name' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->colors_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->colors_id,
			$this->makeModel(Color::class, ['name' => 'after']),
			['name' => 'after'],
		);
		$this->assertOk($u, 'updateColor');
		$g = $this->svc()->getOne($this->userId, $o->colors_id);
		$this->assertSame('after', $g->value->name);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->colors_id),
			'updated_at must advance',
		);
	}

	public function testDeleteColor()
	{
		$o = $this->createOne($this->newWorkGroup());
		$d = $this->svc()->delete($this->userId, $o->colors_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->colors_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM colors WHERE colors_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
