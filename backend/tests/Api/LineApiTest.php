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

	private function fetchUpdatedAt(string $lineId): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM project_lines WHERE project_lines_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($lineId)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
