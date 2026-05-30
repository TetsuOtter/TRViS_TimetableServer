// Round-trip proof for the live-API export/import (replaces the old in-memory
// sample-data export). Seeds a rich graph via the API, exports it through the
// real UI, imports the downloaded file through the real UI, then verifies via
// the API that the imported project reproduces every entity count — which can
// only hold if the whole graph walk + foreign-key remap is correct (a dangling
// FK would drop a child create and shrink a count).
import { test, expect, type APIRequestContext } from "@playwright/test";

import { test as appTest } from "./fixtures";
import { E2E_EMAIL, E2E_PASSWORD } from "./credentials";
import { uniqueName } from "./helpers";

const API = "http://localhost:8080/api/v1";
const EMU =
	"http://localhost:9099/identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=dummy";

async function idToken(request: APIRequestContext): Promise<string> {
	const r = await request.post(EMU, {
		data: { email: E2E_EMAIL, password: E2E_PASSWORD, returnSecureToken: true },
	});
	return (await r.json()).idToken as string;
}

function api(request: APIRequestContext, tok: string) {
	const h = { Authorization: `Bearer ${tok}`, "Content-Type": "application/json" };
	return {
		async post(path: string, body: unknown): Promise<any> {
			const r = await request.post(`${API}${path}`, { headers: h, data: body });
			if (r.status() >= 300) {
				throw new Error(`POST ${path} -> ${r.status()} ${await r.text()}`);
			}
			const j = await r.json();
			return Array.isArray(j) ? j[0] : j;
		},
		async list(path: string): Promise<any[]> {
			const r = await request.get(`${API}${path}?p=1&limit=100`, { headers: h });
			if (r.status() >= 300) {
				throw new Error(`GET ${path} -> ${r.status()} ${await r.text()}`);
			}
			const j = await r.json();
			return Array.isArray(j) ? j : [];
		},
	};
}

// Count every entity in a project's graph via the API.
async function countGraph(
	request: APIRequestContext,
	tok: string,
	pid: string
): Promise<Record<string, number>> {
	const a = api(request, tok);
	const [lines, stations, colors, stopPatterns, workGroups] = await Promise.all([
		a.list(`/projects/${pid}/lines`),
		a.list(`/projects/${pid}/stations`),
		a.list(`/projects/${pid}/colors`),
		a.list(`/projects/${pid}/stop_patterns`),
		a.list(`/projects/${pid}/work_groups`),
	]);
	let stationsOnLine = 0;
	for (const l of lines) {
		stationsOnLine += (await a.list(`/lines/${l.lines_id}/stations_on_line`)).length;
	}
	let stopPatternRows = 0;
	for (const sp of stopPatterns) {
		stopPatternRows += (await a.list(`/stop_patterns/${sp.stop_patterns_id}/rows`)).length;
	}
	let works = 0;
	let trains = 0;
	let timetableRows = 0;
	for (const wg of workGroups) {
		const ws = await a.list(`/work_groups/${wg.work_groups_id}/works`);
		works += ws.length;
		for (const w of ws) {
			const ts = await a.list(`/works/${w.works_id}/trains`);
			trains += ts.length;
			for (const t of ts) {
				timetableRows += (await a.list(`/trains/${t.trains_id}/timetable_rows`)).length;
			}
		}
	}
	return {
		lines: lines.length,
		projectStations: stations.length,
		colors: colors.length,
		stopPatterns: stopPatterns.length,
		workGroups: workGroups.length,
		stationsOnLine,
		stopPatternRows,
		works,
		trains,
		timetableRows,
	};
}

