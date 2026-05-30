// E2E spec for the Projects list screen.
//
// Export/import are now API-backed (transfer.ts → backend export/import
// endpoints): the toolbar "📤 エクスポート" downloads a BUNDLE_KIND bundle of
// every project's graph, the card-menu "JSONとしてエクスポート" downloads a
// single project graph, and "📥 インポート" recreates a project from a v3
// graph file. The tests below exercise that real behaviour.
import * as fs from "fs";

import { test, expect } from "./fixtures";
import {
	createProject,
	projectCard,
	uniqueName,
	modal,
	clickModalButton,
} from "./helpers";

// ─────────────────────────────────────────────────────────────────────────────
// Helpers local to this spec
// ─────────────────────────────────────────────────────────────────────────────

/** Open the kebab context menu for a named project card. */
async function openKebabMenu(page: import("@playwright/test").Page, name: string) {
	const card = projectCard(page, name);
	// The kebab button renders "⋯" text and has title="メニュー".
	// Use getByTitle which matches the title attribute.
	await card.getByTitle("メニュー").click();
}

/** Click a context-menu item by label text. */
async function clickMenuItemByLabel(
	page: import("@playwright/test").Page,
	label: string
) {
	// The context menu is a fixed-position div, not a .modal.
	// Items are buttons with text matching the label.
	await page.locator(`button:has-text("${label}")`).first().click();
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. "＋ 新規プロジェクト" toolbar button
// ─────────────────────────────────────────────────────────────────────────────
test("toolbar ＋新規プロジェクト button — creates card, persists across reload", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-toolbar");

	// Use the helper which clicks the toolbar button and asserts the card appears.
	await createProject(page, name);

	// Verify the card is present before reload.
	await expect(projectCard(page, name)).toBeVisible();

	// Reload and confirm persistence (API-backed).
	await page.reload();
	await expect(projectCard(page, name)).toBeVisible({ timeout: 10_000 });

	expect(alerts, "no backend error alerts").toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. ".card-add" div (also calls onNew)
// ─────────────────────────────────────────────────────────────────────────────
test(".card-add div — creates card, persists across reload", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-card-add");

	// Click the card-add div directly (not the toolbar button).
	await page.locator(".card-add").click();
	const m = modal(page);
	await m.locator("input").first().fill(name);
	await clickModalButton(page, "保存");

	// Card should appear.
	await expect(projectCard(page, name)).toBeVisible({ timeout: 10_000 });

	// Persist check.
	await page.reload();
	await expect(projectCard(page, name)).toBeVisible({ timeout: 10_000 });

	expect(alerts).toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Project card click — opens the project (navigates away from list)
// ─────────────────────────────────────────────────────────────────────────────
test("project card click — opens the project work screen", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-card-click");
	await createProject(page, name);

	// Click the card body (h3 title area to avoid the kebab button).
	await projectCard(page, name).locator("h3").click();

	// After opening a project, the sidebar shows project-management buttons.
	await expect(page.getByRole("button", { name: /路線・駅管理/ })).toBeVisible({
		timeout: 10_000,
	});

	expect(alerts).toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Kebab "⋯" menu → 開く
// ─────────────────────────────────────────────────────────────────────────────
test("kebab menu → 開く — opens the project work screen", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-kebab-open");
	await createProject(page, name);

	await openKebabMenu(page, name);
	await clickMenuItemByLabel(page, "開く");

	await expect(page.getByRole("button", { name: /路線・駅管理/ })).toBeVisible({
		timeout: 10_000,
	});

	expect(alerts).toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Kebab menu → 編集 → save → persists across reload
// ─────────────────────────────────────────────────────────────────────────────
test("kebab menu → 編集 — edits project name, persists across reload", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const origName = uniqueName("proj-edit-before");
	const newName = uniqueName("proj-edit-after");
	await createProject(page, origName);

	await openKebabMenu(page, origName);
	await clickMenuItemByLabel(page, "編集");

	const m = modal(page);
	// Clear and re-fill the name field.
	await m.locator("input").first().clear();
	await m.locator("input").first().fill(newName);
	await clickModalButton(page, "保存");

	// Card with new name should appear.
	await expect(projectCard(page, newName)).toBeVisible({ timeout: 10_000 });

	// Reload and verify persistence.
	await page.reload();
	await expect(projectCard(page, newName)).toBeVisible({ timeout: 10_000 });
	// Old name should be gone.
	await expect(projectCard(page, origName)).toHaveCount(0);

	expect(alerts).toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Right-click (contextmenu) on card → same menu appears
// ─────────────────────────────────────────────────────────────────────────────
test("right-click on card — context menu appears with all items", async ({
	appPage: page,
}) => {
	const name = uniqueName("proj-rightclick");
	await createProject(page, name);

	// Right-click the card body (not the kebab button).
	const card = projectCard(page, name);
	// dispatchEvent on the card to fire contextmenu event.
	await card.dispatchEvent("contextmenu");

	// Verify the expected menu items are visible.
	// Items render as buttons with icon + label in spans.
	await expect(page.locator('button:has-text("開く")')).toBeVisible({
		timeout: 5_000,
	});
	await expect(page.locator('button:has-text("編集")')).toBeVisible();
	await expect(page.locator('button:has-text("JSONとしてエクスポート")')).toBeVisible();
	await expect(page.locator('button:has-text("削除")')).toBeVisible();

	// Close by pressing Escape.
	await page.keyboard.press("Escape");
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Kebab menu → 削除 → ConfirmDialog → card gone after reload
// ─────────────────────────────────────────────────────────────────────────────
test("kebab menu → 削除 → confirm → card gone after reload", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-delete");
	await createProject(page, name);

	await openKebabMenu(page, name);
	await clickMenuItemByLabel(page, "削除");

	// ConfirmDialog should appear (custom modal, NOT native confirm).
	await expect(modal(page)).toBeVisible({ timeout: 5_000 });
	// Click the danger "削除" button inside the modal.
	await clickModalButton(page, "削除");

	// Card should disappear.
	await expect(projectCard(page, name)).toHaveCount(0, { timeout: 10_000 });

	// Reload and confirm it's gone (API-backed).
	await page.reload();
	await expect(projectCard(page, name)).toHaveCount(0, { timeout: 10_000 });

	expect(alerts).toEqual([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Toolbar 📤 エクスポート (export all) — downloads a BUNDLE_KIND bundle that
//    contains every API project's graph. The bundle shape is
//    { version, kind: BUNDLE_KIND, exportedAt, graphs: [ { project: {...}, ...} ] }
//    (App.tsx exportAll + transfer.ts). The previous test asserted the obsolete
//    json.projects[].name shape and timed out (exportAll fan-out via Promise.all
//    over the whole DB). exportAll is now a bounded sequential loop; this test
//    asserts the new bundle shape and that one graph carries our project name.
// ─────────────────────────────────────────────────────────────────────────────
test("toolbar 📤エクスポート — download bundle contains API-created project", async ({
	appPage: page,
}) => {
	// Exporting ALL projects (the dev DB has many) can be slow even when bounded.
	test.setTimeout(120_000);

	const name = uniqueName("proj-export-all");
	await createProject(page, name);

	// Capture the download. Generous timeout — export-all walks every project.
	const [dl] = await Promise.all([
		page.waitForEvent("download", { timeout: 90_000 }),
		page.getByRole("button", { name: /エクスポート/ }).click(),
	]);

	const path = await dl.path();
	expect(path, "download should have a file path").toBeTruthy();
	// eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
	const json = JSON.parse(fs.readFileSync(path!, "utf-8"));

	// New bundle shape.
	// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
	expect(json.kind, "bundle kind").toBe("trvis-project-graph-bundle");
	// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
	expect(Array.isArray(json.graphs), "bundle has graphs array").toBe(true);

	// Our API-created project should appear as one graph (graph.project.name).
	const graphs = (json as { graphs: Array<{ project?: { name?: string } }> })
		.graphs;
	const graphNames = graphs.map((g) => g.project?.name);
	expect(
		graphNames,
		"exported bundle should contain the API-created project"
	).toContain(name);
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. Card-menu "JSONとしてエクスポート" — a download fires.
//    exportProject(pid) now reads apiProjects and calls exportProjectGraph(pid)
//    (App.tsx), so the API project id resolves and a download is produced.
//    (Previously read in-memory sample data → find() undefined → no download.)
// ─────────────────────────────────────────────────────────────────────────────
test("card menu JSONとしてエクスポート — downloads project JSON", async ({
	appPage: page,
}) => {
	const name = uniqueName("proj-export-single");
	await createProject(page, name);

	// Set up a download listener before clicking.
	const dlPromise = page.waitForEvent("download", { timeout: 5_000 }).catch(() => null);

	await openKebabMenu(page, name);
	await clickMenuItemByLabel(page, "JSONとしてエクスポート");

	const dl = await dlPromise;

	// A download should fire containing our project (exportProject now reads
	// apiProjects → exportProjectGraph, so the API id resolves).
	expect(dl, "card export should trigger a download").not.toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// 10. 📥 インポート — importing a valid project-graph JSON adds a card.
//     The importer accepts the new server-side format (transfer.ts:
//     TRANSFER_KIND="trvis-project-graph", v3, shape { version, kind,
//     exportedAt, graph }). The previous test wrote the obsolete v1
//     "trvis-project" format which the importer correctly rejects.
//     Rewritten to produce a REAL graph file by exporting an API-created
//     project via the UI, then re-importing it — the import creates a fresh
//     project with the same name, so a second card with that name appears.
//     (Mirrors export-import.spec.ts's full round-trip, minimally.)
// ─────────────────────────────────────────────────────────────────────────────
test("📥インポート — re-importing an exported project adds a new card", async ({
	appPage: page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	const name = uniqueName("proj-import-roundtrip");
	await createProject(page, name);

	// Export the project via the card kebab menu → produces a valid v3
	// graph file (the only format the importer accepts).
	await openKebabMenu(page, name);
	const [download] = await Promise.all([
		page.waitForEvent("download", { timeout: 10_000 }),
		clickMenuItemByLabel(page, "JSONとしてエクスポート"),
	]);
	const exportPath = await download.path();
	expect(exportPath, "export should produce a file").toBeTruthy();

	// Re-import the exported file via the hidden file input.
	const fileInput = page.locator('input[type="file"][accept*="json"]');
	await fileInput.setInputFiles(exportPath!);

	// Import must not emit a format error.
	await page.waitForTimeout(1_000); // FileReader + React tick
	const formatError = alerts.find((a) => a.includes("未対応"));
	expect(formatError, "import should not emit a format error").toBeUndefined();

	// The import creates a fresh project with the same name, so there are now
	// TWO cards with that name (the original + the imported copy).
	await expect(projectCard(page, name)).toHaveCount(2, { timeout: 15_000 });
});

// ─────────────────────────────────────────────────────────────────────────────
// 11. "再試行" retry button (best-effort: intercept network error, click retry)
// ─────────────────────────────────────────────────────────────────────────────
test("再試行 button — appears on error and retriggers the projects query", async ({
	appPage: page,
}) => {
	// Intercept the projects API to return a 500 error.
	await page.route("**/api/v1/projects**", (route) => {
		void route.fulfill({
			status: 500,
			contentType: "application/json",
			body: JSON.stringify({ message: "simulated server error" }),
		});
	});

	// Reload so the intercepted 500 is seen on the initial fetch.
	await page.reload();

	// The error banner with 再試行 should appear.
	const retryButton = page.getByRole("button", { name: "再試行" });
	await expect(retryButton).toBeVisible({ timeout: 10_000 });

	// Unroute so the retry can succeed.
	await page.unroute("**/api/v1/projects**");

	await retryButton.click();

	// After retry the error banner should disappear and the grid should render.
	await expect(retryButton).toHaveCount(0, { timeout: 10_000 });
	// The card-add affordance is rendered when there's no error.
	await expect(page.locator(".card-add")).toBeVisible({ timeout: 10_000 });
});
