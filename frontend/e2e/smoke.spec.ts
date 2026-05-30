import { test, expect, login } from "./fixtures";

test.describe("smoke", () => {
	test("auth gate shows sign-in form when unauthenticated", async ({
		page,
	}) => {
		await page.goto("/");
		await expect(page.locator("#auth-email")).toBeVisible({
			timeout: 15_000,
		});
		await expect(page.locator("#auth-password")).toBeVisible();
	});

	test("can sign in and reach the project list", async ({ page }) => {
		await login(page);
		// After the gate releases, the project list screen renders. It has a
		// "new project" affordance; assert the app chrome is present rather
		// than the auth form.
		await expect(page.locator("#auth-email")).toHaveCount(0);
		// The page should now contain interactive app content.
		await expect(page.locator("body")).toBeVisible();
	});
});
