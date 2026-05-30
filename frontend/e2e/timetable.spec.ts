/**
 * TimetableGrid E2E spec.
 *
 * Setup chain per-test:
 *   1. createProject → openProject
 *   2. createWorkGroup → createWork  (sidebar)
 *   3. gotoLines → "🚉 駅（全体）" tab → quick-add 2 stations
 *   4. Navigate back to the work → create a train (click ＋ 新規列車) →
 *      click the train in the list to select it (sets currentTrain state)
 *
 * App.tsx has NO router — all state is React state. After page.reload() we
 * must re-navigate the full tree to reach the grid.
 *
 * Drag-reorder: .drag-handle CSS class exists but no draggable attribute is
 * rendered on StationRow → UNIMPLEMENTED.
 *
 * arriveHidden / departureHidden / showHH are model-only fields; they are
 * never written into modelRowToEntityDraft in App.tsx → won't persist → WRONG-SOURCE.
 */

import { test, expect } from "./fixtures";
import {
  uniqueName,
  createProject,
  openProject,
  createWorkGroup,
  gotoLines,
} from "./helpers";

/* ─── local navigation helpers ─────────────────────────────────────────── */

/**
 * Workaround: after openProject with no WGs, the main content area shows
 * an extra "新規WG" button alongside the sidebar one, causing strict-mode
 * violations in helpers.createWorkGroup. Navigate to "lines" first so the
 * main area shows LineManager instead of the empty-state, leaving only the
 * sidebar button visible.
 */
async function safeCreateWorkGroup(
  page: import("@playwright/test").Page,
  wgName: string
) {
  // Switch to lines screen so the main content area no longer shows the
  // duplicate "新規WG" button in the empty-state.
  await gotoLines(page);
  await createWorkGroup(page, wgName);
}

/**
 * Local createWork — identical to helpers.createWork but clears the
 * affectDate field before saving.
 *
 * WHY: The WorkDialog pre-fills affectDate with today's date (a string
 * "YYYY-MM-DD"). The backend POST /work_groups/{id}/works passes that string
 * directly to WorksRepo::insertWork(?DateTimeInterface $affectDate), which
 * throws a TypeError → HTTP 500. Clearing the field makes the body send
 * affect_date: null / omit it, which the backend accepts (nullable column).
 */
async function localCreateWork(
  page: import("@playwright/test").Page,
  wgName: string,
  workName: string
): Promise<void> {
  // WG may be expanded already (after safeCreateWorkGroup calls gotoLines first,
  // then createWorkGroup — which expands the WG). Try clicking "新規ワーク" directly;
  // if not visible, click the WG to expand first.
  const newWorkBtn = page
    .locator(".sidebar-item.indent")
    .filter({ hasText: "新規ワーク" })
    .first();
  const isVisible = await newWorkBtn.isVisible({ timeout: 2_000 }).catch(() => false);
  if (!isVisible) {
    await page.locator(".sidebar-item").filter({ hasText: wgName }).first().click();
    await expect(newWorkBtn).toBeVisible({ timeout: 5_000 });
  }
  await newWorkBtn.click();
  const m = page.locator(".modal").last();
  await expect(m).toBeVisible({ timeout: 5_000 });
  // Fill work name (first input)
  await m.locator("input").first().fill(workName);
  // Clear the date field (second input = affectDate) to avoid backend TypeError
  const dateInput = m.locator("input[type=date]");
  await dateInput.fill("");
  // Save
  await m.getByRole("button", { name: "保存", exact: true }).click();
  await expect(m).not.toBeVisible({ timeout: 6_000 });
  // Wait for the new work to appear in the sidebar. After the backend responds
  // with the new work, mutation.onSuccess fires: setScreen("work") + setCurrentWG +
  // setCurrentWork + invalidateQueries(works). The works query refetches and the
  // sidebar shows the new work. We wait here for that to settle so that:
  //  (a) the mutation onSuccess is complete (no more pending state transitions)
  //  (b) gotoStationsTab won't race with the "setScreen('work')" call
  //
  // NOTE: The app has a useEffect that resets currentWork to null if the newly
  // created work isn't in apiWorks yet (stale cache). We do NOT wait for the work
  // screen / 新規列車 button here because of that race. Instead we just wait for
  // the work to be visible in the sidebar (which confirms onSuccess + query refresh
  // completed), and let callers use gotoWorkFromSidebar to navigate to the work.
  await expect(
    page.locator(".sidebar-item.indent").filter({ hasText: workName }).first()
  ).toBeVisible({ timeout: 15_000 });
}

/** Click the "🚉 駅（全体）" tab inside LineManager. */
async function gotoStationsTab(page: import("@playwright/test").Page) {
  await gotoLines(page);
  // Wait for LineManager to fully mount and tab buttons to be stable.
  // The "路線・駅管理" click triggers a React screen transition; the tab bar
  // may re-render several times while TanStack Query fetches lines/stations.
  // We use waitForSelector with 'visible' state (not just attached) so we
  // don't click a detaching element.
  const tabBtn = page.getByRole("button", { name: /駅（全体）/ });
  await expect(tabBtn).toBeVisible({ timeout: 10_000 });
  await tabBtn.click();
  // Wait for the quick-add bar to be visible
  await expect(
    page.getByPlaceholder(/駅名を入力してEnter/)
  ).toBeVisible({ timeout: 8_000 });
}

