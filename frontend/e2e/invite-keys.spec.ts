// E2E for the invite-key flow (issue → redeem → privilege), across TWO users.
//
// Model (verified against backend/src/trvis_backend/...):
//   - An InviteKey belongs to a WorkGroup; redeeming it writes the granted
//     privilege to the WorkGroup's *parent Project* (projects_privileges).
//     Project is the privilege root, so a redeemed key makes the whole Project
//     visible to the joining user.
//   - Privilege tiers (InviteKeyPrivilegeType): none < read < write < admin.
//   - createWorkGroupInProject requires `write`; the 招待管理 sidebar item and
//     all invite-key management require `admin`. These are the UI-observable
//     discriminators between tiers.
//
// This is the FIRST cross-user spec in the suite: E2E_EMAIL_2 is provisioned in
// global-setup but unused until now. User A (appPage / E2E_EMAIL) is the project
// owner/admin who issues keys; User B (userB / E2E_EMAIL_2) is the joiner. They
// run in separate browser contexts so their Firebase auth state is independent.
//
// Per AGENT_BRIEF: every assertion is scoped to uniquely-named entities, and the
// gold-standard visibility check is "reload, then assert via the API-backed list".
import { test as base, expect, login } from "./fixtures";
import {
	E2E_EMAIL_2,
	E2E_PASSWORD_2,
} from "./credentials";
import {
	createProject,
	createWorkGroup,
	openProject,
	projectCard,
	uniqueName,
} from "./helpers";

import type { Browser, BrowserContext, Page } from "@playwright/test";

type PrivilegeLevel = "read" | "write" | "admin";

// Re-apply the Firebase emulator banner hide for any context we build by hand
// (the appPage fixture does this for User A; User B's context needs it too).
async function hideFbBanner(page: Page): Promise<void> {
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
}

// Local fixture: User B = a second, independently-authenticated page (separate
// browser context → separate Firebase session) signed in as E2E_EMAIL_2, sitting
// on the project list. Auto-closed after the test.
const test = base.extend<{ userB: Page }>({
	userB: async ({ browser }: { browser: Browser }, use) => {
		const context: BrowserContext = await browser.newContext();
		const page = await context.newPage();
		await hideFbBanner(page);
		await login(page, E2E_EMAIL_2, E2E_PASSWORD_2);
		await use(page);
		await context.close();
	},
});

/**
 * As an admin sitting INSIDE a project (post-openProject) that already has ≥1
 * WorkGroup, navigate to 招待管理 and issue one invite key with the given
 * privilege + description. Returns the key's UUID, captured from the POST 201
 * (the source of truth — not the rendered <code>, which we assert separately).
 */
async function issueInviteKey(
	page: Page,
	privilege: PrivilegeLevel,
	description: string
): Promise<string> {
	// 招待管理 sidebar item is admin-only; its presence is itself a precondition.
	await page.getByRole("button", { name: /招待管理/ }).click();

	// Reveal the create form (the toggle button, NOT the panel <div> title which
	// shares the same text once open).
	await page
		.getByRole("button", { name: /招待キーを作成/ })
		.click();

	await page.getByPlaceholder("例: Aチーム用招待キー").fill(description);

	// The privilege <select> is the one carrying read/write/admin option values
	// (the WG <select>, when present, carries WG ids instead).
	const privSelect = page
		.locator("select")
		.filter({ has: page.locator('option[value="read"]') });
	await privSelect.selectOption(privilege);

	const respPromise = page.waitForResponse(
		(r) =>
			/\/work_groups\/[^/]+\/invite_keys$/.test(r.url()) &&
			r.request().method() === "POST"
	);
	await page.getByRole("button", { name: "保存", exact: true }).click();
	const resp = await respPromise;

	// Bulk-capable create: a single object answers 200 (an array would be 201).
	// The frontend always sends one object, so we expect 200.
	expect(
		[200, 201],
		`createInviteKey should succeed, got ${resp.status()}`
	).toContain(resp.status());
	const body = (await resp.json()) as {
		invite_keys_id?: string;
		privilege_type?: string;
	};
	expect(
		body.invite_keys_id,
		"create response must carry invite_keys_id"
	).toBeTruthy();
	expect(body.privilege_type).toBe(privilege);

	const uuid = body.invite_keys_id as string;

	// The just-created key must render in the admin list (description + UUID).
	// This is the regression guard for the GET /work_groups/{id}/invite_keys
	// off-by-one paging fix — before the fix the first page was dropped and a
	// brand-new key never appeared here.
	await expect(page.getByText(description, { exact: true })).toBeVisible({
		timeout: 10_000,
	});
	await expect(page.getByText(uuid, { exact: true })).toBeVisible();

	return uuid;
}

