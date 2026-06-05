// E2E spec for anonymous (unauthenticated) read-only access.
//
// PR #33 removed the hard auth gate — unauthenticated users can now browse
// public projects without being redirected to a sign-in form. Write controls
// (new project, import, invite key) are hidden; the sign-in affordance moves
// to the topbar "👤" button which opens a modal on demand.
//
// All tests use the raw `page` fixture (no appPage) — intentionally NOT signed in.
import { test, expect } from "./fixtures";

// ─────────────────────────────────────────────────────────────────────────────
// 1. App renders for unauthenticated users — no blocking auth gate
// ─────────────────────────────────────────────────────────────────────────────
test("anonymous: app renders without sign-in gate — project list screen visible", async ({
	page,
}) => {
	await page.goto("/");
	// After auth resolves (user=null), the topbar shows the sign-in button.
	await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
		timeout: 15_000,
	});
	// The sign-in form is NOT shown automatically (it's in a modal, not inlined).
	await expect(page.locator("#auth-email")).toHaveCount(0);
	// The project-list toolbar is rendered — the export button is always visible.
	await expect(page.locator('button:has-text("📤")').first()).toBeVisible({
		timeout: 10_000,
	});
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Write buttons hidden for unauthenticated users
// ─────────────────────────────────────────────────────────────────────────────
test("anonymous: write buttons (new project, import, invite key) are hidden", async ({
	page,
}) => {
	await page.goto("/");
	// Wait for auth state to resolve.
	await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
		timeout: 15_000,
	});
	// Wait for the project list toolbar to render.
	await expect(page.locator('button:has-text("📤")').first()).toBeVisible({
		timeout: 10_000,
	});

	// ＋ 新規プロジェクト button should not be visible.
	await expect(page.locator('button:has-text("＋")')).toHaveCount(0);
	// 📥 インポート button should not be visible.
	await expect(page.locator('button:has-text("📥")')).toHaveCount(0);
	// 🔑 招待キーを使用 button should not be visible.
	await expect(page.locator('button:has-text("🔑")')).toHaveCount(0);
	// card-add affordance should not be present.
	await expect(page.locator(".card-add")).toHaveCount(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Export button visible for unauthenticated users (read-only action)
//    When no public projects are available (empty list), the app shows an alert
//    instead of producing a download — that's the expected behavior.
// ─────────────────────────────────────────────────────────────────────────────
test("anonymous: export button is visible; shows alert when no projects available", async ({
	page,
}) => {
	const alerts: string[] = [];
	page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });

	await page.goto("/");
	await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
		timeout: 15_000,
	});
	await expect(page.locator('button:has-text("📤")').first()).toBeVisible({
		timeout: 10_000,
	});

	// Click export — with no public projects the app shows an alert.
	await page.locator('button:has-text("📤")').first().click();
	await page.waitForTimeout(500);
	expect(
		alerts.some((a) => a.includes("エクスポートできるプロジェクトがありません")),
		"export with empty list should alert"
	).toBe(true);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Sign-in dialog opens via topbar button
// ─────────────────────────────────────────────────────────────────────────────
test("anonymous: sign-in dialog opens via topbar button and closes on cancel", async ({
	page,
}) => {
	await page.goto("/");
	await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
		timeout: 15_000,
	});

	// Click the sign-in button.
	await page.locator('.topbar-btn[title*="サインイン"]').click();

	// The sign-in form should now be visible in a modal.
	await expect(page.locator("#auth-email")).toBeVisible({ timeout: 5_000 });
	await expect(page.locator("#auth-password")).toBeVisible();

	// Close the modal (✕ button).
	await page.locator('.modal button:has-text("✕")').click();
	await expect(page.locator("#auth-email")).toHaveCount(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. After signing in, write buttons appear (isAuthenticated flips true)
// ─────────────────────────────────────────────────────────────────────────────
test("anonymous → sign-in: write buttons appear after authentication", async ({
	page,
}) => {
	await page.goto("/");
	await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
		timeout: 15_000,
	});

	// Before sign-in: no write buttons.
	await expect(page.locator('button:has-text("📥")')).toHaveCount(0);
	await expect(page.locator(".card-add")).toHaveCount(0);

	// Sign in.
	await page.locator('.topbar-btn[title*="サインイン"]').click();
	const emailInput = page.locator("#auth-email");
	await emailInput.waitFor({ state: "visible", timeout: 5_000 });

	const { E2E_EMAIL, E2E_PASSWORD } = await import("./credentials");
	await emailInput.fill(E2E_EMAIL);
	await page.locator("#auth-password").fill(E2E_PASSWORD);
	await page.locator('form button[type="submit"]').click();
	await emailInput.waitFor({ state: "detached", timeout: 15_000 });

	// After sign-in: write buttons should be visible.
	await expect(page.locator('button:has-text("📥")').first()).toBeVisible({
		timeout: 10_000,
	});
	await expect(page.locator('button:has-text("🔑")').first()).toBeVisible();
});