/**
 * Add a station via the quick-add bar on the "駅（全体）" tab.
 * Returns the station name.
 */
async function addStation(
  page: import("@playwright/test").Page,
  name: string
) {
  const input = page.getByPlaceholder(/駅名を入力してEnter/);
  await input.fill(name);
  await page.getByRole("button", { name: /^追加$/ }).click();
  // confirm it appears in the table (exact match to avoid matching 'ST' and 'ST駅' both)
  await expect(page.getByRole("cell", { name, exact: true }).first()).toBeVisible({
    timeout: 8_000,
  });
}

/**
 * Navigate to the work browser for a given project/wg/work.
 * Assumes we are already on the project list page (or will reload to get there).
 * Use this ONLY after page.reload() or when you know you're at the project list.
 */
async function gotoWork(
  page: import("@playwright/test").Page,
  projectName: string,
  wgName: string,
  workName: string
) {
  // openProject clicks the project card from the project list
  await openProject(page, projectName);
  // After openProject, SidebarTree initializes openWG with all WG ids,
  // so the WG should already be expanded. But click it if needed.
  const workItem = page
    .locator(".sidebar-item.indent")
    .filter({ hasText: workName })
    .first();
  const workVisible = await workItem.isVisible({ timeout: 2_000 }).catch(() => false);
  if (!workVisible) {
    await page.locator(".sidebar-item").filter({ hasText: wgName }).first().click();
    await expect(workItem).toBeVisible({ timeout: 10_000 });
  }
  // Click the work item
  await workItem.click();
  // Wait until the work content is visible (the train list panel header)
  await expect(page.getByRole("button", { name: /新規列車/ }).first()).toBeVisible({
    timeout: 10_000,
  });
}

/**
 * Navigate back to the work browser from WITHIN a project (after e.g. gotoStationsTab).
 * Uses the sidebar directly (doesn't go back to project list).
 * Strategy: wait up to 10s for the work item to appear (WG should already be expanded
 * because createWork() expands it). If it still isn't visible, check whether the WG
 * is expanded (▾) or collapsed (▸). Only click the WG if it shows ▸ (collapsed).
 * This avoids accidentally COLLAPSING an already-expanded WG.
 */
async function gotoWorkFromSidebar(
  page: import("@playwright/test").Page,
  wgName: string,
  workName: string
) {
  const workItem = page
    .locator(".sidebar-item.indent")
    .filter({ hasText: workName })
    .first();

  // Wait up to 10s for the work item to appear naturally
  // (apiWorks refetch after createWork should settle within this window)
  const isWorkVisible = await workItem.isVisible({ timeout: 10_000 }).catch(() => false);
  if (!isWorkVisible) {
    // Check if the WG button currently shows ▸ (collapsed) vs ▾ (expanded)
    const wgItem = page.locator(".sidebar-item").filter({ hasText: wgName }).first();
    const wgText = await wgItem.textContent().catch(() => "");
    if (wgText?.includes("▸")) {
      // WG is collapsed — expand it
      await wgItem.click();
    }
    // If already ▾ (expanded), the work just hasn't loaded yet — wait more
    await expect(workItem).toBeVisible({ timeout: 15_000 });
  }
  // Click the work
  await workItem.click();
  // Wait until the work content is visible (the train list panel header)
  await expect(page.getByRole("button", { name: /新規列車/ }).first()).toBeVisible({
    timeout: 10_000,
  });
}

/**
 * Create a train via ＋ 新規列車 button and click it in the list to select
 * it (selects it in React state so the timetable grid mounts).
 * Returns the trainNumber shown in the list ("0000M" by default).
 */
async function createAndSelectTrain(
  page: import("@playwright/test").Page
): Promise<string> {
  // Click the top-right "＋ 新規列車" button in the train list panel. The new
  // train (trainNumber "0000M") is auto-selected by WorkBrowser, which mounts
  // the timetable grid — no manual click on the list entry is needed (the old
  // `div ^0000M` click hit a non-clickable container and could hang).
  await page.getByRole("button", { name: /新規列車/ }).first().click();
  // The timetable grid should now be mounted (shows the "行を追加" button)
  await expect(
    page.getByRole("button", { name: /行を追加/ })
  ).toBeVisible({ timeout: 10_000 });
  return "0000M";
}

/**
 * Full re-navigation after page.reload():
 * Reload → project list appears → open project → sidebar → work → select train.
 */
