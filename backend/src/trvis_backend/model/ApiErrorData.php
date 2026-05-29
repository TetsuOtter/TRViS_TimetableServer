<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * ApiErrorData — the `{code, message}` body every error response carries
 * (Utils::withError emits exactly this shape). Faithful port of the legacy
 * inline `error_code_message` schema (title preserved 1:1); neither property
 * is required (matches the legacy inline shape).
 *
 * Written once here as a peer of ApiInfo so the Project worked example — and
 * every P3 *Api after it — can `$ref: '#/components/schemas/ApiErrorData'`
 * its 400/401/403/404 responses without a shared mutable file. Properties
 * live on the #[OA\Schema] attribute (not as PHP properties) so they don't
 * collide with BaseModel's __get/__set magic. OAS_PROPERTIES / OAS_REQUIRED
 * drive the runtime model; the attribute drives swagger-php.
 * ApiErrorDataDriftTest asserts the two stay in sync.
 */
#[OA\Schema(
	schema: 'ApiErrorData',
	title: 'error_code_message',
	type: 'object',
	description: 'エラーコードとエラーメッセージ',
	properties: [
		new OA\Property(
			property: 'code',
			type: 'number',
			description: 'エラーコード',
		),
		new OA\Property(
			property: 'message',
			type: 'string',
			description: 'エラーメッセージ',
		),
	],
)]
class ApiErrorData extends BaseModel
{
	protected const OAS_PROPERTIES = ['code', 'message'];
	protected const OAS_REQUIRED = [];
}
