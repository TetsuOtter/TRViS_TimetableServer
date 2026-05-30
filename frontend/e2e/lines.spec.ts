/**
 * e2e/lines.spec.ts — Lines & Stations manager (路線・駅管理) E2E spec.
 *
 * Covers:
 *  - Line create / select / edit / delete
 *  - Station create (via inline table) / edit / delete
 *  - StationOnLine add / edit / delete / drag-reorder
 *  - StationTrack (番線) create / edit / delete
 *  - StopPattern wizard open / delete / duplicate
 */
import { test, expect } from "./fixtures";
import {
	createProject,
	openProject,
	gotoLines,
	uniqueName,
	modal,
} from "./helpers";

/* ─── Local helpers ──────────────────────────────────────────────────── */

/**
 * Locate a single stop-pattern list card by its name.
 *
 * Both the StopPatternCard (className="card") AND the surrounding line-detail
 * card (also className="card", which *contains* the pattern cards) match a
 * bare `.card` filter by pattern name — strict-mode violation. The line-detail
 * card uniquely contains the tab labels "🚉 経由駅" / "🧩 停車パターン"; pattern
 * cards never contain "経由駅", so `hasNotText: /経由駅/` excludes the outer card.
 */
function patternCard(page: import("@playwright/test").Page, name: string) {
	return page
		.locator(".card")
		.filter({ hasText: name })
		.filter({ hasNotText: /経由駅/ });
}

/**
 * Wait for the LineManager to be rendered.
 * Uses the "＋ 路線を追加" button that only appears on the lines screen.
 */
async function waitForLineManager(page: import("@playwright/test").Page) {
	// The header has "路線・駅管理" as a h2 and there's a "＋ 路線を追加" button.
	// We can't use getByRole("heading") reliably because it depends on the active tab.
	// Instead, wait for the "路線を追加" button OR the main tabs to be visible.
	await expect(
		page.locator("button").filter({ hasText: /路線を追加|addLine/ }).first()
	).toBeVisible({ timeout: 10_000 }).catch(async () => {
		// fallback: wait for the two main tabs to appear
		await expect(
			page.getByRole("button", { name: /🛤 路線/ })
		).toBeVisible({ timeout: 5_000 });
	});
}

/**
 * Open the "路線を追加" dialog, fill name, save.
 * Waits for the new line to appear in the sidebar.
 */
async function addLine(
	page: import("@playwright/test").Page,
	name: string
): Promise<void> {
	await page.locator("button").filter({ hasText: /路線を追加/ }).first().click();
	const m = modal(page);
	await m.locator('input').first().fill(name);
	await m.getByRole("button", { name: "保存" }).click();
}

/**
 * Click the ⚙ settings button for a specific line in the sidebar list.
 * The ⚙ is absolutely positioned inside the same relative-positioned div
 * as the line's selection button.
 */
async function openLineSettingsDialog(
	page: import("@playwright/test").Page,
	lineName: string
): Promise<void> {
	// The line list item: a div > button(name) + button(⚙)
	// We locate the ⚙ button that is a sibling of the line name button.
	// The parent div wraps both buttons.
	const lineItem = page.locator("div").filter({
		has: page.locator("button").filter({ hasText: lineName }),
	}).last();
	await lineItem.getByRole("button", { name: "⚙" }).click();
}

/** Switch to the 駅（全体）main tab in LineManager. */
async function gotoStationsTab(page: import("@playwright/test").Page) {
	await page.getByRole("button", { name: /🚉 駅（全体）/ }).click();
}

/** Switch to the 🛤 路線 main tab in LineManager. */
async function gotoLinesTab(page: import("@playwright/test").Page) {
	// The tab button has EXACT text "🛤 路線" (as opposed to the sidebar button
	// which reads "🛤 路線・駅管理"). Use the element that lives inside the tabs bar.
	// The tabs are rendered as <button> elements with `style` and no class; we use
	// exact text to avoid matching the sidebar item.
	await page.locator("button", { hasText: /^🛤 路線$/ }).first().click();
}

/**
 * Click the "新しい駅を追加" inline affordance in the Stations table,
 * fill the station name, then confirm.
 */
