// Shared E2E fixtures + helpers.
//
// `login(page)` drives the real sign-in form against the Firebase Auth
// emulator (the user is provisioned in global-setup). It waits until the
// auth modal closes, which is the observable signal that `user !== null`.
//
// NOTE: AuthGate no longer hard-blocks unauthenticated users (PR #33).
// The sign-in form is now inside a modal opened via the topbar "👤" button.
// login() clicks that button first, then fills the form.
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
	// AuthGate shows a loading splash, then the app (anonymous access allowed).
	// Once auth state resolves and user is null, the topbar "sign in" button appears.
	const signInButton = page.locator('.topbar-btn[title*="サインイン"]');
	await signInButton.waitFor({ state: "visible", timeout: 15_000 });
	await signInButton.click();
	// Sign-in dialog opens.
	const emailInput = page.locator("#auth-email");
	await emailInput.waitFor({ state: "visible", timeout: 5_000 });
	await emailInput.fill(email);
	await page.locator("#auth-password").fill(password);
	await page.locator('form button[type="submit"]').click();
	// Dialog closes when auth completes — sign-in form detaches.
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
