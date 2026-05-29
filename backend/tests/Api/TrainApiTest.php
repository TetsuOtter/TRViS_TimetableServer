<?php

/**
 * TRViS用 時刻表管理用API
 *
 * NOTE: DB integration tests for Train (TrainApi -> TrainsService). Train is
 * keyed to a Work (-> WG); privilege resolves through the project's
 * projects_privileges via the Work parent chain. TrainsService is standalone
 * (no MyServiceBase), so getOne/update/delete go through
 * TrainsRepo::selectPrivilegeType -> WorkGroupsPrivilegesRepo ->
 * ProjectsPrivilegesRepo. This regresses the project-root privilege-resolution
 * fix (admin -> 200, not 404).
 * @see tests/Integration/IntegrationTestCase.php
 */

namespace dev_t0r\trvis_backend\api;

use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use dev_t0r\trvis_backend\auth\MyAuthMiddleware;
use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\service\TrainsService;
use dev_t0r\trvis_backend\service\WorksService;
use dev_t0r\trvis_backend\service\WorkGroupsService;
use dev_t0r\trvis_backend\tests\integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// IntegrationTestCase is composer classmap-autoloaded
// (autoload-dev.classmap: ["tests/Integration/"]); no require_once needed.

#[CoversClass(\dev_t0r\trvis_backend\api\TrainApi::class)]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'createTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'getTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'getTrainList')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'updateTrain')]
#[CoversMethod(\dev_t0r\trvis_backend\api\TrainApi::class, 'deleteTrain')]
class TrainApiTest extends IntegrationTestCase
{
	private function svc(): TrainsService
	{
		return new TrainsService($this->db, $this->logger);
	}

	/** Create Project-rooted WG -> Work; return the Work id (Train parent). */
	private function newWork(): UuidInterface
	{
		$wg = (new WorkGroupsService($this->db, $this->logger))->createWorkGroupInProject(
			$this->projectId,
			$this->userId,
			'IT WG',
			'desc',
		);
		$this->assertOk($wg, 'createWorkGroupInProject');
		$wgId = $wg->value->work_groups_id;
		$this->register('work_groups', 'work_groups_id', (string)$wgId);

		$w = (new WorksService($this->db, $this->logger))->create(
			$wgId,
			$this->userId,
			[$this->makeModel(Work::class, ['name' => 'W', 'description' => 'd'])],
		);
		$this->assertOk($w, 'createWork');
		$worksId = $w->value[0]->works_id;
		$this->register('works', 'works_id', (string)$worksId);
		return $worksId;
	}

	private function createOne(UuidInterface $worksId, array $over = []): Train
	{
		$data = array_merge([
			'description' => 'd',
			'train_number' => 'T1',
			'direction' => 1,
			'day_count' => 0,
			// is_ride_on_moving is optional (BOOLEAN NOT NULL DEFAULT FALSE);
			// the repo coalesces null -> false. Passed explicitly here for
			// determinism; testCreateTrainDefaults covers the omitted path.
			'is_ride_on_moving' => false,
		], $over);
		$r = $this->svc()->create($worksId, $this->userId, [$this->makeModel(Train::class, $data)]);
		$this->assertOk($r, 'createTrain');
		$o = $r->value[0];
		$this->register('trains', 'trains_id', (string)$o->trains_id);
		return $o;
	}

	public function testCreateTrain(): void
	{
		$work = $this->newWork();
		$o = $this->createOne($work);
		$this->assertTrue(Uuid::isValid((string)$o->trains_id));
		$this->assertSame((string)$work, (string)$o->works_id);
		$this->assertSame('T1', $o->train_number);
	}

	/**
	 * Recommended-spec regression: is_ride_on_moving may be omitted by the
	 * client; the repo must coalesce null -> DB DEFAULT (false), not 500.
	 */
	public function testCreateTrainDefaults(): void
	{
		$work = $this->newWork();
		$data = [
			'description' => 'd',
			'train_number' => 'T-default',
			'direction' => 1,
			'day_count' => 0,
			// is_ride_on_moving intentionally omitted
		];
		$r = $this->svc()->create($work, $this->userId, [$this->makeModel(Train::class, $data)]);
		$this->assertOk($r, 'createTrain (is_ride_on_moving omitted)');
		$trainsId = $r->value[0]->trains_id;
		$this->register('trains', 'trains_id', (string)$trainsId);

		$g = $this->svc()->getOne($this->userId, $trainsId);
		$this->assertOk($g, 'getOne');
		// MySQL BOOLEAN is TINYINT(1): getOne maps it back as int 0/1.
		// assertSame(0, ...) (not (bool) cast) so a future null-mapping bug
		// is not masked by null->false coercion.
		$this->assertSame(0, (int)$g->value->is_ride_on_moving, 'omitted is_ride_on_moving must persist as DB DEFAULT false');
	}

	public function testGetTrain(): void
	{
		$o = $this->createOne($this->newWork());
		// admin -> 200 (regresses the project-root privilege fix)
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertOk($g, 'getOne');
		$this->assertSame((string)$o->trains_id, (string)$g->value->trains_id);
		// non-member -> 404 (meaningful only because admin got 200 above)
		$nm = $this->svc()->getOne($this->nonMemberId, $o->trains_id);
		$this->assertTrue($nm->isError);
		$this->assertSame(404, $nm->statusCode);
	}

	public function testGetTrainList(): void
	{
		$work = $this->newWork();
		$a = $this->createOne($work, ['train_number' => 'A']);
		$b = $this->createOne($work, ['train_number' => 'B']);
		$list = $this->svc()->getPage($this->userId, $work, 1, 50, null);
		$this->assertOk($list, 'getPage');
		$ids = array_map(fn($x) => (string)$x->trains_id, $list->value);
		$this->assertContains((string)$a->trains_id, $ids);
		$this->assertContains((string)$b->trains_id, $ids);
	}