/**
 * Redeem an invite key as the given (joining) user from the project-list screen.
 * Expects success: asserts the in-dialog confirmation, then closes the dialog.
 */
async function redeemInviteKey(page: Page, uuid: string): Promise<void> {
	await page.getByRole("button", { name: /招待キーを使用/ }).click();
	const dialog = page.locator(".modal").last();
	await dialog
		.getByPlaceholder("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx")
		.fill(uuid);
	await dialog.getByRole("button", { name: "参加する", exact: true }).click();
	// Real effect of a successful redeem: the join-confirmation message.
	await expect(dialog.getByText(/プロジェクトに参加しました/)).toBeVisible({
		timeout: 10_000,
	});
	await dialog.getByRole("button", { name: "閉じる", exact: true }).click();
}

/**
 * Full admin-side setup: User A creates a fresh project + one WorkGroup, then
 * issues an invite key of the requested tier. Returns { projectName, uuid }.
 */
async function setupProjectWithKey(
	adminPage: Page,
	privilege: PrivilegeLevel
): Promise<{ projectName: string; wgName: string; uuid: string }> {
	const projectName = uniqueName(`invite-${privilege}`);
	const wgName = uniqueName("wg");
	const keyDesc = uniqueName(`key-${privilege}`);

	await createProject(adminPage, projectName);
	await openProject(adminPage, projectName);
	await createWorkGroup(adminPage, wgName);
	const uuid = await issueInviteKey(adminPage, privilege, keyDesc);

	return { projectName, wgName, uuid };
}

