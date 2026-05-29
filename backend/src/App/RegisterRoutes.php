<?php

declare(strict_types=1);

namespace dev_t0r\App;

use dev_t0r\trvis_backend\api\ApiInfoApi;
use dev_t0r\trvis_backend\api\ColorApi;
use dev_t0r\trvis_backend\api\DumpApi;
use dev_t0r\trvis_backend\api\InviteKeyApi;
use dev_t0r\trvis_backend\api\LineApi;
use dev_t0r\trvis_backend\api\ProjectApi;
use dev_t0r\trvis_backend\api\StationApi;
use dev_t0r\trvis_backend\api\StationOnLineApi;
use dev_t0r\trvis_backend\api\StationTrackApi;
use dev_t0r\trvis_backend\api\StopPatternApi;
use dev_t0r\trvis_backend\api\StopPatternRowApi;
use dev_t0r\trvis_backend\api\TimetableRowApi;
use dev_t0r\trvis_backend\api\TrainApi;
use dev_t0r\trvis_backend\api\WorkApi;
use dev_t0r\trvis_backend\api\WorkGroupApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Hand-written route registration.
 *
 * Code-first: each *Api class owns a `public static function routes(): array`
 * mirroring its #[OA\*] operation attributes (no attribute-routing lib). This
 * file only keeps an FQCN registry — a Phase-3 agent wires a new entity by
 * (a) writing its *Api file with routes() and (b) appending one FQCN to
 * API_CLASSES. No shared mutable route table.
 *
 * Each route string is `basePath . path`; e.g. ApiInfo is `/api/v1` + `/`
 * = `/api/v1/` (Slim is strict about the trailing slash — keep the
 * operation's #[OA\Get(path:'/')] and routes() in agreement).
 *
 * The OPTIONS catch-all is registered first so CORS pre-flight for every
 * path is answered before any concrete route; ResponseEmitter then adds
 * the CORS headers lazily.
 *
 * @var array<class-string> API_CLASSES
 */
class RegisterRoutes
{
	private const API_CLASSES = [
		ApiInfoApi::class,
		ProjectApi::class,
		WorkGroupApi::class,
		LineApi::class,
		StopPatternApi::class,
		InviteKeyApi::class,
		ColorApi::class,
		StationApi::class,
		StationOnLineApi::class,
		WorkApi::class,
		StopPatternRowApi::class,
		StationTrackApi::class,
		TrainApi::class,
		TimetableRowApi::class,
		DumpApi::class,
	];

	public function __invoke(\Slim\App $app): void
	{
		// CORS pre-flight catch-all (must be registered before concrete routes)
		$app->options('/{routes:.*}', function (ServerRequestInterface $request, ResponseInterface $response) {
			return $response;
		});

		foreach (self::API_CLASSES as $apiClass) {
			foreach ($apiClass::routes() as $op) {
				$app
					->map($op['methods'], $op['basePath'] . $op['path'], $op['handler'])
					->setName($op['name']);
			}
		}
	}
}
