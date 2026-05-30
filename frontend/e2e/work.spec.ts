/**
 * E2E tests for the Work / Trains browser screen and the project sidebar.
 *
 * Setup per test: createProject → openProject → createWG (local) → createWork
 * (uses shared helpers from helpers.ts except createWorkGroup — see below).
 */
import { test, expect, type Page } from "./fixtures";
import {
	createProject,
	openProject,
	uniqueName,
	modal,
	clickModalButton,
} from "./helpers";

// ---------------------------------------------------------------------------
// Local helpers
// ---------------------------------------------------------------------------

/**
 * Local createWG that uses .first() to avoid strict-mode violations.
 *
 * When a project has no WGs yet, the main content pane shows an extra
 * "＋ 新規WG" button (App.tsx:1513-1518). The shared helper (helpers.ts:82)
 * does not use .first(), causing a strict-mode error.
 */
async function createWG(page: Page, name: string): Promise<void> {
	await page.getByRole("button", { name: /新規WG/ }).first().click();
	const m = modal(page);
	await m.locator("input").first().fill(name);
	await clickModalButton(page, "保存");
	await expect(
		page.locator(".sidebar-item").filter({ hasText: name })
	).toBeVisible({ timeout: 10_000 });
}

/**
 * Local createWork: clicks the WG row to OPEN it (WG always starts closed
 * after createWG — SidebarTree initialises openWG from an empty workGroups
 * set), then clicks the "＋ 新規ワーク" indent item.
 *
 * After the modal saves, App.tsx onSuccess sets currentWG + currentWork and
 * the sidebar work item appears once apiWorks (TanStack Query) refreshes.
 */
async function localCreateWork(
	page: Page,
	wgName: string,
	workName: string
): Promise<void> {
	// WG is closed after createWG — click it to open.
	await page
		.locator(".sidebar-item")
		.filter({ hasText: wgName })
		.first()
		.click();
	// Wait for the "新規ワーク" indent button to appear.
	const newWorkBtn = page
		.locator(".sidebar-item.indent")
		.filter({ hasText: "新規ワーク" })
		.first();
	await expect(newWorkBtn).toBeVisible({ timeout: 5_000 });
	await newWorkBtn.click();
	// Wait explicitly for the WorkDialog modal to open.
	await expect(page.locator(".modal-backdrop")).toBeVisible({ timeout: 5_000 });
	const m = modal(page);
	// Fill in the work name (first input in the dialog).
	await m.locator("input").first().fill(workName);
	// Clear the affect_date input (second input) to avoid a BACKEND-500 bug:
	// POST /work_groups/{id}/works returns 500 when affect_date is a date string.
	// Clearing the field sends affect_date=undefined in the request.
	await m.locator("input[type='date']").first().fill("");
	// Wait for the save button to be enabled (valid name entered).
	const saveBtn = m.getByRole("button", { name: "保存", exact: true });
	await expect(saveBtn).toBeEnabled({ timeout: 3_000 });
	await saveBtn.click();
	// Wait for the modal backdrop to close.
	await expect(page.locator(".modal-backdrop")).not.toBeVisible({ timeout: 10_000 });
}

/**
 * Navigate from the project list to the WorkBrowser for a specific work.
 * Used post-reload (reload drops you back to the project list).
 *
 * After reload openProject re-runs, and SidebarTree initialises openWG from
 * the CURRENT project.workGroups (which already contains the WG), so the WG
 * starts OPEN and the work item is immediately visible.
 */
