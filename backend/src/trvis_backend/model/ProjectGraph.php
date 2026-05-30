<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\model;

use OpenApi\Attributes as OA;

/**
 * ProjectGraph — the export/import envelope for a whole Project graph.
 *
 * This is a FLAT bundle: `project` plus one array per child entity type, each
 * array carrying that entity's normal schema object (the same shape the per-
 * entity GET endpoints return). Import walks the arrays in dependency order
 * and remaps every id server-side, so the envelope deliberately keeps the
 * original (readOnly) ids — they are the remap keys, not authoritative.
 *
 * Attribute-only (no BaseModel): the service builds the response as a plain
 * array of already-serializable models, so there is no runtime instance to
 * carry OAS_PROPERTIES. swagger-php reads the #[OA\Schema] below regardless.
 */
#[OA\Schema(
	schema: 'ProjectGraph',
	type: 'object',
	required: ['project'],
	description: 'Project の全グラフ (エクスポート/インポート用バックアップ)。子エンティティは依存順の配列として平坦に保持され、インポート時に全 id はサーバ側で再採番される。',
	properties: [
		new OA\Property(property: 'project', ref: '#/components/schemas/Project'),
		new OA\Property(property: 'colors', type: 'array', items: new OA\Items(ref: '#/components/schemas/Color')),
		new OA\Property(property: 'stations', type: 'array', items: new OA\Items(ref: '#/components/schemas/Station')),
		new OA\Property(property: 'station_tracks', type: 'array', items: new OA\Items(ref: '#/components/schemas/StationTrack')),
		new OA\Property(property: 'lines', type: 'array', items: new OA\Items(ref: '#/components/schemas/Line')),
		new OA\Property(property: 'stations_on_line', type: 'array', items: new OA\Items(ref: '#/components/schemas/StationOnLine')),
		new OA\Property(property: 'stop_patterns', type: 'array', items: new OA\Items(ref: '#/components/schemas/StopPattern')),
		new OA\Property(property: 'stop_pattern_rows', type: 'array', items: new OA\Items(ref: '#/components/schemas/StopPatternRow')),
		new OA\Property(property: 'work_groups', type: 'array', items: new OA\Items(ref: '#/components/schemas/WorkGroup')),
		new OA\Property(property: 'works', type: 'array', items: new OA\Items(ref: '#/components/schemas/Work')),
		new OA\Property(property: 'trains', type: 'array', items: new OA\Items(ref: '#/components/schemas/Train')),
		new OA\Property(property: 'timetable_rows', type: 'array', items: new OA\Items(ref: '#/components/schemas/TimetableRow')),
	],
)]
final class ProjectGraph
{
}
