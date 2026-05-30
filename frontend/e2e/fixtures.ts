// Shared E2E fixtures + helpers.
//
// `login(page)` drives the real sign-in form against the Firebase Auth
// emulator (the user is provisioned in global-setup). It waits until the
// AuthGate releases (the sign-in form detaches), which is the single
// observable signal that `user !== null`.
//
// The `test` export is the standard Playwright test extended with an
// `appPage` fixture: a page that is already signed in and sitting on the
// project list.
import { test as base, expect, type Page } from "@playwright/test";

import { E2E_EMAIL, E2E_PASSWORD } from "./credentials";

export { expect };

export async function login(
	page: Page,
	email: string = E2E_EMAIL,
	password: string = E2E_PASSWORD
): Promise<void> {
	await page.goto("/");
	// AuthGate shows a loading splash, then the sign-in form.
	const emailInput = page.locator("#auth-email");
	await emailInput.waitFor({ state: "visible", timeout: 15_000 });
	await emailInput.fill(email);
	await page.locator("#auth-password").fill(password);
	await page.locator('form button[type="submit"]').click();
	// AuthGate releases → the sign-in form detaches.
	await emailInput.waitFor({ state: "detached", timeout: 15_000 });
}

export const test = base.extend<{ appPage: Page }>({
	appPage: async ({ page }, use) => {
		// The Firebase Auth emulator injects a fixed-position warning banner at
		// the bottom of the viewport ("Running in emulator mode..."). It overlaps
		// page controls (e.g. the timetable grid's 行を追加 button) and intercepts
		// pointer events, causing click timeouts. It's a test-only artifact, so
		// hide it on every navigation/reload. addInitScript re-runs after reload.
		await page.addInitScript(() => {
			const inject = () => {
				if (document.getElementById("e2e-hide-fb-banner")) return;
				const style = document.createElement("style");
				style.id = "e2e-hide-fb-banner";
				style.textContent =
					".firebase-emulator-warning{display:none !important;}";
				(document.head ?? document.documentElement).appendChild(style);
			};
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", inject);
			} else {
				inject();
			}
		});
		await login(page);
		await use(page);
	},
});
