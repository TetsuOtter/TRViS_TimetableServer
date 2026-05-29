<?php

/**
 * TRViS用 時刻表管理用API
 *
 * DB integration tests for Color (ColorApi -> ColorsService). Color is now
 * Project-rooted (re-rooted from work-group; colors.projects_id), privilege
 * resolves through ProjectsPrivilegesRepo. ColorsService is standalone (no
 * MyServiceBase).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\model\ColorReal;
use dev_t0r\trvis_backend\service\ColorsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

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

	private function createOne(array $over = []): Color
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
		$r = $this->svc()->create($this->projectId, $this->userId, [$this->makeModel(Color::class, $data)]);
		$this->assertOk($r, 'createColor');
		$o = $r->value[0];
		$this->register('colors', 'colors_id', (string)$o->colors_id);
		return $o;
	}

	public function testCreateColor()
	{
		$o = $this->createOne();
		$this->assertTrue(Uuid::isValid((string)$o->colors_id));
		$this->assertSame((string)$this->projectId, (string)$o->projects_id);
		$this->assertSame('C', $o->name);
	}

	public function testGetColor()
	{
		$o = $this->createOne();
		// admin -> 200
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
		$a = $this->createOne(['name' => 'A']);
		$b = $this->createOne(['name' => 'B']);
		$list = $this->svc()->getPage($this->userId, $this->projectId, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->colors_id, $list->value);
		$this->assertContains((string)$a->colors_id, $ids);
		$this->assertContains((string)$b->colors_id, $ids);
	}

	public function testUpdateColor()
	{
		$o = $this->createOne(['name' => 'before']);
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
		$o = $this->createOne();
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
