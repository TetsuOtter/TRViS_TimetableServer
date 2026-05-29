import { signOut } from "firebase/auth";
import createClient from "openapi-fetch";

import { auth } from "../firebase/configure";

import type { components, paths } from "./schema";

// Server error envelope (OA schema `ApiErrorData`): { code, message }. On a
// non-2xx response openapi-fetch parses the JSON body into `error` (it always
// matches this schema for our API), so we never re-read the consumed body.
type ApiErrorData = components["schemas"]["ApiErrorData"];

// Error thrown by unwrap(): carries the HTTP status and the server-provided
// code/message so callers (TanStack Query error states, toasts) can show a
// real message instead of an opaque "API 404". `message` is the server text
// when present, else a status fallback.
export class ApiError extends Error {
	readonly status: number;
	readonly code?: number;
	constructor(status: number, body?: ApiErrorData) {
		super(body?.message ?? `API ${status}`);
		this.name = "ApiError";
		this.status = status;
		this.code = body?.code;
	}
}

// New code-first API client: openapi-typescript types + openapi-fetch runtime.
// Lives beside the old `client.ts` (openapi-generator Configuration) during
// the P4.5→P4.6 side-by-side migration. P4.6's final commit deletes the old
// client.ts/instances.ts/adapters.ts and this file becomes `client.ts`.

const rawBase = import.meta.env.VITE_API_BASE_URL ?? "/api/v1";
const baseUrl = rawBase.replace(/\/+$/, "");

export const getCurrentIdToken = async (): Promise<string> => {
	const u = auth.currentUser;
	if (u === null) return "";
	try {
		return await u.getIdToken();
	} catch {
		return "";
	}
};

export const client = createClient<paths>({ baseUrl });

// openapi-fetch resolves to { data, error, response } instead of throwing on
// non-2xx. TanStack Query needs a *thrown* error to enter its error state, so
// every hook funnels its call through unwrap().
//
// LOCKED pilot pattern — every fan-out hook copies this verbatim:
//   useQuery     queryFn:    async () => (await unwrap(client.GET("/x", {}))) ?? []
//   useMutation  mutationFn: () => unwrap(client.POST("/x", { body }))
//
// Dates: the OpenAPI doc types date/date-time fields as `string`; the snake_case
// adapters wrap them with `new Date(...)` (see adapters.ts). Do NOT parse
// here — keep this transport-only.
export async function unwrap<T>(
	p: Promise<{ data?: T; error?: unknown; response: Response }>
): Promise<T> {
	const { data, error, response } = await p;
	if (error !== undefined || !response.ok) {
		// `error` is the parsed ApiErrorData body when the server sent one;
		// fall back to a status-only message otherwise.
		throw new ApiError(response.status, error as ApiErrorData | undefined);
	}
	return data as T;
}

// The bulk-capable create endpoints accept either a single object or an
// array and answer 200+single / 201+array respectively (backend H8a). The
// OpenAPI doc therefore types the create response as `S | S[]`. Every
// frontend create hook sends exactly one object, so it always lands on the
// 200+single branch; normalize the union back to the single element (and
// defensively take [0] if an array ever comes back) so single-item
// adapters keep their precise type.
//
// LOCKED pilot pattern — bulk-capable create hooks use this instead of unwrap:
//   useMutation  mutationFn: () => unwrapCreated(client.POST("/x", { body }))
export async function unwrapCreated<S>(
	p: Promise<{ data?: S | S[]; error?: unknown; response: Response }>
): Promise<S> {
	const v = await unwrap(p);
	return (Array.isArray(v) ? v[0] : v) as S;
}

// List endpoints are server-paginated (query params `p` / `limit`; backend
// default limit is 10, max 100 — see Constants + PagingQueryValidator). A hook
// that GETs without paging silently truncates the list at the default page
// size (the 11th+ row just never arrives). fetchAllPages walks every page
// (limit = PAGE_LIMIT) until a short/empty page is returned, preserving the
// flat-array contract the list hooks expose so no consumer needs to know about
// paging. Proper paginated / infinite-scroll UI is a separate concern; this
// only removes the silent-truncation data loss.
export const PAGE_LIMIT = 100;

// Hard bound so a server that (buggily) keeps returning full pages can never
// loop forever. 1000 pages * 100 = 100k rows aligns with the backend dump cap.
const MAX_PAGES = 1000;

export async function fetchAllPages<T>(
	fetchPage: (p: number, limit: number) => Promise<T[] | undefined>
): Promise<T[]> {
	const all: T[] = [];
	for (let p = 1; p <= MAX_PAGES; p++) {
		const page = (await fetchPage(p, PAGE_LIMIT)) ?? [];
		all.push(...page);
		if (page.length < PAGE_LIMIT) {
			return all;
		}
	}
	console.warn(
		`fetchAllPages: hit MAX_PAGES (${MAX_PAGES}); list may be truncated at ${MAX_PAGES * PAGE_LIMIT} rows`
	);
	return all;
}

client.use({
	onRequest: async ({ request }) => {
		const token = await getCurrentIdToken();
		// Anonymous access is by-design (privilege-filtered, returns 200 []).
		// Never attach an empty Bearer — it would corrupt the anon path.
		if (token !== "") {
			request.headers.set("Authorization", `Bearer ${token}`);
		}
		return request;
	},
	onResponse: async ({ response }) => {
		if (response.status === 401 && auth.currentUser !== null) {
			await signOut(auth);
		}
		return undefined;
	},
});