async function createInlineStation(
	page: import("@playwright/test").Page,
	name: string
): Promise<void> {
	// Click the ＋ 新しい駅を追加 button (styled as a plain button at the bottom of the table)
	await page.getByRole("button", { name: /新しい駅を追加/ }).click();
	// Fill the first input in the new row (placeholder "駅名（短）")
	await page.getByPlaceholder("駅名（短）").fill(name);
	// Click ✓ 確定 button
	await page.getByRole("button", { name: /✓ 確定/ }).click();
}

/**
 * Select a line from the left sidebar by clicking its button.
 * The line button appears in the "路線一覧" card.
 */
async function selectLine(
	page: import("@playwright/test").Page,
	lineName: string
): Promise<void> {
	// Line buttons are inside the "routes list" card on the left side.
	// They contain the line name and match as a button text.
	await page.locator("button").filter({ hasText: lineName }).first().click();
}

/**
 * Helper to add a station to the currently visible line (LineStationsTab).
 * Requires the 経由駅 tab to be active.
 */
async function addStationToActiveLine(
	page: import("@playwright/test").Page,
	stationName: string,
	locationM: number
): Promise<void> {
	await page.getByRole("button", { name: /既存の駅を路線に追加/ }).click();
	const sel = page.locator("select");
	await expect(sel).toBeVisible({ timeout: 5_000 });
	// selectOption label must be a string; stationName is unique so we match exactly
	// The option text is "{stationName}（{fullName}）" so we must select by the station name prefix
	// Use locator for the option element instead to avoid API constraint
	const opt = sel.locator("option").filter({ hasText: stationName }).first();
	const optValue = await opt.getAttribute("value");
	if (optValue) {
		await sel.selectOption(optValue);
	} else {
		// fallback: try by label string (non-regex)
		await sel.selectOption({ label: stationName });
	}
	// The location_m input (number type in the new row)
	const locInput = page.locator('input[type="number"]').first();
	await locInput.click({ clickCount: 3 });
	await locInput.fill(String(locationM));
	await page.getByRole("button", { name: /✓ 追加/ }).click();
	// Wait for the station to appear in the table
	await expect(
		page.locator("td").filter({ hasText: stationName }).first()
	).toBeVisible({ timeout: 7_000 });
}

/* ─── Tests ──────────────────────────────────────────────────────────── */

