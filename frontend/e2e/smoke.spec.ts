import { test, expect, login } from "./fixtures";

test.describe("smoke", () => {
	// PR #33 removed the hard auth gate — unauthenticated users can now browse
	// the app (public projects). The sign-in form lives in a modal opened via
	// the topbar "👤" button, not on the initial page load.
	test("app renders for unauthenticated users with sign-in affordance", async ({
		page,
	}) => {
		await page.goto("/");
		// Once auth state resolves (user=null), the topbar "sign in" button appears.
		await expect(page.locator('.topbar-btn[title*="サインイン"]')).toBeVisible({
			timeout: 15_000,
		});
		// The sign-in form is NOT shown automatically — it's behind a modal.
		await expect(page.locator("#auth-email")).toHaveCount(0);
	});

	test("can sign in and reach the project list", async ({ page }) => {
		await login(page);
		// After sign-in the "sign in" button disappears (replaced by account button).
		await expect(page.locator('.topbar-btn[title*="サインイン"]')).toHaveCount(0);
		// The page should now contain interactive app content.
		await expect(page.locator("body")).toBeVisible();
	});
});