	public function testUpdateTrain(): void
	{
		$o = $this->createOne($this->newWork(), ['train_number' => 'before']);
		$before = $this->fetchUpdatedAt((string)$o->trains_id);
		sleep(1);
		$u = $this->svc()->update(
			$this->userId,
			$o->trains_id,
			$this->makeModel(Train::class, ['train_number' => 'after']),
			['train_number' => 'after'],
		);
		$this->assertOk($u, 'updateTrain');
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertSame('after', $g->value->train_number);
		$this->assertGreaterThan(
			$before,
			$this->fetchUpdatedAt((string)$o->trains_id),
			'updated_at must advance',
		);
	}

	public function testDeleteTrain(): void
	{
		$o = $this->createOne($this->newWork());
		$d = $this->svc()->delete($this->userId, $o->trains_id);
		$this->assertOk($d, 'delete');
		$g = $this->svc()->getOne($this->userId, $o->trains_id);
		$this->assertTrue($g->isError);
		$this->assertSame(404, $g->statusCode);
	}

	/**
	 * Build a ServerRequest with a JSON-decoded parsed body and a fake auth
	 * token attribute set so getUserIdOrNull() returns the given userId.
	 *
	 * @param array<string,mixed>|list<array<string,mixed>> $parsedBody
	 */
	private function buildRequest(mixed $parsedBody, string $userId): \Psr\Http\Message\ServerRequestInterface
	{
		$tokenMock = $this->createMock(UnencryptedToken::class);
		$claims = new DataSet(['sub' => $userId], '');
		$tokenMock->method('claims')->willReturn($claims);

		$req = (new ServerRequestFactory())->createServerRequest('POST', '/');
		return $req
			->withParsedBody($parsedBody)
			->withAttribute(MyAuthMiddleware::ATTR_NAME_TOKEN_OBJ, $tokenMock);
	}

	/**
	 * Build a fully-populated Train body (all optional properties set to null)
	 * to prevent BaseModel::__get "Undefined array key" warnings when the
	 * service/repo reads optional fields from the model.
	 *
	 * @param array<string,mixed> $over
	 * @return array<string,mixed>
	 */
	private function trainBody(array $over = []): array
	{
		return array_merge([
			'train_number' => 'API-T1',
			'description' => 'd',
			'direction' => 1,
			'day_count' => 0,
			'is_ride_on_moving' => false,
			'max_speed' => null,
			'speed_type' => null,
			'nominal_tractive_capacity' => null,
			'car_count' => null,
			'destination' => null,
			'begin_remarks' => null,
			'after_remarks' => null,
			'remarks' => null,
			'before_departure' => null,
			'after_arrive' => null,
			'train_info' => null,
		], $over);
	}

	/**
	 * Api-level single-item create test: POST a single JSON object to
	 * /works/{workId}/trains — assert HTTP 200 and body is a single object
	 * (not a list). This is the faithful-port contract from KNOWLEDGE.md §1.
	 */
	public function testCreateTrainApiSingleItemReturns200(): void
	{
		$worksId = $this->newWork();
		$api = new TrainApi($this->db, $this->logger);

		$req = $this->buildRequest($this->trainBody(['train_number' => 'API-1']), $this->userId);
		$resp = $api->createTrain($req, (new ResponseFactory())->createResponse(), (string)$worksId);

		$this->assertSame(200, $resp->getStatusCode(), 'single-item create must return HTTP 200');

		$decoded = json_decode((string)$resp->getBody(), true);
		$this->assertIsArray($decoded, 'body must be JSON-decoded to an array/object');
		$this->assertFalse(
			array_is_list($decoded),
			'single-item create body must be an associative object, not a list',
		);
		$this->assertArrayHasKey('train_number', $decoded);
		$this->assertSame('API-1', $decoded['train_number']);

		// Cleanup: register the created train for teardown
		if (isset($decoded['trains_id'])) {
			$this->register('trains', 'trains_id', $decoded['trains_id']);
		}
	}

	/**
	 * Bonus: POST array body to /works/{workId}/trains — assert HTTP 201 + list.
	 */
	public function testCreateTrainApiArrayBodyReturns201(): void
	{
		$worksId = $this->newWork();
		$api = new TrainApi($this->db, $this->logger);

		$body = [
			$this->trainBody(['train_number' => 'ARR-1', 'direction' => 1]),
			$this->trainBody(['train_number' => 'ARR-2', 'direction' => -1]),
		];
		$req = $this->buildRequest($body, $this->userId);
		$resp = $api->createTrain($req, (new ResponseFactory())->createResponse(), (string)$worksId);

		$this->assertSame(201, $resp->getStatusCode(), 'array create must return HTTP 201');

		$decoded = json_decode((string)$resp->getBody(), true);
		$this->assertIsArray($decoded, 'body must be JSON array');
		$this->assertTrue(array_is_list($decoded), 'array create body must be a list');
		$this->assertCount(2, $decoded);

		foreach ($decoded as $item) {
			if (isset($item['trains_id'])) {
				$this->register('trains', 'trains_id', $item['trains_id']);
			}
		}
	}

	private function fetchUpdatedAt(string $id): string
	{
		$st = $this->db->prepare(
			"SELECT updated_at FROM trains WHERE trains_id = :id"
		);
		$st->execute([':id' => Uuid::fromString($id)->getBytes()]);
		return (string)$st->fetchColumn();
	}
}