async function reloadAndNavigate(
  page: import("@playwright/test").Page,
  projectName: string,
  wgName: string,
  workName: string,
  // Train number to select after re-navigating. Defaults to "0000M" (the
  // number createAndSelectTrain assigns). Tests that rename the train must
  // pass the new number so this doesn't hang waiting for the old one.
  trainNumber = "0000M"
) {
  // Inline edits fire their save mutation asynchronously; the UI signal the
  // tests wait on (the edit input unmounting) happens BEFORE the PUT completes.
  // Reloading immediately aborts the in-flight write (net::ERR_ABORTED), so the
  // value never persists. Let pending requests settle before navigating away.
  await page.waitForLoadState("networkidle");
  await page.reload();
  // After reload the app boots to the project list (no URL routing)
  await expect(page.locator(".project-card").first()).toBeVisible({ timeout: 15_000 });
  await gotoWork(page, projectName, wgName, workName);
  // Select the train in the list by its number (default "0000M").
  await page
    .locator("div")
    .filter({ hasText: trainNumber })
    .first()
    .click();
  await expect(
    page.getByRole("button", { name: /行を追加/ })
  ).toBeVisible({ timeout: 10_000 });
}

/**
 * Full test setup: project + WG + stations + work → navigate to work browser.
 * Returns an object with the created entity names.
 * Ends on the work browser screen with the "新規列車" button visible.
 *
 * Strategy: create everything, then use page.reload() to get a clean state,
 * then navigate via the project list → sidebar. This avoids complex state
 * management issues with React state and TanStack Query cache timing.
 */
async function setupProjectAndWork(
  page: import("@playwright/test").Page,
  stationNames: string[]
): Promise<{ pName: string; wgName: string; workName: string }> {
  const pName = uniqueName("ttg-p");
  const wgName = uniqueName("ttg-wg");
  const workName = uniqueName("ttg-w");

  await createProject(page, pName);
  await openProject(page, pName);

  // Navigate to lines screen to avoid duplicate "新規WG" button in work empty-state
  await gotoLines(page);
  await createWorkGroup(page, wgName);
  // localCreateWork clears affectDate to avoid backend TypeError (affect_date string vs ?DateTimeInterface)
  await localCreateWork(page, wgName, workName);

  // Add stations if needed
  if (stationNames.length > 0) {
    // Go to stations tab (gotoStationsTab calls gotoLines then clicks the tab)
    await gotoStationsTab(page);
    for (const stName of stationNames) {
      await addStation(page, stName);
    }
  }

  // Reload to get a clean React state, then navigate via project list
  await page.reload();
  await expect(page.locator(".project-card").first()).toBeVisible({ timeout: 15_000 });
  await gotoWork(page, pName, wgName, workName);

  return { pName, wgName, workName };
}

/** Click "行を追加", pick a station from the picker, wait for row to appear. */
async function addRow(
  page: import("@playwright/test").Page,
  stationName: string
) {
  await page.getByRole("button", { name: /行を追加/ }).click();
  // Station picker modal
  await expect(page.locator(".modal").last()).toBeVisible({ timeout: 6_000 });
  await page
    .locator(".modal")
    .last()
    .getByRole("button", { name: stationName })
    .click();
  // Row appears in the grid
  await expect(
    page.locator(".station-name").filter({ hasText: stationName })
  ).toBeVisible({ timeout: 8_000 });
}

/* ─── Tests ─────────────────────────────────────────────────────────────── */