test.describe("Invite keys (cross-user)", () => {
	test("read key: invisible before redeem, visible after, read-only privilege", async ({
		appPage: admin,
		userB: member,
	}) => {
		const alerts: string[] = [];
		member.on("dialog", (d) => {
			alerts.push(d.message());
			void d.accept();
		});

		// ── User A issues a read invite key for a fresh project. ──
		const { projectName, wgName, uuid } = await setupProjectWithKey(
			admin,
			"read"
		);

		// ── Before redeeming, User B must NOT see the project. ──
		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);

		// ── User B redeems the key → project becomes visible (after reload). ──
		await redeemInviteKey(member, uuid);
		await member.reload();
		await expect(projectCard(member, projectName)).toBeVisible({
			timeout: 10_000,
		});

		// ── Privilege is "read": project opens, but no admin affordances. ──
		await openProject(member, projectName);
		// 招待管理 is admin-only → must be absent for a read member.
		await expect(
			member.getByRole("button", { name: /招待管理/ })
		).toHaveCount(0);

		// A read member cannot create a WorkGroup: the control exists (not
		// frontend-gated) but the backend rejects with 403, surfaced via alert,
		// and nothing persists. (This is the read-vs-write discriminator.)
		const blockedWg = uniqueName("blocked-wg");
		await member.getByRole("button", { name: /新規WG/ }).first().click();
		const m = member.locator(".modal").last();
		await m.locator("input").first().fill(blockedWg);
		await m.getByRole("button", { name: "保存", exact: true }).click();

		// The create attempt must fail with a 403/permission alert...
		await expect
			.poll(() => alerts.join("\n"), { timeout: 10_000 })
			.toMatch(/403|permission|権限/i);
		// ...and the WorkGroup must not exist (API-backed truth). reload() resets
		// the in-memory project context to the list, so RE-OPEN the project, then
		// assert against the freshly-fetched sidebar. The sentinel (A's WG) must
		// be present — proving the sidebar actually loaded — while the blocked WG
		// is absent (so the count-0 can't be a false pass on an empty screen).
		await member.reload();
		await openProject(member, projectName);
		await expect(
			member.locator(".sidebar-item").filter({ hasText: wgName })
		).toBeVisible({ timeout: 10_000 });
		await expect(
			member.locator(".sidebar-item").filter({ hasText: blockedWg })
		).toHaveCount(0);
	});

	test("write key: visible after redeem, can create a WorkGroup, but no admin tools", async ({
		appPage: admin,
		userB: member,
	}) => {
		const { projectName, uuid } = await setupProjectWithKey(admin, "write");

		// Invisible before; visible after redeem + reload.
		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);
		await redeemInviteKey(member, uuid);
		await member.reload();
		await expect(projectCard(member, projectName)).toBeVisible({
			timeout: 10_000,
		});

		await openProject(member, projectName);
		// write < admin → no 招待管理.
		await expect(
			member.getByRole("button", { name: /招待管理/ })
		).toHaveCount(0);

		// write CAN create a WorkGroup (the read-vs-write discriminator, positive
		// side): createWorkGroup throws if the create 403s / the WG never renders.
		const newWgName = uniqueName("member-wg");
		await createWorkGroup(member, newWgName);
		// Persisted? reload() resets to the project list, so re-open the project
		// and assert the member-created WG via the API-backed sidebar.
		await member.reload();
		await openProject(member, projectName);
		await expect(
			member.locator(".sidebar-item").filter({ hasText: newWgName })
		).toBeVisible({ timeout: 10_000 });
	});

	test("admin key: visible after redeem, admin tools present, can issue own key", async ({
		appPage: admin,
		userB: member,
	}) => {
		const { projectName, uuid } = await setupProjectWithKey(admin, "admin");

		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);
		await redeemInviteKey(member, uuid);
		await member.reload();
		await expect(projectCard(member, projectName)).toBeVisible({
			timeout: 10_000,
		});

		await openProject(member, projectName);
		// admin → 招待管理 sidebar item present.
		await expect(
			member.getByRole("button", { name: /招待管理/ })
		).toBeVisible();

		// The strongest admin-capability proof: the new admin member can issue
		// their OWN invite key end-to-end (create returns 200 with the key).
		const ownKeyDesc = uniqueName("member-issued");
		const ownUuid = await issueInviteKey(member, "read", ownKeyDesc);
		expect(ownUuid).toMatch(
			/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
		);
	});

	test("privilege upgrade: a second (admin) key raises a read member to admin", async ({
		appPage: admin,
		userB: member,
	}) => {
		// User A issues BOTH a read and an admin key for the same project/WG.
		const projectName = uniqueName("invite-upgrade");
		const wgName = uniqueName("wg");
		await createProject(admin, projectName);
		await openProject(admin, projectName);
		await createWorkGroup(admin, wgName);
		const readUuid = await issueInviteKey(
			admin,
			"read",
			uniqueName("key-read")
		);
		const adminUuid = await issueInviteKey(
			admin,
			"admin",
			uniqueName("key-admin")
		);

		// B redeems the READ key first → read member (no admin tools).
		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);
		await redeemInviteKey(member, readUuid);
		await member.reload();
		await openProject(member, projectName);
		await expect(
			member.getByRole("button", { name: /招待管理/ })
		).toHaveCount(0);

		// Back to the list (projectId is in-memory; reload resets to the list),
		// then redeem the ADMIN key → privilege upgrades read → admin.
		await member.goto("/");
		await redeemInviteKey(member, adminUuid);
		await member.reload();
		await openProject(member, projectName);
		// The upgrade is observable: 招待管理 now appears for the same member.
		await expect(
			member.getByRole("button", { name: /招待管理/ })
		).toBeVisible({ timeout: 10_000 });
	});

	test("revoked key cannot be redeemed; project stays invisible", async ({
		appPage: admin,
		userB: member,
	}) => {
		// User A issues a read key; issueInviteKey leaves A on 招待管理 with the
		// active key row rendered (the list-render fix makes this reliable).
		const { projectName, uuid } = await setupProjectWithKey(admin, "read");

		// Revoke it through the UI: the ✕ button (title=無効化) on the active row,
		// then confirm in the modal. Gate on the DELETE completing AND the row
		// moving to the 無効・期限切れ section before B tries to redeem — otherwise
		// the two actors race the same key.
		await admin.getByTitle("無効化").click();
		const confirm = admin.locator(".modal").last();
		const delResp = admin.waitForResponse(
			(r) =>
				/\/invite_keys\/[0-9a-f-]+$/i.test(r.url()) &&
				r.request().method() === "DELETE"
		);
		await confirm.getByRole("button", { name: "無効化", exact: true }).click();
		expect((await delResp).status()).toBe(200);
		await expect(admin.getByText(/無効・期限切れ/)).toBeVisible({
			timeout: 10_000,
		});

		// User B attempts to redeem the now-revoked key.
		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);
		await member.getByRole("button", { name: /招待キーを使用/ }).click();
		const d = member.locator(".modal").last();
		await d
			.getByPlaceholder("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx")
			.fill(uuid);
		await d.getByRole("button", { name: "参加する", exact: true }).click();

		// Redemption must fail: an error surfaces in the dialog (backend 404
		// "InviteKey not found" — the disabled key is treated as non-existent)
		// and the join-confirmation never appears.
		await expect(d.getByText(/not found|InviteKey/i)).toBeVisible({
			timeout: 10_000,
		});
		await expect(d.getByText(/プロジェクトに参加しました/)).toHaveCount(0);
		await d.getByRole("button", { name: "キャンセル", exact: true }).click();

		// And the project remains invisible after reload (API-backed truth).
		await member.reload();
		await expect(projectCard(member, projectName)).toHaveCount(0);
	});
});
