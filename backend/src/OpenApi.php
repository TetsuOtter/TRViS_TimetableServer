<?php

declare(strict_types=1);

namespace dev_t0r;

use OpenApi\Attributes as OA;

/**
 * Root OpenAPI document metadata.
 *
 * Carries only #[OA\Info] / #[OA\Server] / #[OA\SecurityScheme]; swagger-php
 * collects these into the generated openapi.json. This class is never
 * instantiated — it exists so the document-level annotations have a PSR-4
 * home (swagger-php's ReflectionAnalyser requires one class per file).
 *
 * The server url is `/api/v1` (no trailing slash); individual operation
 * paths add the trailing segment, e.g. ApiInfo is `/api/v1/` (base `/api/v1`
 * + path `/`).
 */
#[OA\Info(
	version: '1.0.0',
	title: 'TRViS用 時刻表管理用API',
	description: 'TRViS 用の時刻表管理 API。PHP 実装が source of truth で、swagger-php が本 OpenAPI 文書を生成する。',
)]
#[OA\Server(url: '/api/v1', description: 'relative (same-origin)')]
#[OA\Server(url: 'https://trvis.t0r.dev/api/v1', description: 'production')]
#[OA\SecurityScheme(
	securityScheme: 'bearerAuth',
	type: 'http',
	scheme: 'bearer',
	bearerFormat: 'JWT',
)]
final class OpenApi
{
}
