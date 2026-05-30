import { defineConfig, devices } from "@playwright/test";

// E2E config. Targets the Vite dev server on localhost:5173, which proxies
// /api → http://localhost:8080 (dockerized php backend) and lets the app's
// `hostname === "localhost"` check wire Firebase Auth to the emulator (:9099).
//
// Prereqs (not started by Playwright): the docker backend stack must be up —
//   docker compose up -d php mysql firebase
// The Firebase Auth emulator is seeded with a@a.test / b@a.test (password 0000).
//
// This config lives outside the `tsc` build gate (tsconfig only includes src/)
// and outside ESLint (ignored), so e2e specs never block the merge gate.
export default defineConfig({
	testDir: "./e2e",
	globalSetup: "./e2e/global-setup.ts",
	// Delete the dedicated E2E users' projects after the run so the shared dev DB
	// never accumulates test artifacts (which slow the project-list render and
	// flake the createProject setup helper). See e2e/global-teardown.ts.
	globalTeardown: "./e2e/global-teardown.ts",
	// DB/emulator state is shared across specs; run serially to avoid
	// cross-spec collisions on created entities.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env["CI"],
	retries: 0,
	reporter: [["list"], ["html", { open: "never", outputFolder: "e2e-report" }]],
	timeout: 30_000,
	expect: { timeout: 7_000 },
	use: {
		baseURL: "http://localhost:5173",
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
		video: "retain-on-failure",
	},
	projects: [
		{ name: "chromium", use: { ...devices["Desktop Chrome"] } },
	],
	webServer: {
		command: "yarn dev",
		url: "http://localhost:5173",
		reuseExistingServer: true,
		timeout: 30_000,
	},
});
