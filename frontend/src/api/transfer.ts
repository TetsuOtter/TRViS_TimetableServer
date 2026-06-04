// Project-graph transfer — thin wrappers over the dedicated backend endpoints.
//
// Export/import are now SERVER-side (GET /projects/{id}/export aggregates the
// whole graph in one read transaction; POST /projects/import recreates it under
// a brand-new Project in one write transaction, remapping every id server-side).
// The client treats the graph envelope as an OPAQUE blob: it never walks the
// tree, never remaps foreign keys, never parses entity fields. This sidesteps
// the openapi-typescript readOnly-id stripping on the request type entirely
// (the export output carries ids that the generated request type would drop) —
// the blob is passed straight through as `unknown`.
//
// Replaces the previous client-side graph walk + fan-out create + old→new id
// remap, all of which is the backend's job now.

import { client, unwrap, unwrapCreated } from "./client";

export const TRANSFER_KIND = "trvis-project-graph";
export const BUNDLE_KIND = "trvis-project-graph-bundle";
// v3: server-side opaque transfer (v2 was the client-side graph walk).
export const TRANSFER_VERSION = 3;

// On-disk single-project file: metadata envelope around the opaque backend
// ProjectGraph. The "export all" path writes BUNDLE_KIND with a `graphs` array.
export type TransferFile = {
	version: number;
	kind: string;
	exportedAt: string;
	graph: unknown;
};

/* ───────────────────────── Export ───────────────────────── */

// Fetch the whole project graph from the backend. Opaque: callers only persist
// the result; they never inspect it.
export async function exportProjectGraph(projectId: string): Promise<unknown> {
	return unwrap(
		client.GET("/projects/{projectId}/export", {
			params: { path: { projectId } },
		})
	);
}

/* ───────────────────────── Import ───────────────────────── */

export type ImportResult = {
	projectId: string;
	name: string;
};

// POST an opaque graph envelope; the backend creates a fresh Project (201) and
// returns it. `graph` is cast to `never` only to satisfy the generated request
// type — the backend reads the raw body, including the readOnly ids the typed
// request shape omits.
export async function importProjectGraph(
	graph: unknown
): Promise<ImportResult> {
	const proj = await unwrapCreated(
		client.POST("/projects/import", { body: graph as never })
	);
	const p = proj as { projects_id: string; name?: string };
	return { projectId: p.projects_id, name: p.name ?? "" };
}

/* ───────────────────────── File parsing ───────────────────────── */

// A bare backend envelope is recognisable by its `project` object.
function isGraph(x: unknown): boolean {
	return (
		typeof x === "object" &&
		x !== null &&
		typeof (x as Record<string, unknown>)["project"] === "object" &&
		(x as Record<string, unknown>)["project"] !== null
	);
}

// Unwrap a single-file metadata envelope (`{ graph }`) to its backend graph;
// a bare backend envelope passes through unchanged.
function unwrapGraph(x: unknown): unknown {
	if (typeof x === "object" && x !== null && "graph" in x) {
		return x.graph;
	}
	return x;
}

// Extract every postable graph from an arbitrary loaded JSON file: a bundle
// (`graphs` array), a single file envelope, or a bare backend envelope.
export function extractGraphs(raw: unknown): unknown[] {
	if (typeof raw !== "object" || raw === null) return [];
	const o = raw as Record<string, unknown>;
	if (o["kind"] === BUNDLE_KIND && Array.isArray(o["graphs"])) {
		return (o["graphs"] as unknown[]).map(unwrapGraph).filter(isGraph);
	}
	const g = unwrapGraph(raw);
	return isGraph(g) ? [g] : [];
}