appTest("export → import reproduces the full project graph", async ({
	appPage: page,
	request,
}) => {
	const tok = await idToken(request);
	const a = api(request, tok);

	// ── Seed a rich source graph via the API ──
	const projName = uniqueName("xport");
	const proj = await a.post("/projects", { name: projName, description: "src" });
	const pid = proj.projects_id;

	const line = await a.post(`/projects/${pid}/lines`, { name: "L1", description: "" });
	const st1 = await a.post(`/projects/${pid}/stations`, { name: "S1", description: "" });
	const st2 = await a.post(`/projects/${pid}/stations`, { name: "S2", description: "" });
	const color = await a.post(`/projects/${pid}/colors`, {
		name: "C1",
		description: "",
		color_8bit: { red: 10, green: 20, blue: 30 },
		color_real: { red: 10 / 255, green: 20 / 255, blue: 30 / 255 },
	});
	await a.post(`/lines/${line.lines_id}/stations_on_line`, {
		lines_id: line.lines_id,
		project_stations_id: st1.stations_id,
		location_m: 0,
	});
	await a.post(`/lines/${line.lines_id}/stations_on_line`, {
		lines_id: line.lines_id,
		project_stations_id: st2.stations_id,
		location_m: 1000,
	});
	const sp = await a.post(`/projects/${pid}/stop_patterns`, {
		name: "P1",
		lines_id: line.lines_id,
	});
	await a.post(`/stop_patterns/${sp.stop_patterns_id}/rows`, [
		{ project_stations_id: st1.stations_id, sort_key: 0 },
		{ project_stations_id: st2.stations_id, sort_key: 1 },
	]);
	const wg = await a.post(`/projects/${pid}/work_groups`, { name: "WG1", description: "" });
	const work = await a.post(`/work_groups/${wg.work_groups_id}/works`, {
		name: "WK1",
		description: "",
	});
	const train = await a.post(`/works/${work.works_id}/trains`, {
		train_number: "1M",
		direction: 1,
		day_count: 0,
		description: "",
	});
	// Timetable row carries FKs (station + color marker) that must be remapped.
	// description is a required field on timetable_rows (as on every entity).
	await a.post(`/trains/${train.trains_id}/timetable_rows`, {
		stations_id: st1.stations_id,
		colors_id_marker: color.colors_id,
		description: "",
		drive_time_mm: 2,
		drive_time_ss: 0,
	});
	await a.post(`/trains/${train.trains_id}/timetable_rows`, {
		stations_id: st2.stations_id,
		description: "",
		drive_time_mm: 3,
		drive_time_ss: 0,
	});

	const sourceCounts = await countGraph(request, tok, pid);
	// Sanity: the seed actually produced a non-trivial graph.
	expect(sourceCounts.timetableRows).toBe(2);
	expect(sourceCounts.stationsOnLine).toBe(2);

	// ── Export via the real UI (project card ⋯ menu → JSONとしてエクスポート) ──
	page.on("dialog", (d) => void d.accept()); // confirm + alert on import
	await page.reload();
	const card = page.locator(".project-card").filter({ hasText: projName });
	await expect(card).toBeVisible({ timeout: 10_000 });
	await card.getByRole("button", { name: "⋯" }).click();
	const [download] = await Promise.all([
		page.waitForEvent("download"),
		page.getByText("JSONとしてエクスポート").click(),
	]);
	const path = await download.path();
	expect(path).toBeTruthy();

	// ── Import the downloaded file via the real UI (hidden file input) ──
	await page.locator('input[type="file"]').setInputFiles(path);

	// Import completes (alert auto-accepted). Wait for a second project with the
	// same name to materialise in the API.
	await expect
		.poll(
			async () =>
				(await a.list("/projects")).filter((p) => p.name === projName).length,
			{ timeout: 20_000 }
		)
		.toBe(2);

	// ── Verify the imported project reproduces every count ──
	const projects = await a.list("/projects");
	const imported = projects.find((p) => p.name === projName && p.projects_id !== pid);
	expect(imported).toBeTruthy();
	const importedCounts = await countGraph(request, tok, imported.projects_id);
	expect(importedCounts).toEqual(sourceCounts);
});
