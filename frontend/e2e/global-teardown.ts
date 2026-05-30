// Playwright global teardown — delete every project owned by the dedicated E2E
// users after the suite finishes, so the shared dev DB never accumulates test
// artifacts. WHY THIS MATTERS: each spec creates projects via the UI; with no
// cleanup these pile up indefinitely (we found 639 stale ones), and the project
// list's fetchAllPages + un-virtualized render then races the createProject
// helper's card-visibility wait → flaky setup failures unrelated to any
// assertion. Starting each run from a clean slate caps the in-run project count
// at a single page, keeping setup fast. The E2E users are dedicated test
// accounts (e2e-user@example.com / e2e-user2@example.com) with no real data, so
// deleting ALL their projects is safe — it does NOT touch other users' data and
// is NOT a DB wipe. See memory: e2e-createproject-flake-rootcause.
import {
	E2E_EMAIL,
	E2E_PASSWORD,
	E2E_EMAIL_2,
	E2E_PASSWORD_2,
} from "./credentials";

const EMU_HOST = "localhost:9099";
const API_KEY = "e2e-fake-api-key";
// Same surface the tests hit: the Vite dev server (kept up by `webServer`)
// proxies /api → the dockerized backend (:8080).
const API_BASE = "http://localhost:5173/api/v1";
const PAGE_LIMIT = 100;

async function signIn(email: string, password: string): Promise<string | null> {
	const url = `http://${EMU_HOST}/identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=${API_KEY}`;
	try {
		const res = await fetch(url, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ email, password, returnSecureToken: true }),
		});
		if (!res.ok) return null;
		const body = (await res.json()) as { idToken?: string };
		return body.idToken ?? null;
	} catch {
		return null;
	}
}

async function deleteAllProjects(email: string, token: string): Promise<void> {
	const auth = { Authorization: `Bearer ${token}` };
	// Collect every project id across all pages first (deleting while paging
	// would shift later pages).
	const ids: string[] = [];
	for (let p = 1; p <= 1000; p++) {
		const res = await fetch(
			`${API_BASE}/projects?p=${p}&limit=${PAGE_LIMIT}`,
			{ headers: auth }
		);
		if (!res.ok) break;
		const json = (await res.json()) as
			| { data?: Array<{ projects_id?: string }> }
			| Array<{ projects_id?: string }>;
		const items = Array.isArray(json) ? json : (json.data ?? []);
		for (const it of items) {
			if (it.projects_id) ids.push(it.projects_id);
		}
		if (items.length < PAGE_LIMIT) break;
	}
	let deleted = 0;
	for (const id of ids) {
		try {
			const res = await fetch(`${API_BASE}/projects/${id}`, {
				method: "DELETE",
				headers: auth,
			});
			if (res.ok) deleted++;
		} catch {
			// best-effort; teardown must not fail the run
		}
	}
	// eslint-disable-next-line no-console
	console.log(`[e2e teardown] ${email}: deleted ${deleted}/${ids.length} projects`);
}

export default async function globalTeardown(): Promise<void> {
	// Best-effort: never throw — a teardown failure must not mask the actual
	// test result. If the stack is already down, sign-in just returns null.
	for (const [email, password] of [
		[E2E_EMAIL, E2E_PASSWORD],
		[E2E_EMAIL_2, E2E_PASSWORD_2],
	] as const) {
		try {
			const token = await signIn(email, password);
			if (token === null) {
				// eslint-disable-next-line no-console
				console.log(`[e2e teardown] ${email}: skipped (sign-in unavailable)`);
				continue;
			}
			await deleteAllProjects(email, token);
		} catch (e) {
			// Stack unreachable / non-JSON response between the last test and
			// teardown must NOT fail an otherwise-green run. Log and move on.
			// eslint-disable-next-line no-console
			console.log(`[e2e teardown] ${email}: skipped (${String(e)})`);
		}
	}
}