test.describe("TimetableGrid", () => {
  // Each test creates a full project + WG + work + station + train + rows,
  // then reloads to verify persistence. 90 s covers the full round-trip.
  test.setTimeout(90_000);

  /**
   * Shared setup: project + wg + work + 2 stations are created once and
   * reused by every test (each test creates its own train/rows to stay
   * isolated from count assertions).
   *
   * Because Playwright serial workers reset page state (not DB state), and
   * stations are project-scoped, we create them in a beforeAll.
   */

  /* ─── add row ─────────────────────────────────────────────────────────── */

  test("add row via 行を追加 → persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const stName = uniqueName("ST");
    const { pName, wgName, workName } = await setupProjectAndWork(page, [stName]);
    await createAndSelectTrain(page);

    // Add a row
    await addRow(page, stName);

    // Persist check: reload and re-navigate
    await reloadAndNavigate(page, pName, wgName, workName);
    await expect(
      page.locator(".station-name").filter({ hasText: stName })
    ).toBeVisible({ timeout: 10_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── station picker populated from real API ───────────────────────────── */

  test("station picker shows real API stations (not sample data)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-api-p");
    const wgName = uniqueName("ttgrid-api-wg");
    const workName = uniqueName("ttgrid-api-w");
    const stName = uniqueName("API_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);

    await gotoStationsTab(page);
    await addStation(page, stName);

    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);

    // Open the station picker
    await page.getByRole("button", { name: /行を追加/ }).click();
    const pickerModal = page.locator(".modal").last();
    await expect(pickerModal).toBeVisible({ timeout: 6_000 });

    // The real station we created should appear
    await expect(
      pickerModal.getByRole("button", { name: stName })
    ).toBeVisible({ timeout: 6_000 });

    // Sample-data stations (hardcoded in sampleData.ts) should NOT appear
    // because modelProjectStations comes from the API for THIS project
    const sampleStation = pickerModal.getByRole("button", { name: /新大阪|品川/ });
    await expect(sampleStation).not.toBeVisible();

    // Close the picker
    await pickerModal.getByRole("button", { name: /✕/ }).click();
    expect(alerts).toHaveLength(0);
  });

  /* ─── arrive/depart inline edit → persists ─────────────────────────────── */

  test("arrive time inline edit persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-arr-p");
    const wgName = uniqueName("ttgrid-arr-wg");
    const workName = uniqueName("ttgrid-arr-w");
    const stName = uniqueName("ARR_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // Click arrive time cell (first .time-display in the row for this station)
    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.locator(".time-display").first().click();
    // Input appears
    const timeInput = stRow.locator(".time-input").first();
    await expect(timeInput).toBeVisible({ timeout: 5_000 });
    await timeInput.fill("08:30:00");
    await timeInput.press("Tab");

    // Wait for API mutation to complete (input detaches, grid re-renders)
    await expect(stRow.locator(".time-input")).toHaveCount(0, { timeout: 8_000 });

    // Reload and check it persisted
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    // The arrive time should show 08:30 somewhere in the row
    await expect(reloadedRow).toContainText(/08.30/, { timeout: 8_000 });

    expect(alerts).toHaveLength(0);
  });

  test("departure time inline edit persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-dep-p");
    const wgName = uniqueName("ttgrid-dep-wg");
    const workName = uniqueName("ttgrid-dep-w");
    const stName = uniqueName("DEP_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    // Departure cell is the second .time-display in the row
    await stRow.locator(".time-display").nth(1).click();
    const timeInput = stRow.locator(".time-input").first();
    await expect(timeInput).toBeVisible({ timeout: 5_000 });
    await timeInput.fill("09:15:00");
    await timeInput.press("Tab");
    await expect(stRow.locator(".time-input")).toHaveCount(0, { timeout: 8_000 });

    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await expect(reloadedRow).toContainText(/09.15/, { timeout: 8_000 });
    expect(alerts).toHaveLength(0);
  });

  /* ─── 通過 (isPass) toggle ─────────────────────────────────────────────── */

  test("通過 checkbox toggle persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-pass-p");
    const wgName = uniqueName("ttgrid-pass-wg");
    const workName = uniqueName("ttgrid-pass-w");
    const stName = uniqueName("PASS_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    const passCheck = stRow.locator(".toggle-check");
    await expect(passCheck).not.toBeChecked();
    // The checkbox is controlled by row.isPass with no optimistic update: after
    // the click it re-renders from props (still false) until the PUT round-trips
    // and the query refetches. Playwright's .check() expects a synchronous
    // toggle and fails ("did not change its state"); click then await the
    // eventual checked state instead.
    await passCheck.click();
    await expect(passCheck).toBeChecked({ timeout: 8_000 });

    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await expect(reloadedRow.locator(".toggle-check")).toBeChecked({ timeout: 8_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── remarks inline edit → persists ──────────────────────────────────── */

  test("remarks inline edit persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-rem-p");
    const wgName = uniqueName("ttgrid-rem-wg");
    const workName = uniqueName("ttgrid-rem-w");
    const stName = uniqueName("REM_ST");
    const remarkText = uniqueName("注意");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    const remarksInput = stRow.locator(".remarks-input");
    await remarksInput.fill(remarkText);
    await remarksInput.blur();
    await page.waitForTimeout(500);

    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await expect(reloadedRow.locator(".remarks-input")).toHaveValue(remarkText, {
      timeout: 8_000,
    });
    expect(alerts).toHaveLength(0);
  });

  /* ─── row delete ──────────────────────────────────────────────────────── */

  test("row delete button removes row and persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-del-p");
    const wgName = uniqueName("ttgrid-del-wg");
    const workName = uniqueName("ttgrid-del-w");
    const stName = uniqueName("DEL_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // Confirm row is present
    await expect(
      page.locator(".station-name").filter({ hasText: stName })
    ).toBeVisible();

    // Click the ✕ delete button in the row (no confirm dialog – direct mutation)
    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.locator(".row-action-btn.del").click();

    // Row disappears immediately
    await expect(
      page.locator(".station-name").filter({ hasText: stName })
    ).not.toBeVisible({ timeout: 8_000 });

    // Reload check
    await reloadAndNavigate(page, pName, wgName, workName);
    await expect(
      page.locator(".station-name").filter({ hasText: stName })
    ).not.toBeVisible({ timeout: 8_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal (⚙ button) ──────────────────────────────────────────── */

  test("⚙ detail modal opens and saves drive time / remarks / isOperationOnlyStop", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-dtl-p");
    const wgName = uniqueName("ttgrid-dtl-wg");
    const workName = uniqueName("ttgrid-dtl-w");
    const stName = uniqueName("DTL_ST");
    const detailRemark = uniqueName("dtl-remark");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // Open detail modal via the ⚙ button
    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.getByRole("button", { name: /⚙/ }).click();

    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // Set drive time minutes to 5
    const driveTimeMM = detailModal.locator("input[type=number]").first();
    await driveTimeMM.fill("5");

    // Toggle 運転停車 (isOperationOnlyStop). Select by its label, not by index:
    // the modal's checkbox order varies with conditional fields (着/発 非表示,
    // first/last-row extras), so nth(N) is unreliable and hit departureHidden.
    const opStopCheck = detailModal
      .locator("label")
      .filter({ hasText: /運転停車/ })
      .locator("input[type=checkbox]");
    await opStopCheck.check();

    // Set remarks in the detail modal (the BBCode-aware textarea area)
    // The RowDetailModal uses BBCodeField for remarks
    const remarksArea = detailModal.locator("textarea").last();
    await remarksArea.fill(detailRemark);

    // Save
    await detailModal.getByRole("button", { name: /保存/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    // Reopen the detail modal to verify persisted values
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await reloadedRow.getByRole("button", { name: /⚙/ }).click();
    const reloadedModal = page.locator(".modal").last();
    await expect(reloadedModal).toBeVisible({ timeout: 6_000 });

    // Drive time minutes should be 5
    await expect(reloadedModal.locator("input[type=number]").first()).toHaveValue("5", {
      timeout: 6_000,
    });
    // 運転停車 should still be checked
    await expect(
      reloadedModal.locator("label").filter({ hasText: /運転停車/ }).locator("input[type=checkbox]")
    ).toBeChecked({ timeout: 6_000 });
    // Remarks
    await expect(reloadedModal.locator("textarea").last()).toHaveValue(detailRemark, {
      timeout: 6_000,
    });

    expect(alerts).toHaveLength(0);
  });

  test("detail modal double-click on station cell also opens it", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-dbl-p");
    const wgName = uniqueName("ttgrid-dbl-wg");
    const workName = uniqueName("ttgrid-dbl-w");
    const stName = uniqueName("DBL_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // Double-click the station cell
    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.locator(".station-cell").dblclick();
    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // Modal title should contain the station name
    await expect(detailModal.locator(".modal-title")).toContainText(stName);

    // Close
    await detailModal.getByRole("button", { name: /✕/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: hasBracket (first row only) ────────────────────────── */

  test("detail modal shows 車両到着時刻 checkbox only for first row", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-bkt-p");
    const wgName = uniqueName("ttgrid-bkt-wg");
    const workName = uniqueName("ttgrid-bkt-w");
    const st1Name = uniqueName("BKT_ST1");
    const st2Name = uniqueName("BKT_ST2");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, st1Name);
    await addStation(page, st2Name);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, st1Name);
    await addRow(page, st2Name);

    // First row detail: 車両到着時刻 should appear
    const row1 = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st1Name }),
    });
    await row1.getByRole("button", { name: /⚙/ }).click();
    const modal1 = page.locator(".modal").last();
    await expect(modal1.getByText(/車両到着時刻/)).toBeVisible({ timeout: 5_000 });
    await modal1.getByRole("button", { name: /✕/ }).click();
    await expect(modal1).not.toBeVisible({ timeout: 5_000 });

    // Second row detail: 車両到着時刻 should NOT appear
    const row2 = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st2Name }),
    });
    await row2.getByRole("button", { name: /⚙/ }).click();
    const modal2 = page.locator(".modal").last();
    await expect(modal2).toBeVisible({ timeout: 5_000 });
    await expect(modal2.getByText(/車両到着時刻/)).not.toBeVisible();
    await modal2.getByRole("button", { name: /✕/ }).click();
    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: "終着駅にしない" (last row only) ───────────────────── */

  test("detail modal shows 終着駅にしない checkbox only for last row", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-last-p");
    const wgName = uniqueName("ttgrid-last-wg");
    const workName = uniqueName("ttgrid-last-w");
    const st1Name = uniqueName("LAST_ST1");
    const st2Name = uniqueName("LAST_ST2");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, st1Name);
    await addStation(page, st2Name);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, st1Name);
    await addRow(page, st2Name);

    // First row: no "終着駅にしない"
    const row1 = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st1Name }),
    });
    await row1.getByRole("button", { name: /⚙/ }).click();
    const modal1 = page.locator(".modal").last();
    await expect(modal1).toBeVisible({ timeout: 5_000 });
    await expect(modal1.getByText(/終着駅にしない/)).not.toBeVisible();
    await modal1.getByRole("button", { name: /✕/ }).click();
    await expect(modal1).not.toBeVisible();

    // Last row: "終着駅にしない" should appear
    const row2 = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st2Name }),
    });
    await row2.getByRole("button", { name: /⚙/ }).click();
    const modal2 = page.locator(".modal").last();
    await expect(modal2.getByText(/終着駅にしない/)).toBeVisible({ timeout: 5_000 });
    await modal2.getByRole("button", { name: /✕/ }).click();
    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: runInLimit / runOutLimit ────────────────────────────── */

  test("detail modal runInLimit/runOutLimit persist across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-lim-p");
    const wgName = uniqueName("ttgrid-lim-wg");
    const workName = uniqueName("ttgrid-lim-w");
    const stName = uniqueName("LIM_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.getByRole("button", { name: /⚙/ }).click();
    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // runInLimit input: "進入制限" label, number input after it
    const runInInput = detailModal.locator("input[type=number][min='0'][max='999']").first();
    const runOutInput = detailModal.locator("input[type=number][min='0'][max='999']").nth(1);
    await runInInput.fill("80");
    await runOutInput.fill("100");

    await detailModal.getByRole("button", { name: /保存/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    // Reload and recheck
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await reloadedRow.getByRole("button", { name: /⚙/ }).click();
    const reloadedModal = page.locator(".modal").last();
    await expect(reloadedModal).toBeVisible({ timeout: 6_000 });
    await expect(
      reloadedModal.locator("input[type=number][min='0'][max='999']").first()
    ).toHaveValue("80", { timeout: 6_000 });
    await expect(
      reloadedModal.locator("input[type=number][min='0'][max='999']").nth(1)
    ).toHaveValue("100", { timeout: 6_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: workType field ─────────────────────────────────────── */

  test("detail modal workType is NOT persisted (work_type 実装準備中)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-wt-p");
    const wgName = uniqueName("ttgrid-wt-wg");
    const workName = uniqueName("ttgrid-wt-w");
    const stName = uniqueName("WT_ST");
    const workTypeVal = "荷役";

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.getByRole("button", { name: /⚙/ }).click();
    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // workType is the only TEXT input with placeholder "—" (runIn/runOut
    // limits also use placeholder "—" but are type=number) → exclude those.
    const workTypeInput = detailModal.locator("input[placeholder='—']:not([type=number])");
    await workTypeInput.fill(workTypeVal);
    await detailModal.getByRole("button", { name: /保存/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await reloadedRow.getByRole("button", { name: /⚙/ }).click();
    const reloadedModal = page.locator(".modal").last();
    await expect(reloadedModal).toBeVisible({ timeout: 6_000 });
    // work_type is UNIMPLEMENTED by design (実装準備中): the DB column is a
    // TINYINT UNSIGNED, the backend WorkAtStationType enum has only
    // `case none = 0`, and the OpenAPI doc labels it '作業種別 (実装準備中)'.
    // The frontend surfaces it as a free-text input but the value cannot
    // persist (can't store "荷役" in a TINYINT enum). It is OMITTED from the
    // write path (toApiTimetableRow) so it degrades gracefully and the row
    // save still succeeds — exactly like the showHH/arriveHidden model-only
    // fields. See UNIMPLEMENTED.md §3-3.
    await expect(
      reloadedModal.locator("input[placeholder='—']:not([type=number])")
    ).toHaveValue("", { timeout: 6_000 });

    // The save must succeed silently — no error alert (work_type is not sent),
    // matching the other model-only fields.
    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: showHH (model-only → won't persist) ───────────────── */

  test("detail modal showHH radio changes are NOT persisted (model-only field)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-shh-p");
    const wgName = uniqueName("ttgrid-shh-wg");
    const workName = uniqueName("ttgrid-shh-w");
    const stName = uniqueName("SHH_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.getByRole("button", { name: /⚙/ }).click();
    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // Select "常に表示" (showHH=true) radio
    await detailModal.locator("label", { hasText: /常に表示/ }).click();

    await detailModal.getByRole("button", { name: /保存/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    // Reload: showHH should have reverted to "自動" because the field is
    // not part of modelRowToEntityDraft (WRONG-SOURCE / not persisted)
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await reloadedRow.getByRole("button", { name: /⚙/ }).click();
    const reloadedModal = page.locator(".modal").last();
    await expect(reloadedModal).toBeVisible({ timeout: 6_000 });

    // "自動" should be selected (radio is checked), not "常に表示"
    const autoRadio = reloadedModal.locator("input[type=radio][name=showHH]").first();
    await expect(autoRadio).toBeChecked({ timeout: 6_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── detail modal: arriveHidden/departureHidden (model-only → won't persist) */

  test("detail modal arriveHidden checkbox is NOT persisted (model-only field)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-ah-p");
    const wgName = uniqueName("ttgrid-ah-wg");
    const workName = uniqueName("ttgrid-ah-w");
    const stName = uniqueName("AH_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await stRow.getByRole("button", { name: /⚙/ }).click();
    const detailModal = page.locator(".modal").last();
    await expect(detailModal).toBeVisible({ timeout: 6_000 });

    // Check "非表示" for the arrive time (label: 非表示)
    const arriveHiddenCheck = detailModal
      .locator("label", { hasText: /非表示/ })
      .first()
      .locator("input[type=checkbox]");
    await arriveHiddenCheck.check();
    await expect(arriveHiddenCheck).toBeChecked();

    await detailModal.getByRole("button", { name: /保存/ }).click();
    await expect(detailModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    // Reload: 非表示 should have reverted (field not in entity type)
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    await reloadedRow.getByRole("button", { name: /⚙/ }).click();
    const reloadedModal = page.locator(".modal").last();
    await expect(reloadedModal).toBeVisible({ timeout: 6_000 });

    const reloadedArrHidden = reloadedModal
      .locator("label", { hasText: /非表示/ })
      .first()
      .locator("input[type=checkbox]");
    // Should be unchecked after reload (not persisted)
    await expect(reloadedArrHidden).not.toBeChecked({ timeout: 6_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── row count summary ─────────────────────────────────────────────────── */

  test("row count summary in add-row-bar updates correctly", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-cnt-p");
    const wgName = uniqueName("ttgrid-cnt-wg");
    const workName = uniqueName("ttgrid-cnt-w");
    const st1Name = uniqueName("CNT_ST1");
    const st2Name = uniqueName("CNT_ST2");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, st1Name);
    await addStation(page, st2Name);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, st1Name);
    await addRow(page, st2Name);

    // The add-row-bar shows "N 駅 · 通過 M · 運停 P"
    const bar = page.locator(".add-row-bar");
    await expect(bar).toContainText(/2 駅/, { timeout: 6_000 });
    await expect(bar).toContainText(/通過.*0/);

    // Toggle one to pass. The 通過 checkbox is controlled (checked={!!row.isPass})
    // and its onChange fires an async mutation with NO optimistic update, so
    // React immediately re-renders the box to its prior state. Playwright's
    // .check() over-asserts synchronous state change and fails. Use .click()
    // (which doesn't assert synchronous state) then assert the OBSERVABLE
    // outcome after the mutation settles + refetch: the summary count AND the
    // checkbox reflecting isPass=true.
    const row1 = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st1Name }),
    });
    const toggle = row1.locator(".toggle-check");
    await toggle.click();
    await expect(bar).toContainText(/通過.*1/, { timeout: 8_000 });
    await expect(toggle).toBeChecked({ timeout: 8_000 });

    // Persist check: reload and confirm the pass state survived.
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: st1Name }),
    });
    await expect(reloadedRow.locator(".toggle-check")).toBeChecked({ timeout: 8_000 });
    await expect(page.locator(".add-row-bar")).toContainText(/通過.*1/, { timeout: 8_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── drag reorder: UNIMPLEMENTED ──────────────────────────────────────── */

  test("drag-reorder handle is not rendered (UNIMPLEMENTED)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-drag-p");
    const wgName = uniqueName("ttgrid-drag-wg");
    const workName = uniqueName("ttgrid-drag-w");
    const stName = uniqueName("DRAG_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // The .drag-handle CSS class exists but no element with that class is
    // rendered in StationRow → confirm it's absent
    await expect(page.locator(".drag-handle")).toHaveCount(0);

    expect(alerts).toHaveLength(0);
  });

  /* ─── track cell (station track required) ──────────────────────────────── */

  test("track cell shows enabled — button when station has no tracks (lazy fetch)", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-trk-p");
    const wgName = uniqueName("ttgrid-trk-wg");
    const workName = uniqueName("ttgrid-trk-w");
    const stName = uniqueName("TRK_ST");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // By design the track-pick button is ENABLED whenever a station is assigned
    // (stationId !== ""). Tracks are fetched lazily only when the picker opens
    // (useStationTracks(stationId, open && stationId !== "")) to avoid fanning
    // out a per-row tracks query. With no tracks it shows "—" (title "なし").
    const trackBtn = page
      .locator("tr")
      .filter({ has: page.locator(".station-name", { hasText: stName }) })
      .locator(".track-pick");
    await expect(trackBtn).toBeEnabled({ timeout: 5_000 });
    await expect(trackBtn).toHaveText("—");
    await expect(trackBtn).toHaveAttribute("title", "なし");

    // Opening the picker reveals the empty-tracks state. The opened track
    // select is autoFocused (:focus); the row's color cell also renders a
    // select.color-select, so scope to the focused one. With no tracks it has
    // exactly one option (the "— なし —" placeholder).
    await trackBtn.click();
    const trackSelect = page
      .locator("tr")
      .filter({ has: page.locator(".station-name", { hasText: stName }) })
      .locator("select.color-select:focus");
    await expect(trackSelect).toBeVisible({ timeout: 5_000 });
    await expect(trackSelect.locator("option")).toHaveCount(1, { timeout: 5_000 });
    await expect(trackSelect.locator("option")).toHaveText("— なし —");

    expect(alerts).toHaveLength(0);
  });

  /* ─── color cell ────────────────────────────────────────────────────────── */

  test("color cell select persists across reload", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-col-p");
    const wgName = uniqueName("ttgrid-col-wg");
    const workName = uniqueName("ttgrid-col-w");
    const stName = uniqueName("COL_ST");
    const colorName = uniqueName("COL");

    await createProject(page, pName);
    await openProject(page, pName);

    // Create a color via the color manager. The add UI is an INLINE form
    // (ColorManager renders it when editingId === "new"), not a .modal — target
    // the form's name input (placeholder "赤 / Red ...") directly on the page.
    await page.getByRole("button", { name: /色マーカー管理/ }).click();
    await page.getByRole("button", { name: /色マーカーを追加/ }).click();
    const colorNameInput = page.getByPlaceholder("赤 / Red ...");
    await expect(colorNameInput).toBeVisible({ timeout: 6_000 });
    await colorNameInput.fill(colorName);
    await page.getByRole("button", { name: /保存/ }).click();
    await expect(colorNameInput).not.toBeVisible({ timeout: 6_000 });
    // Confirm color appeared in the list
    await expect(page.getByText(colorName)).toBeVisible({ timeout: 8_000 });

    // Now navigate to work
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoStationsTab(page);
    await addStation(page, stName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);
    await addRow(page, stName);

    // Select the color from the color cell select
    const stRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    const colorSelect = stRow.locator(".color-select");
    await colorSelect.selectOption({ label: colorName });
    await page.waitForTimeout(500);

    // Reload and check
    await reloadAndNavigate(page, pName, wgName, workName);
    const reloadedRow = page.locator("tr").filter({
      has: page.locator(".station-name", { hasText: stName }),
    });
    // Assert the selected option's label persisted. (colorName must be passed
    // into the browser context — it's a Node-side variable, not in scope inside
    // evaluate.) Poll until the refetched row re-selects the color.
    await expect
      .poll(
        () =>
          reloadedRow
            .locator(".color-select")
            .evaluate((el: HTMLSelectElement) => el.options[el.selectedIndex]?.text ?? ""),
        { timeout: 8_000 }
      )
      .toBe(colorName);

    expect(alerts).toHaveLength(0);
  });

  /* ─── TrainHeaderBar: 🧩 パターン適用 button ──────────────────────────── */

  test("🧩 パターン適用 button opens ApplyPatternDialog", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-pat-p");
    const wgName = uniqueName("ttgrid-pat-wg");
    const workName = uniqueName("ttgrid-pat-w");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);

    // Click the パターン適用 button in the train header bar
    await page.getByRole("button", { name: /パターン適用/ }).click();

    // ApplyPatternDialog should open. Assert the modal container plus its
    // unambiguous title (.modal-title shows "🧩 停車パターンから列車を作成").
    // The previous /停車パターン|パターン/ text match hit 6 elements
    // (strict-mode violation); scope to the dialog title instead.
    const patternModal = page.locator(".modal").last();
    await expect(patternModal).toBeVisible({ timeout: 6_000 });
    // The button on a selected train opens the dialog in edit mode, so the
    // title reads "…列車を編集" (vs "…作成" when creating a new train).
    await expect(
      patternModal.locator(".modal-title")
    ).toContainText(/停車パターンから列車を(作成|編集)/, { timeout: 6_000 });

    // Close it (the dialog has both a ✕ header button and a キャンセル footer
    // button → scope to the modal and take the first).
    const closeBtn = patternModal
      .getByRole("button", { name: /閉じる|✕|キャンセル/ })
      .first();
    if (await closeBtn.isVisible()) {
      await closeBtn.click();
    }
    expect(alerts).toHaveLength(0);
  });

  /* ─── TrainHeaderBar: ⚙ 列車情報 button ────────────────────────────────── */

  test("⚙ 列車情報 button opens TrainInfoDialog and saves trainNumber", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-ti-p");
    const wgName = uniqueName("ttgrid-ti-wg");
    const workName = uniqueName("ttgrid-ti-w");
    const newTrainNum = uniqueName("T").slice(0, 8);

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);

    // Click ⚙ 列車情報 button
    await page.getByRole("button", { name: /列車情報/ }).click();
    const infoModal = page.locator(".modal").last();
    await expect(infoModal).toBeVisible({ timeout: 6_000 });

    // Change the train number
    const trainNumInput = infoModal.locator("input").first();
    await trainNumInput.fill(newTrainNum);
    await infoModal.getByRole("button", { name: /保存/ }).click();
    await expect(infoModal).not.toBeVisible({ timeout: 6_000 });
    await page.waitForTimeout(500);

    // Reload and check. After the rename the train is no longer "0000M", so
    // select it by its new number.
    await reloadAndNavigate(page, pName, wgName, workName, newTrainNum);
    // The new train number persisted and appears in BOTH the train list and
    // the selected-train header bar (2 elements), so .first() avoids a
    // strict-mode violation while still asserting the rename persisted.
    await expect(page.getByText(newTrainNum).first()).toBeVisible({ timeout: 8_000 });

    expect(alerts).toHaveLength(0);
  });

  /* ─── Picker: empty project shows proper message ────────────────────────── */

  test("station picker shows 'no stations registered' when project has no stations", async ({ appPage: page }) => {
    const alerts: string[] = [];
    page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

    const pName = uniqueName("ttgrid-empty-p");
    const wgName = uniqueName("ttgrid-empty-wg");
    const workName = uniqueName("ttgrid-empty-w");

    await createProject(page, pName);
    await openProject(page, pName);
    await safeCreateWorkGroup(page, wgName);
    await localCreateWork(page, wgName, workName);
    await gotoWorkFromSidebar(page, wgName, workName);
    await createAndSelectTrain(page);

    // Open picker without any stations
    await page.getByRole("button", { name: /行を追加/ }).click();
    const pickerModal = page.locator(".modal").last();
    await expect(pickerModal).toBeVisible({ timeout: 6_000 });

    await expect(
      pickerModal.getByText(/このプロジェクトには駅が登録されていません/)
    ).toBeVisible({ timeout: 5_000 });

    // Close
    await pickerModal.getByRole("button", { name: /✕/ }).click();
    expect(alerts).toHaveLength(0);
  });

});
