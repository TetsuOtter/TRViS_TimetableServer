<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;
use OpenApi\Attributes as OA;

/**
 * TRViS_json_WorkGroup — the root object the Dump aggregator emits
 * (DumpService returns a single TRViSJsonWorkGroup). Faithful port of the
 * legacy OpenAPI-Generator MODEL_SCHEMA (required/properties/order/example
 * preserved 1:1).
 *
 * Properties live on the #[OA\Schema] attribute (not as PHP properties) so
 * they don't collide with BaseModel's __get/__set magic. OAS_PROPERTIES /
 * OAS_REQUIRED drive the runtime model; the attribute drives swagger-php.
 * TRViSJsonWorkGroupDriftTest asserts the two stay in sync.
 *
 * The schema name MUST be `TRViS_json_WorkGroup` — DumpApi's 200 response and
 * the nested $ref chain (Work -> Train -> TimetableRow) depend on the exact
 * `TRViS_json_*` names. (Legacy `title:` is intentionally dropped, matching
 * the Work.php sibling precedent.)
 */
#[OA\Schema(
	schema: 'TRViS_json_WorkGroup',
	type: 'object',
	required: ['Name', 'Works'],
	properties: [
		new OA\Property(
			property: 'Name',
			type: 'string',
			example: 'AAA運輸区',
		),
		new OA\Property(
			property: 'DBVersion',
			type: 'integer',
			example: 1,
		),
		new OA\Property(
			property: 'Works',
			type: 'array',
			items: new OA\Items(ref: '#/components/schemas/TRViS_json_Work'),
		),
	],
)]
class TRViSJsonWorkGroup extends BaseModel
{
	protected const OAS_PROPERTIES = [
		'Name',
		'DBVersion',
		'Works',
	];
	protected const OAS_REQUIRED = ['Name', 'Works'];
}