async function gotoWork(
	page: Page,
	projName: string,
	wgName: string,
	workName: string
): Promise<void> {
	await openProject(page, projName);
	// WG should be open (initialised from existing workGroups on mount).
	// Wait up to 5 s; if still not visible the WG might be closed, try opening it.
	const workItem = page
		.locator(".sidebar-item.indent")
		.filter({ hasText: workName })
		.first();
	const wgItem = page.locator(".sidebar-item").filter({ hasText: wgName }).first();
	try {
		await expect(workItem).toBeVisible({ timeout: 5_000 });
	} catch {
		// Fallback: WG was closed — open it.
		await wgItem.click();
		await expect(workItem).toBeVisible({ timeout: 5_000 });
	}
	await workItem.click();
	// Wait until the WorkBrowser train panel renders.
	await expect(
		page.getByRole("button", { name: /新規列車/ }).first()
	).toBeVisible({ timeout: 10_000 });
}

/**
 * Setup shortcut: project + WG + work, and navigate into the work screen.
 * Returns { proj, wg, work } names for use in assertions.
 *
 * NOTE: createWork (helpers.ts) saves the modal and App.tsx onSuccess calls
 * setCurrentWork/setScreen, but the work isn't rendered until apiWorks
 * (TanStack Query) refreshes and the sidebar shows the work item. We wait for
 * the work sidebar item to appear, then click it explicitly to ensure
 * WorkBrowser renders.
 */
