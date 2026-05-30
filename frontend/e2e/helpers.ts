// Shared navigation / creation helpers for E2E specs.
//
// Default app language is "ja" (SettingsContext DEFAULTS, no browser
// detection), so all label-based selectors use the Japanese strings from
// src/i18n/strings.ts.
//
// These helpers assume create/open flows work; if one is broken the helper
// throws, which is itself a finding (surface it in the spec, don't swallow).
//
// IMPORTANT for assertions: the app renders from the API (TanStack Query),
// NOT from in-memory sample data. To prove a mutation actually persisted,
// reload the page and assert the entity is still present.
import { expect, type Page } from "@playwright/test";

let counter = 0;
/** Unique, human-readable name so parallel/repeated runs never collide. */
export function uniqueName(prefix: string): string {
	counter += 1;
	// No Date.now() needed for uniqueness within a run; combine perf + counter.
	return `${prefix}-${process.pid}-${counter}-${Math.floor(performance.now())}`;
}

/** The active modal dialog (`.modal` inside a backdrop). */
export function modal(page: Page) {
	return page.locator(".modal").last();
}

/** Click a footer/primary button by its visible label inside the active modal. */
export async function clickModalButton(page: Page, label: string) {
	await modal(page).getByRole("button", { name: label, exact: true }).click();
}

/* ─── Project ─────────────────────────────────────────────────────────── */

export async function createProject(
	page: Page,
	name: string,
	description = ""
): Promise<void> {
	await page
		.getByRole("button", { name: /新規プロジェクト/ })
		.first()
		.click();
	const m = modal(page);
	await m.locator("input").first().fill(name);
	if (description !== "") {
		await m.locator("textarea").first().fill(description);
	}
	await clickModalButton(page, "保存");
	// The list re-renders from the API; the card should appear.
	await expect(
		page.locator(".project-card").filter({ hasText: name })
	).toBeVisible({ timeout: 10_000 });
}

export function projectCard(page: Page, name: string) {
	return page.locator(".project-card").filter({ hasText: name });
}

export async function openProject(page: Page, name: string): Promise<void> {
	// Click the card title (avoid the kebab "⋯" button).
	await projectCard(page, name).locator("h3").click();
	// Sidebar / work screen chrome should appear.
	await expect(page.getByRole("button", { name: /路線・駅管理/ })).toBeVisible({
		timeout: 10_000,
	});
}

/* ─── Sidebar navigation ──────────────────────────────────────────────── */

export async function gotoLines(page: Page): Promise<void> {
	await page.getByRole("button", { name: /路線・駅管理/ }).click();
}

export async function gotoColors(page: Page): Promise<void> {
	await page.getByRole("button", { name: /色マーカー管理/ }).click();
}

/* ─── WorkGroup / Work (sidebar) ──────────────────────────────────────── */

export async function createWorkGroup(page: Page, name: string): Promise<void> {
	await page.getByRole("button", { name: /新規WG/ }).first().click();
	const m = modal(page);
	await m.locator("input").first().fill(name);
	await clickModalButton(page, "保存");
	await expect(
		page.locator(".sidebar-item").filter({ hasText: name })
	).toBeVisible({ timeout: 10_000 });
}

export async function createWork(
	page: Page,
	wgName: string,
	workName: string
): Promise<void> {
	// Expand the WG, then use its "＋ 新規ワーク" affordance.
	const wgItem = page.locator(".sidebar-item").filter({ hasText: wgName });
	await wgItem.first().click(); // toggle open
	await page
		.locator(".sidebar-item.indent")
		.filter({ hasText: "新規ワーク" })
		.first()
		.click();
	const m = modal(page);
	await m.locator("input").first().fill(workName);
	await clickModalButton(page, "保存");
}
