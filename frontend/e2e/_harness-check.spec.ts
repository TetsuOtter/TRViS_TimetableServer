import { test, expect } from "./fixtures";
import { createProject, projectCard, uniqueName } from "./helpers";

// Validates the shared harness: create persists across reload (the
// effect-assertion pattern the real specs must follow).
test("created project persists across reload", async ({ appPage: page }) => {
	const name = uniqueName("harness-proj");
	await createProject(page, name);
	await page.reload();
	await expect(projectCard(page, name)).toBeVisible({ timeout: 10_000 });
});