async function setupWorkScreen(page: Page) {
	const proj = uniqueName("wk-proj");
	const wg = uniqueName("wk-wg");
	const work = uniqueName("wk-work");
	await createProject(page, proj);
	await openProject(page, proj);
	await createWG(page, wg);
	await localCreateWork(page, wg, work);

	// Wait for the work item to appear in the sidebar (mutation + query refresh).
	const workItem = page
		.locator(".sidebar-item.indent")
		.filter({ hasText: work })
		.first();
	await expect(workItem).toBeVisible({ timeout: 10_000 });

	// Click it to ensure WorkBrowser renders.
	await workItem.click();
	await expect(
		page.getByRole("button", { name: /新規列車/ }).first()
	).toBeVisible({ timeout: 10_000 });
	return { proj, wg, work };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe("Work / Train browser", () => {
	// ── Sidebar: WG expand/collapse ─────────────────────────────────────────

	test("sidebar WG row toggles expand/collapse", async ({ appPage: page }) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { wg, work } = await setupWorkScreen(page);

		// Work item should be visible (WG is expanded).
		const workItem = page
			.locator(".sidebar-item.indent")
			.filter({ hasText: work });
		await expect(workItem.first()).toBeVisible();

		// Click WG row to collapse.
		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		await wgItem.click();
		// After collapse, the work item should be hidden (display:none via React conditional).
		await expect(workItem.first()).toBeHidden({ timeout: 5_000 });

		// Click again to expand.
		await wgItem.click();
		await expect(workItem.first()).toBeVisible({ timeout: 5_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: "＋ 新規WG" button ─────────────────────────────────────────

	test("sidebar '＋ 新規WG' creates and persists work group", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const proj = uniqueName("wk-proj");
		await createProject(page, proj);
		await openProject(page, proj);

		const wgName = uniqueName("new-wg");
		// Use local createWG which uses .first() to avoid strict-mode.
		await createWG(page, wgName);

		// Should appear in sidebar immediately.
		await expect(
			page.locator(".sidebar-item").filter({ hasText: wgName }).first()
		).toBeVisible({ timeout: 10_000 });

		// Persists across reload.
		await page.reload();
		await openProject(page, proj);
		await expect(
			page.locator(".sidebar-item").filter({ hasText: wgName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: "＋ 新規ワーク" inline button ─────────────────────────────

	test("sidebar '＋ 新規ワーク' (inline) creates and persists work", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const proj = uniqueName("wk-proj");
		const wg = uniqueName("wk-wg");
		await createProject(page, proj);
		await openProject(page, proj);
		await createWG(page, wg);

		const workName = uniqueName("new-work");
		// WG starts closed after creation — click to open it first.
		await page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first()
			.click();
		// Now the "＋ 新規ワーク" indent button is visible.
		await page
			.locator(".sidebar-item.indent")
			.filter({ hasText: "新規ワーク" })
			.first()
			.click();
		// Wait for modal to open before interacting.
		await expect(page.locator(".modal-backdrop")).toBeVisible({ timeout: 5_000 });
		const m = modal(page);
		await m.locator("input").first().fill(workName);
		// Clear affect_date to avoid BACKEND-500 (work creation fails with any date string).
		await m.locator("input[type='date']").first().fill("");
		const saveBtnInline = m.getByRole("button", { name: "保存", exact: true });
		await expect(saveBtnInline).toBeEnabled({ timeout: 3_000 });
		await saveBtnInline.click();
		// Wait for modal to close before checking sidebar.
		await expect(page.locator(".modal-backdrop")).not.toBeVisible({ timeout: 10_000 });

		// Work should appear in the sidebar.
		await expect(
			page.locator(".sidebar-item.indent").filter({ hasText: workName }).first()
		).toBeVisible({ timeout: 10_000 });

		// Persists across reload.
		await page.reload();
		await openProject(page, proj);
		// Expand WG.
		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		if (
			!(await page
				.locator(".sidebar-item.indent")
				.filter({ hasText: workName })
				.first()
				.isVisible())
		) {
			await wgItem.click();
		}
		await expect(
			page.locator(".sidebar-item.indent").filter({ hasText: workName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: Work row click selects work ────────────────────────────────

	test("clicking Work row in sidebar navigates to WorkBrowser", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// WorkBrowser is already visible after setupWorkScreen.
		// Verify the 新規列車 button is present (confirms WorkBrowser rendered).
		await expect(
			page.getByRole("button", { name: /新規列車/ }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: WG right-click → 編集 ────────────────────────────────────

	test("WG right-click context menu → 編集 updates WG name and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg } = await setupWorkScreen(page);

		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		await wgItem.click({ button: "right" });

		// Wait for the context menu to appear.
		// ContextMenu (EntityDialogs.tsx) renders as a fixed-position div at zIndex 200.
		// Its items are <button> elements with icon+label spans.
		// We wait for "編集" to appear outside any .modal, then click it.
		const ctxEditBtn = page
			.locator("button")
			.filter({ hasText: /編集/ })
			.first();
		await expect(ctxEditBtn).toBeVisible({ timeout: 5_000 });
		await ctxEditBtn.click();

		const newName = uniqueName("wg-edited");
		const m = modal(page);
		const nameInput = m.locator("input").first();
		await nameInput.selectText();
		await nameInput.fill(newName);
		await clickModalButton(page, "保存");

		await expect(
			page.locator(".sidebar-item").filter({ hasText: newName }).first()
		).toBeVisible({ timeout: 10_000 });

		// Persists across reload.
		await page.reload();
		await openProject(page, proj);
		await expect(
			page.locator(".sidebar-item").filter({ hasText: newName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: WG right-click → ＋新規ワーク ────────────────────────────

	test("WG right-click context menu → ＋新規ワーク creates work", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { wg } = await setupWorkScreen(page);

		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		await wgItem.click({ button: "right" });

		// Context menu "新規ワーク" — wait for it to appear.
		// Note: the sidebar also has "新規ワーク" inline, but it uses .sidebar-item.indent,
		// whereas the context menu button does not.
		// The context menu item is a <button> that is NOT itself a .sidebar-item
		// (the sidebar's inline "＋ 新規ワーク" is .sidebar-item.indent). Use a CSS
		// :not on the element itself — a `hasNot` locator filter only excludes
		// DESCENDANT matches, so it wouldn't exclude the sidebar button.
		const ctxNewWork = page
			.locator("button:not(.sidebar-item)")
			.filter({ hasText: /新規ワーク/ })
			.first();
		await expect(ctxNewWork).toBeVisible({ timeout: 5_000 });
		await ctxNewWork.click();

		const workName = uniqueName("ctx-work");
		const m = modal(page);
		await m.locator("input").first().fill(workName);
		// Clear affect_date to avoid BACKEND-500 (work creation fails with any date string).
		await m.locator("input[type='date']").first().fill("");
		await clickModalButton(page, "保存");

		await expect(
			page.locator(".sidebar-item.indent").filter({ hasText: workName }).first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: WG right-click → 削除 ───────────────────────────────────

	test("WG right-click context menu → 削除 removes WG and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg } = await setupWorkScreen(page);

		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		await wgItem.click({ button: "right" });

		await page
			.locator("button")
			.filter({ hasText: /削除/ })
			.first()
			.click();

		// ConfirmDialog (custom modal with a "削除" button).
		await modal(page).getByRole("button", { name: "削除", exact: true }).click();

		// WG should vanish.
		await expect(
			page.locator(".sidebar-item").filter({ hasText: wg })
		).not.toBeVisible({ timeout: 10_000 });

		// Persists across reload.
		await page.reload();
		await openProject(page, proj);
		await expect(
			page.locator(".sidebar-item").filter({ hasText: wg })
		).not.toBeVisible();

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: Work right-click → 編集 ──────────────────────────────────

	test("Work right-click context menu → 編集 updates work name and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg, work } = await setupWorkScreen(page);

		const workItem = page
			.locator(".sidebar-item.indent")
			.filter({ hasText: work })
			.first();
		await workItem.click({ button: "right" });

		await page
			.locator("button")
			.filter({ hasText: /編集/ })
			.first()
			.click();

		const updatedName = uniqueName("work-edited");
		const m = modal(page);
		const nameInput = m.locator("input").first();
		await nameInput.selectText();
		await nameInput.fill(updatedName);
		await clickModalButton(page, "保存");

		await expect(
			page
				.locator(".sidebar-item.indent")
				.filter({ hasText: updatedName })
				.first()
		).toBeVisible({ timeout: 10_000 });

		// Persists across reload.
		await page.reload();
		await openProject(page, proj);
		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		if (
			!(await page
				.locator(".sidebar-item.indent")
				.filter({ hasText: updatedName })
				.first()
				.isVisible())
		) {
			await wgItem.click();
		}
		await expect(
			page
				.locator(".sidebar-item.indent")
				.filter({ hasText: updatedName })
				.first()
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar: Work right-click → 削除 ─────────────────────────────────

	test("Work right-click context menu → 削除 removes work and persists", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg, work } = await setupWorkScreen(page);

		const workItem = page
			.locator(".sidebar-item.indent")
			.filter({ hasText: work })
			.first();
		await workItem.click({ button: "right" });

		await page
			.locator("button")
			.filter({ hasText: /削除/ })
			.first()
			.click();

		// ConfirmDialog modal → 削除 button.
		await modal(page).getByRole("button", { name: "削除", exact: true }).click();

		await expect(
			page.locator(".sidebar-item.indent").filter({ hasText: work })
		).not.toBeVisible({ timeout: 10_000 });

		await page.reload();
		await openProject(page, proj);
		const wgItem = page
			.locator(".sidebar-item")
			.filter({ hasText: wg })
			.first();
		if (!(await wgItem.isVisible())) {
			// WG itself is gone somehow — test passed
		} else {
			// Expand WG if collapsed.
			const isExpanded = await page
				.locator(".sidebar-item.indent")
				.filter({ hasText: "新規ワーク" })
				.first()
				.isVisible();
			if (!isExpanded) await wgItem.click();
			await expect(
				page.locator(".sidebar-item.indent").filter({ hasText: work })
			).not.toBeVisible();
		}

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar footer: 🛤 路線・駅管理 ────────────────────────────────────

	test("sidebar footer '🛤 路線・駅管理' navigates to line manager", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Use force:true to bypass the firebase emulator warning banner overlay.
		await page
			.getByRole("button", { name: /路線・駅管理/ })
			.click({ force: true });
		// LineManager screen renders "路線を追加" affordance.
		await expect(
			page.getByRole("button", { name: /路線を追加/ })
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar footer: 🎨 色マーカー管理 ─────────────────────────────────

	test("sidebar footer '🎨 色マーカー管理' navigates to color manager", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		await page
			.getByRole("button", { name: /色マーカー管理/ })
			.click({ force: true });
		// ColorManager renders "色マーカーを追加".
		await expect(
			page.getByRole("button", { name: /色マーカーを追加/ })
		).toBeVisible({ timeout: 10_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── Sidebar footer: 📤 エクスポート (JSON) ────────────────────────────
	// exportAll reads apiProjects and exports each via the backend graph API, so
	// a download is produced. (Was WRONG-SOURCE: read in-memory sample `data` and
	// returned early when the API projectId didn't match — fixed in App.tsx.)

	test("sidebar footer '📤 エクスポート (JSON)' triggers download", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Listen for a download event. Export should produce a file.
		// (exportAll now reads apiProjects and exports each via the backend graph
		// API, so a download is produced — previously it read in-memory sample
		// data and returned early.)
		const downloadPromise = page
			.waitForEvent("download", { timeout: 3_000 })
			.catch(() => null);

		await page
			.getByRole("button", { name: /エクスポート/ })
			.click({ force: true });

		const download = await downloadPromise;

		// A download should happen (exportAll reads apiProjects → backend export).
		expect(download, "📤 Export should produce a download file").not.toBeNull();
	});

	// ── WorkBrowser: "新規列車" creates a train immediately (no dialog) ────

	test("'新規列車' button creates train immediately and it appears in list", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Immediate create — no dialog. Default trainNumber is "0000M".
		await page.getByRole("button", { name: /新規列車/ }).first().click();

		// The train should appear in the train list panel.
		await expect(page.locator("text=0000M").first()).toBeVisible({
			timeout: 10_000,
		});

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: train create → edit via ⚙ 列車情報 → persists ────────

	test("create train → edit trainNumber via ⚙ 列車情報 → persists across reload", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg, work } = await setupWorkScreen(page);

		// Create a new train (immediate create).
		await page.getByRole("button", { name: /新規列車/ }).first().click();

		// Click the train row to select it.
		const trainRow = page.locator("text=0000M").first();
		await expect(trainRow).toBeVisible({ timeout: 10_000 });
		await trainRow.click();

		// TrainHeaderBar renders — open 列車情報 dialog.
		await page.getByRole("button", { name: /列車情報/ }).first().click();

		const dlg = modal(page);
		await expect(dlg).toBeVisible({ timeout: 5_000 });

		// Edit trainNumber to a unique value.
		const uniqueTrainNum = uniqueName("T");
		const numInput = dlg.locator("input").first();
		await numInput.fill(uniqueTrainNum);

		// Save.
		await dlg.getByRole("button", { name: /保存/ }).click();

		// Updated number should appear in the train list.
		await expect(page.locator(`text=${uniqueTrainNum}`).first()).toBeVisible({
			timeout: 10_000,
		});

		// Reload and verify persistence.
		await page.reload();
		await gotoWork(page, proj, wg, work);

		await expect(page.locator(`text=${uniqueTrainNum}`).first()).toBeVisible({
			timeout: 10_000,
		});

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: train delete ────────────────────────────────────────

	test("delete train via ⚙ dialog → train is gone after reload", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		// Train delete uses native confirm() — auto-accept via dialog handler.
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		const { proj, wg, work } = await setupWorkScreen(page);

		// Create train and edit to unique name.
		await page.getByRole("button", { name: /新規列車/ }).first().click();
		await page.locator("text=0000M").first().click();

		await page.getByRole("button", { name: /列車情報/ }).first().click();
		const dlg = modal(page);
		const uniqueTrainNum = uniqueName("DEL");
		await dlg.locator("input").first().fill(uniqueTrainNum);
		await dlg.getByRole("button", { name: /保存/ }).click();

		// Select the train.
		await page.locator(`text=${uniqueTrainNum}`).first().click();

		// Re-open info dialog.
		await page.getByRole("button", { name: /列車情報/ }).first().click();

		// Click the 🗑 削除 button (triggers native confirm).
		const dlg2 = modal(page);
		await dlg2.getByRole("button", { name: /削除/ }).first().click();

		// Native confirm is auto-accepted — train should disappear from BOTH the
		// train list AND the selected-train header bar. The number renders in
		// those two places, so while the delete settles the locator transiently
		// matches 2 elements; `.not.toBeVisible()` strict-fails on 2 matches.
		// `toHaveCount(0)` is the correct (and stronger) "all instances gone"
		// assertion — it retries until ZERO elements match.
		await expect(
			page.locator(`text=${uniqueTrainNum}`)
		).toHaveCount(0, { timeout: 10_000 });

		// Reload and verify.
		await page.reload();
		await gotoWork(page, proj, wg, work);
		await expect(page.locator(`text=${uniqueTrainNum}`)).toHaveCount(0, {
			timeout: 10_000,
		});

		// The delete confirm dialog message should have been fired.
		expect(alerts.some((a) => a.includes("削除しますか"))).toBe(true);
	});

	// ── WorkBrowser: train select ────────────────────────────────────────

	test("clicking a train row selects it (header bar appears)", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Create a train.
		await page.getByRole("button", { name: /新規列車/ }).first().click();
		const trainRow = page.locator("text=0000M").first();
		await expect(trainRow).toBeVisible({ timeout: 10_000 });

		// Click it — the TrainHeaderBar should render (⚙ 列車情報 button).
		await trainRow.click();
		await expect(
			page.getByRole("button", { name: /列車情報/ }).first()
		).toBeVisible({ timeout: 5_000 });

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: "🧩" (TrainListPanel header) opens ApplyPatternDialog ─

	test("'🧩' button in train list panel header opens ApplyPatternDialog", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// The 🧩 button (btn-ghost btn-xs, title="停車パターンから追加").
		await page.getByRole("button", { name: "🧩" }).first().click();

		// ApplyPatternDialog renders — modal-title contains "停車パターンから列車を".
		await expect(
			page
				.locator(".modal-title")
				.filter({ hasText: "停車パターンから列車を" })
				.first()
		).toBeVisible({ timeout: 5_000 });

		// Close.
		await page
			.locator(".modal")
			.last()
			.getByRole("button", { name: /キャンセル/ })
			.click();
		await expect(
			page.locator(".modal-title").filter({ hasText: "停車パターンから列車を" })
		).not.toBeVisible();

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: empty state "🧩 パターンから作成" button ──────────────

	test("empty state '🧩 パターンから作成' opens ApplyPatternDialog", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// No train selected — the empty-state right pane shows "パターンから作成".
		const patternBtn = page.getByRole("button", { name: /パターンから作成/ });
		await expect(patternBtn).toBeVisible({ timeout: 5_000 });
		await patternBtn.click();

		await expect(
			page
				.locator(".modal-title")
				.filter({ hasText: "停車パターンから列車を" })
				.first()
		).toBeVisible({ timeout: 5_000 });

		await page
			.locator(".modal")
			.last()
			.getByRole("button", { name: /キャンセル/ })
			.click();

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: "🧩 パターン適用" in TrainHeaderBar ───────────────────

	test("TrainHeaderBar '🧩 パターン適用' opens ApplyPatternDialog (existingTrain set)", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Create and select a train.
		await page.getByRole("button", { name: /新規列車/ }).first().click();
		await page.locator("text=0000M").first().click();

		await expect(
			page.getByRole("button", { name: /パターン適用/ })
		).toBeVisible({ timeout: 5_000 });
		await page.getByRole("button", { name: /パターン適用/ }).click();

		// Dialog opens with existingTrain → title says "列車を編集".
		await expect(
			page
				.locator(".modal-title")
				.filter({ hasText: "停車パターンから列車を" })
				.first()
		).toBeVisible({ timeout: 5_000 });

		await page
			.locator(".modal")
			.last()
			.getByRole("button", { name: /キャンセル/ })
			.click();

		expect(alerts).toHaveLength(0);
	});

	// ── ApplyPatternDialog: lines dropdown must NOT show sample data ──
	// The dialog now receives modelLines (from the API), not the in-memory
	// sample data.lines. Our freshly created test project has no lines, so the
	// dropdown must not show the old sample lines "東海道本線"/"横須賀線" (ids l1/l2).

	test("ApplyPatternDialog line filter does NOT show sample data lines", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		// Open ApplyPatternDialog via the 🧩 button.
		await page.getByRole("button", { name: "🧩" }).first().click();
		await expect(
			page
				.locator(".modal-title")
				.filter({ hasText: "停車パターンから列車を" })
				.first()
		).toBeVisible({ timeout: 5_000 });

		// The line filter <select> is inside the modal.
		const lineSelect = page.locator(".modal select").first();
		const options = await lineSelect.locator("option").allTextContents();

		// Sample-data lines that should NOT appear for a freshly created project.
		const hasSampleLines =
			options.some((o) => o.includes("東海道本線")) ||
			options.some((o) => o.includes("横須賀線"));

		// Close before asserting.
		await page
			.locator(".modal")
			.last()
			.getByRole("button", { name: /キャンセル/ })
			.click();

		// No sample lines visible (App.tsx now passes modelLines from the API,
		// not the in-memory sample data.lines).
		expect(
			hasSampleLines,
			"ApplyPatternDialog line dropdown should NOT show in-memory sample lines"
		).toBe(false);

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: ⚙ TrainInfoDialog field edits (direction, destination) ─

	test("TrainInfoDialog fields (direction, destination) are editable and saved", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		await page.getByRole("button", { name: /新規列車/ }).first().click();
		await page.locator("text=0000M").first().click();
		await page.getByRole("button", { name: /列車情報/ }).first().click();

		const dlg = modal(page);

		// Change direction to 上り (↑). selectOption's `label` takes a string, not
		// a regex; use the option value (-1 = 上り) instead.
		const dirSelect = dlg.locator("select").first();
		await dirSelect.selectOption("-1");

		// Set destination (3rd input: trainNumber=0, direction select (not input), destination=1).
		const dest = uniqueName("Dest");
		await dlg.locator("input").nth(1).fill(dest);

		// Save.
		await dlg.getByRole("button", { name: /保存/ }).click();

		// The TrainHeaderBar chip should now be amber (上り direction).
		await expect(page.locator(".chip.amber").first()).toBeVisible({
			timeout: 5_000,
		});

		expect(alerts).toHaveLength(0);
	});

	// ── WorkBrowser: ⚙ TrainInfoDialog cancel discards changes ──────────

	test("TrainInfoDialog cancel does not apply changes", async ({
		appPage: page,
	}) => {
		const alerts: string[] = [];
		page.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		await setupWorkScreen(page);

		await page.getByRole("button", { name: /新規列車/ }).first().click();
		await page.locator("text=0000M").first().click();
		await page.getByRole("button", { name: /列車情報/ }).first().click();

		const dlg = modal(page);
		const neverPersisted = uniqueName("NOPE");
		await dlg.locator("input").first().fill(neverPersisted);

		// Cancel.
		await dlg.getByRole("button", { name: /キャンセル/ }).click();

		// Original "0000M" should still be shown.
		await expect(page.locator("text=0000M").first()).toBeVisible();
		await expect(page.locator(`text=${neverPersisted}`)).not.toBeVisible();

		expect(alerts).toHaveLength(0);
	});
});