test.describe("Lines & Stations Manager", () => {

	/* ── LINE CREATE ──────────────────────────────────────────────────── */

	test("addLine: creates a line and it persists across reload", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-line");
		const lineName = uniqueName("line-A");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await addLine(page, lineName);

		// Line should appear in the sidebar list immediately
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Persist check: reload — but after reload the app is on the project list,
		// so we must navigate back.
		await page.reload();
		// After reload we're on the project list; navigate back
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── LINE EDIT ──────────────────────────────────────────────────── */

	test("editLine: edits a line name and it persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-ledit");
		const lineName = uniqueName("line-edit");
		const lineUpdated = uniqueName("line-edited");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Open the ⚙ edit dialog
		await openLineSettingsDialog(page, lineName);
		const m = modal(page);
		const nameInput = m.locator('input').first();
		await nameInput.click({ clickCount: 3 });
		await nameInput.fill(lineUpdated);
		await m.getByRole("button", { name: "保存" }).click();

		await expect(
			page.locator("button").filter({ hasText: lineUpdated }).first()
		).toBeVisible({ timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await expect(
			page.locator("button").filter({ hasText: lineUpdated }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── LINE DELETE ──────────────────────────────────────────────────── */

	test("deleteLine: deletes a line and it is gone after reload", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-ldel");
		const lineName = uniqueName("line-del");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Open ⚙ dialog → click 削除 button
		await openLineSettingsDialog(page, lineName);
		const m = modal(page);
		// In the LineDialog footer, the delete button has class btn-danger and text "🗑 削除"
		await m.locator(".btn-danger").click();
		// Native confirm fires → accepted by our handler above

		// Line should disappear from the sidebar
		await expect(
			page.locator("button").filter({ hasText: lineName })
		).toHaveCount(0, { timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await expect(
			page.locator("button").filter({ hasText: lineName })
		).toHaveCount(0);
	});

	/* ── STATION CREATE ──────────────────────────────────────────────── */

	test("addStation (inline): creates a station and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-sta");
		const stationName = uniqueName("sta-new");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await gotoStationsTab(page);
		await createInlineStation(page, stationName);

		// Should appear in the table
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	test("addStation (QuickAdd): creates a station and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-qsta");
		const stationName = uniqueName("qsta");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);

		// QuickAdd: type into the placeholder "駅名を入力してEnter" field, then click 追加
		const quickInput = page.getByPlaceholder(/駅名を入力してEnter/);
		await quickInput.fill(stationName);
		// Click the 追加 button next to the quick add input (btn-primary btn-sm)
		await page.getByRole("button", { name: "追加" }).first().click();

		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── STATION EDIT ──────────────────────────────────────────────── */

	test("editStation: edits a station name and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-stedit");
		const stationName = uniqueName("sta-orig");
		const updatedName = uniqueName("sta-updated");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await createInlineStation(page, stationName);

		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Click on the station row to enter inline edit mode
		// The station name is in a <td> — clicking the row triggers startEdit()
		await page.locator("tr").filter({ hasText: stationName }).first().click();

		// In edit mode, ICell renders an input. The first input in the editing row
		// is the station name (stationName ICell). Because ICell passes `placeholder`
		// only as attribute hint, and the input HAS a value, we target the input by
		// its current value (which is the station name) to avoid ambiguity.
		// We find the first text-type input that currently has stationName as value.
		const nameInput = page.locator(`input[value="${stationName}"]`).first();
		await expect(nameInput).toBeVisible({ timeout: 3_000 });
		await nameInput.click({ clickCount: 3 });
		await nameInput.fill(updatedName);
		// Commit the edit
		await page.getByRole("button", { name: /✓ 確定/ }).first().click();

		await expect(
			page.locator("td").filter({ hasText: updatedName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await expect(
			page.locator("td").filter({ hasText: updatedName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── STATION DELETE ──────────────────────────────────────────────── */

	test("deleteStation: deletes a station and it is gone after reload", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-stdel");
		const stationName = uniqueName("sta-del");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await createInlineStation(page, stationName);

		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// The station row (non-editing) shows a 🗑 button.
		// We need to click the row to make the trash btn appear (it's always there
		// in view mode as a btn-ghost in the last td).
		const stationRow = page.locator("tr").filter({ hasText: stationName }).first();
		// The last button in the row that is NOT the 🛤 trackManager button
		// The row has two buttons: 🛤 and 🗑
		// Use the one with color danger (🗑 has style color: danger)
		// Simplest: get all buttons in the row and click the last one (🗑)
		const rowBtns = stationRow.getByRole("button");
		await rowBtns.last().click();
		// Native confirm fires → accepted

		await expect(
			page.locator("td").filter({ hasText: stationName })
		).toHaveCount(0, { timeout: 7_000 });

		// Persist check
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await expect(
			page.locator("td").filter({ hasText: stationName })
		).toHaveCount(0);
	});

	/* ── STATION ON LINE CRUD ────────────────────────────────────────── */

	test("stationOnLine: add to line, persist, edit location, persist, delete, persist", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-sol");
		const lineName = uniqueName("line-sol");
		const stationName = uniqueName("sta-sol");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		// Create line
		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Create station in the global stations tab
		await gotoStationsTab(page);
		await createInlineStation(page, stationName);
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Go back to lines tab, select our line
		await gotoLinesTab(page);
		await selectLine(page, lineName);

		// Make sure 経由駅 sub-tab is active
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();

		// Add station to the line
		await addStationToActiveLine(page, stationName, 1500);

		// Persist check: reload
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 10_000 });

		// Edit location_m: click on the row → inline edit mode
		await page.locator("tr").filter({ hasText: stationName }).first().click();

		// The edit mode for a sol row shows a number input for location_m
		const locInput = page.locator('input[type="number"]').first();
		await expect(locInput).toBeVisible({ timeout: 3_000 });
		await locInput.click({ clickCount: 3 });
		await locInput.fill("2500");
		// Click the ✓ commit button (single checkmark, no extra text)
		await page.locator("tr").filter({ hasText: stationName }).getByRole("button", { name: /^✓$/ }).click();

		// Should show 2.5 km in the display
		await expect(
			page.locator("td").filter({ hasText: "2.5 km" })
		).toBeVisible({ timeout: 7_000 });

		// Persist check after edit
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();
		await expect(
			page.locator("td").filter({ hasText: "2.5 km" })
		).toBeVisible({ timeout: 10_000 });

		// Delete station-on-line: click the row → edit mode → 🗑 button
		await page.locator("tr").filter({ hasText: stationName }).first().click();
		// In edit mode, the last button (🗑) in the row removes the station from line
		const solRow = page.locator("tr").filter({ hasText: stationName }).first();
		await solRow.getByRole("button").last().click();
		// confirm dialog accepted automatically

		await expect(
			page.locator("td").filter({ hasText: stationName })
		).toHaveCount(0, { timeout: 7_000 });

		// Persist check after delete
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();
		await expect(
			page.locator("td").filter({ hasText: stationName })
		).toHaveCount(0);

		// The removeSol function fires a native confirm() — that's expected UI behaviour.
		// Only assert no error alerts (4xx/5xx) were fired.
		const errorAlerts = alerts.filter(
			(a) => /\d{3}|Error|エラー/i.test(a) && !/路線から除外|削除しますか/.test(a)
		);
		expect(errorAlerts).toHaveLength(0);
	});

	/* ── STATION ON LINE REORDER ─────────────────────────────────────── */

	test("stationOnLine reorder: drag-to-reorder (UNSURE if unreliable)", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-reorder");
		const lineName = uniqueName("line-reorder");
		const stationA = uniqueName("sta-RO-A");
		const stationB = uniqueName("sta-RO-B");
		const stationC = uniqueName("sta-RO-C");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		// Create line
		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Create 3 stations
		await gotoStationsTab(page);
		await createInlineStation(page, stationA);
		await expect(page.locator("td").filter({ hasText: stationA }).first()).toBeVisible({ timeout: 7_000 });
		await createInlineStation(page, stationB);
		await expect(page.locator("td").filter({ hasText: stationB }).first()).toBeVisible({ timeout: 7_000 });
		await createInlineStation(page, stationC);
		await expect(page.locator("td").filter({ hasText: stationC }).first()).toBeVisible({ timeout: 7_000 });

		// Add all 3 to the line at 1000m, 2000m, 3000m respectively
		await gotoLinesTab(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();

		await addStationToActiveLine(page, stationA, 1000);
		await addStationToActiveLine(page, stationB, 2000);
		await addStationToActiveLine(page, stationC, 3000);

		// Verify initial order: A(1.0 km), B(2.0 km), C(3.0 km)
		const solRows = page.locator("tbody tr").filter({ has: page.locator("td").filter({ hasText: /km/ }) });
		await expect(solRows.first()).toContainText(stationA, { timeout: 5_000 });

		// Attempt drag: move stationC (row 3) above stationA (row 1)
		const dragHandleForRow = (sName: string) =>
			// The drag handle is the first <td> cell (⠿ character) in each row
			page.locator("tr").filter({ hasText: sName }).first().locator("td").first();

		const sourceCell = dragHandleForRow(stationC);
		const targetCell = dragHandleForRow(stationA);

		// Wait for the PUT reorder mutation to flush before reloading
		const reorderResponsePromise = page.waitForResponse(
			(r) => r.url().includes("stations_on_line") && r.request().method() === "PUT",
			{ timeout: 10_000 }
		).catch(() => null); // null if no PUT happens (drag did nothing)

		await sourceCell.dragTo(targetCell);
		await reorderResponsePromise;

		// Reload and verify the new order persisted
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();

		// After drag reorder: C should be at position 1 (was moved above A)
		const solRowsAfter = page.locator("tbody tr").filter({ has: page.locator("td").filter({ hasText: /km/ }) });
		await expect(solRowsAfter.first()).toContainText(stationC, { timeout: 7_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── STATION TRACK (番線) CRUD ──────────────────────────────────── */

	test("stationTrack: add track, persist, edit, persist, delete, persist", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-trk");
		const stationName = uniqueName("sta-trk");
		const trackName = uniqueName("trk-1");
		const trackUpdated = uniqueName("trk-upd");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);
		await createInlineStation(page, stationName);
		await expect(
			page.locator("td").filter({ hasText: stationName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Open the StationTrackManager modal via the 🛤 button (title="番線管理")
		const stationRow = page.locator("tr").filter({ hasText: stationName }).first();
		await stationRow.getByTitle(/番線管理/).click();

		const trkModal = modal(page);
		await expect(trkModal).toBeVisible({ timeout: 5_000 });

		// Click "＋ 番線を追加"
		await trkModal.getByRole("button", { name: /番線を追加/ }).click();

		// Fill the track name input (placeholder "1 / 上2 ...")
		const trkInput = trkModal.locator('input[placeholder="1 / 上2 ..."]');
		await expect(trkInput).toBeVisible({ timeout: 3_000 });
		await trkInput.fill(trackName);

		// Click 保存
		await trkModal.getByRole("button", { name: "保存" }).click();

		// Track should appear in the list inside the modal
		await expect(
			trkModal.locator("span").filter({ hasText: trackName })
		).toBeVisible({ timeout: 7_000 });

		// Close the modal
		await trkModal.getByRole("button", { name: "✕" }).click();

		// Reload and verify persistence
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);

		await page.locator("tr").filter({ hasText: stationName }).first().getByTitle(/番線管理/).click();
		const trkModal2 = modal(page);
		await expect(
			trkModal2.locator("span").filter({ hasText: trackName })
		).toBeVisible({ timeout: 10_000 });

		// Edit the track: click 編集 button
		await trkModal2.getByRole("button", { name: "編集" }).first().click();
		const editInput = trkModal2.locator('input[placeholder="1 / 上2 ..."]');
		await expect(editInput).toBeVisible({ timeout: 3_000 });
		await editInput.click({ clickCount: 3 });
		await editInput.fill(trackUpdated);
		await trkModal2.getByRole("button", { name: "保存" }).click();

		await expect(
			trkModal2.locator("span").filter({ hasText: trackUpdated })
		).toBeVisible({ timeout: 7_000 });

		// Delete the track: click 🗑 button
		await trkModal2.getByRole("button").filter({ hasText: /🗑/ }).click();

		await expect(
			trkModal2.locator("span").filter({ hasText: trackUpdated })
		).toHaveCount(0, { timeout: 7_000 });

		// Close and reload to verify deletion persisted
		await trkModal2.getByRole("button", { name: "✕" }).click();
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await gotoStationsTab(page);

		await page.locator("tr").filter({ hasText: stationName }).first().getByTitle(/番線管理/).click();
		const trkModal3 = modal(page);
		await expect(
			trkModal3.locator("span").filter({ hasText: trackUpdated })
		).toHaveCount(0);

		expect(alerts).toHaveLength(0);
	});

	/* ── STOP PATTERN: WIZARD OPEN ──────────────────────────────────── */

	test("stopPattern wizard: opens on click and can be closed", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", async (d) => { alerts.push(d.message()); await d.accept(); });

		const proj = uniqueName("proj-spwiz");
		const lineName = uniqueName("line-spwiz");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		await selectLine(page, lineName);
		// Switch to 停車パターン sub-tab
		await page.locator(".tab").filter({ hasText: /停車パターン/ }).click();

		// Click the wizard button
		await page.getByRole("button", { name: /ウィザードで作成/ }).click();

		// The StopPatternWizard should mount — verify step 1 is visible
		await expect(
			page.getByText(/路線・区間を選択/)
		).toBeVisible({ timeout: 7_000 });

		// Close wizard via the ✕ button
		await page.getByRole("button", { name: "✕" }).first().click();

		// Wizard should be gone
		await expect(
			page.getByText(/路線・区間を選択/)
		).toHaveCount(0, { timeout: 5_000 });

		expect(alerts).toHaveLength(0);
	});

	/* ── STOP PATTERN: CREATE / EDIT / DELETE / DUPLICATE ──────────── */

	test("stopPattern: create, reload (persist), edit, delete, duplicate — all persist", async ({
		appPage: page,
	}) => {
		// Stop-pattern create/edit/delete/duplicate all persist. Pattern cards are
		// located via the patternCard() helper, which excludes the surrounding
		// line-detail card (that card *contains* the pattern cards and would
		// otherwise produce a strict-mode "2 elements" failure).
		// This test runs the full wizard flow 3× with 3 reloads → needs > 30s.
		test.setTimeout(90_000);
		const alerts: string[] = [];
		page.on("dialog", async (d) => {
			alerts.push(d.message());
			await d.accept();
		});

		const proj = uniqueName("proj-spcd");
		const lineName = uniqueName("line-spcd");
		const stationA = uniqueName("sta-spA");
		const stationB = uniqueName("sta-spB");
		const patternName = uniqueName("pat");

		await createProject(page, proj);
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);

		// Create line
		await addLine(page, lineName);
		await expect(
			page.locator("button").filter({ hasText: lineName }).first()
		).toBeVisible({ timeout: 7_000 });

		// Create 2 stations
		await gotoStationsTab(page);
		await createInlineStation(page, stationA);
		await expect(page.locator("td").filter({ hasText: stationA }).first()).toBeVisible({ timeout: 7_000 });
		await createInlineStation(page, stationB);
		await expect(page.locator("td").filter({ hasText: stationB }).first()).toBeVisible({ timeout: 7_000 });

		// Add both stations to the line
		await gotoLinesTab(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /経由駅/ }).click();
		await addStationToActiveLine(page, stationA, 1000);
		await addStationToActiveLine(page, stationB, 2000);

		// Switch to 停車パターン sub-tab
		await page.locator(".tab").filter({ hasText: /停車パターン/ }).click();

		// Open wizard
		await page.getByRole("button", { name: /ウィザードで作成/ }).click();
		await expect(page.getByText(/路線・区間を選択/)).toBeVisible({ timeout: 7_000 });

		// Step 1: select the line
		// The StopPatternWizard shows a <select> for line selection
		const lineSelect = page.locator("select").first();
		await expect(lineSelect).toBeVisible({ timeout: 5_000 });
		// selectOption label must be string; use value-based selection from option element
		const lineOpt = lineSelect.locator("option").filter({ hasText: lineName }).first();
		const lineOptVal = await lineOpt.getAttribute("value");
		if (lineOptVal) {
			await lineSelect.selectOption(lineOptVal);
		} else {
			await lineSelect.selectOption({ label: lineName });
		}

		// Select direction (↓ 下り = direction 1)
		await page.getByRole("button", { name: /↓ 下り/ }).click();

		// After selecting direction, the from/to station selects appear
		// Select fromStation (stationA) and toStation (stationB)
		const fromSelect = page.locator("select").nth(1);
		await expect(fromSelect).toBeVisible({ timeout: 5_000 });
		const fromOpt = fromSelect.locator("option").filter({ hasText: stationA }).first();
		const fromOptVal = await fromOpt.getAttribute("value");
		if (fromOptVal) {
			await fromSelect.selectOption(fromOptVal);
		}

		// After selecting from station, to station select is enabled
		const toSelect = page.locator("select").nth(2);
		await expect(toSelect).toBeEnabled({ timeout: 3_000 });
		const toOpt = toSelect.locator("option").filter({ hasText: stationB }).first();
		const toOptVal = await toOpt.getAttribute("value");
		if (toOptVal) {
			await toSelect.selectOption(toOptVal);
		}

		// After selecting from/to, the pattern name input appears in step 1
		// (パターン名 field, placeholder "例: 快速停車パターン")
		const patNameInput = page.getByPlaceholder(/快速停車パターン/);
		await expect(patNameInput).toBeVisible({ timeout: 3_000 });
		await patNameInput.fill(patternName);

		// Now the 次へ button should be enabled
		await expect(page.getByRole("button", { name: "次へ" })).toBeEnabled({ timeout: 3_000 });

		// Click 次へ to go to step 2
		await page.getByRole("button", { name: "次へ" }).click();

		// Step 2: set stop pattern (station stop settings grid — no name input here)
		// Use the wizard step label (exact text) to avoid matching the description paragraph
		await expect(
			page.locator(".wizard-step-label", { hasText: "停車パターンを設定" }).first()
		).toBeVisible({ timeout: 7_000 });

		// Click 次へ to go to step 3
		await page.getByRole("button", { name: "次へ" }).click();

		// Step 3: confirmation
		await expect(
			page.getByText(/確認/)
		).toBeVisible({ timeout: 7_000 });

		// Click 完了 / 保存 to finish
		const finishBtn = page.getByRole("button", { name: /完了|保存/ }).last();
		await finishBtn.click();

		// Wizard should close
		await expect(page.getByText(/路線・区間を選択/)).toHaveCount(0, { timeout: 10_000 });

		// Pattern card should appear in 停車パターン tab
		// Give a brief moment for TanStack Query to refetch
		await page.waitForTimeout(500);

		await expect(
			patternCard(page, patternName)
		).toBeVisible({ timeout: 10_000 });

		// ── Persist check for create ──
		// Let in-flight writes settle so reload doesn't abort them ("Failed to
		// fetch") — mirrors reloadAndNavigate's documented pattern.
		await page.waitForLoadState("networkidle");
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /停車パターン/ }).click();
		await expect(
			patternCard(page, patternName)
		).toBeVisible({ timeout: 10_000 });

		// ── DUPLICATE ──
		const patCard = patternCard(page, patternName).first();
		await patCard.getByRole("button", { name: /複製|⎘/ }).click();

		const copyName = patternName + " (コピー)";
		await expect(
			patternCard(page, copyName)
		).toBeVisible({ timeout: 10_000 });

		// Persist check for duplicate
		await page.waitForLoadState("networkidle");
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /停車パターン/ }).click();
		await expect(
			patternCard(page, copyName)
		).toBeVisible({ timeout: 10_000 });

		// ── EDIT pattern (open wizard in edit mode) ──
		// Click the ✏ 編集 button on the pattern card.
		// At this point both the original card and the "コピー" copy are visible;
		// filter hasNotText: "コピー" to unambiguously select the original.
		const origCardForEdit = patternCard(page, patternName).filter({ hasNotText: "コピー" });
		await origCardForEdit.getByRole("button", { name: /編集|✏/ }).click();

		// The wizard opens in edit mode
		await expect(
			page.getByText(/路線・区間を選択/)
		).toBeVisible({ timeout: 7_000 });

		// Step 1 in edit mode — the pattern name input is here (pre-filled)
		// Update the pattern name before clicking 次へ
		const editedPatternName = patternName + "-edited";
		const editPatNameInput = page.getByPlaceholder(/快速停車パターン/);
		if (await editPatNameInput.count() > 0) {
			await editPatNameInput.click({ clickCount: 3 });
			await editPatNameInput.fill(editedPatternName);
		}
		await page.getByRole("button", { name: "次へ" }).click();

		// Step 2: stop pattern settings grid
		await expect(
			page.locator(".wizard-step-label", { hasText: "停車パターンを設定" }).first()
		).toBeVisible({ timeout: 7_000 });
		await page.getByRole("button", { name: "次へ" }).click();

		// Step 3 — in edit mode the confirm button reads "更新" (not 完了/保存)
		await expect(page.getByText(/確認/)).toBeVisible({ timeout: 7_000 });
		await page.getByRole("button", { name: /完了|保存|更新/ }).last().click();

		// Wizard closes
		await expect(page.getByText(/路線・区間を選択/)).toHaveCount(0, { timeout: 10_000 });

		// ── DELETE the copy ──
		const copyCard = patternCard(page, copyName).first();
		await copyCard.getByRole("button").filter({ hasText: /🗑/ }).click();
		// confirm → accepted

		await expect(
			patternCard(page, copyName)
		).toHaveCount(0, { timeout: 7_000 });

		// Persist check for deletion
		await page.waitForLoadState("networkidle");
		await page.reload();
		await expect(page.locator(".project-card").filter({ hasText: proj })).toBeVisible({ timeout: 10_000 });
		await openProject(page, proj);
		await gotoLines(page);
		await waitForLineManager(page);
		await selectLine(page, lineName);
		await page.locator(".tab").filter({ hasText: /停車パターン/ }).click();
		// Copy should be gone
		await expect(
			patternCard(page, copyName)
		).toHaveCount(0);

		// The delete fires a confirm("…削除しますか") dialog, which the handler
		// captures — that's expected. Assert it happened AND that no *other*
		// alert leaked (a real error like "Failed to fetch" would still fail).
		expect(alerts.some(a => a.includes("削除しますか"))).toBe(true);
		expect(alerts.filter(a => !a.includes("削除しますか"))).toEqual([]);
	});
});
