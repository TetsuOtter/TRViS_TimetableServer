# E2E spec-writing brief (shared)

You are writing Playwright E2E specs for the TRViS Timetable editor frontend.
Goal: **click every button / button-like control on your assigned screen and
assert the REAL EFFECT**, then report which controls are unimplemented,
wired-to-the-wrong-source, or hit a backend 500.

## The whole point

The user is hunting for controls that *look* like they work but don't —
buttons, and **button-like `onClick` divs/spans, context-menu items, kebab
menus, drag-reorder handles**. A test that only checks "a modal opened" or "a
click happened" PASSES on exactly these broken controls and is useless. **Every
spec must assert the downstream effect**, and for create/edit/delete the gold
standard is: **reload the page and assert via the rendered (API-backed) list.**
The app renders from the backend API (TanStack Query), NOT from any in-memory
sample data — so a reload is what proves a mutation actually persisted.

## Harness (already built — use it, don't reinvent)

- `import { test, expect } from "./fixtures";` — `test` has an `appPage`
  fixture: a Page already signed in (Firebase emulator) and on the project
  list. Use `test("...", async ({ appPage: page }) => { ... })`.
- `import { login } from "./fixtures";` for manual control if needed.
- `import { createProject, openProject, gotoLines, gotoColors,
  createWorkGroup, createWork, uniqueName, modal, clickModalButton,
  projectCard } from "./helpers";` — shared nav/creation helpers. Read
  `e2e/helpers.ts` to see exactly what they do.
- Default app language is **Japanese** (`ja`). All visible labels are the
  Japanese strings in `src/i18n/strings.ts`. Use **regex / substring** role
  matching, never exact — labels carry emoji + whitespace (e.g. "📥 インポート").

## Hard rules

1. **Do NOT edit anything under `src/`** and **do NOT edit `e2e/helpers.ts`,
   `e2e/fixtures.ts`, `e2e/credentials.ts`, `playwright.config.ts`, or
   `global-setup.ts`.** You may only CREATE your own spec file. Put any
   screen-specific helpers as local functions inside your spec file.
2. **Self-seed** every test with `uniqueName("...")`. Never depend on
   pre-existing data.
3. **Scope every assertion to your own uniquely-named entities.** NEVER assert
   global counts ("N 件"), "list length equals", or anything about entities you
   didn't create — other specs run concurrently against the same backend.
4. Capture dialogs so they don't hang the run AND so you can report errors:
   ```ts
   const alerts: string[] = [];
   page.on("dialog", (d) => { alerts.push(d.message()); void d.accept(); });
   ```
   Mutations surface backend errors via `alert(e.message)` (an `ApiError`
   message). If a button "does nothing", check `alerts` — an alert with a 4xx/5xx
   message means backend-broken, not frontend-unimplemented. Note: native
   `confirm()` is used by JSON import; entity deletes use a custom modal
   (`.modal` with a 削除 button), NOT native confirm.
5. Run ONLY your own spec, with your own output dir:
   `yarn playwright test e2e/<yourfile>.spec.ts --reporter=line --output=test-results/<yourscreen>`
   Iterate until selectors are stable and each test's pass/fail reflects the
   real app behavior (a red test because the FEATURE is broken is a SUCCESS for
   this exercise — keep it red and document it; a red test because your SELECTOR
   is wrong must be fixed).
6. For a control you believe is broken, write the spec to assert the
   *correct* behavior (so it fails now and will pass once fixed), and mark it
   `test.fail()` ONLY if you want green CI — but prefer leaving it as a normal
   failing assertion and documenting it. Either way, the assertion must encode
   the intended effect.

## Triage buckets for your report

For each control, classify:
- **OK** — works, effect verified (ideally across reload).
- **UNIMPLEMENTED** — handler missing / no-op; nothing happens, no network call.
- **WRONG-SOURCE** — does something but against the wrong data (e.g. reads/writes
  in-memory sample data instead of the API), so the visible effect is missing or
  stale.
- **BACKEND-500** — frontend calls the API correctly but the server errors
  (alert shows a 4xx/5xx). Include the status/message.
- **UNSURE** — couldn't determine; explain what blocked you.

## Report format (return this as your final message)

```
## <Screen name>
Spec file: e2e/<file>.spec.ts  (N tests: P passed, F failed)

### Clickable inventory
- <control> → <handler/expected effect> → <bucket> — <evidence: alert text,
  HTTP status, persisted? reload-checked?>
...

### Findings (non-OK only), most severe first
1. [BUCKET] <control>: <what's wrong> — <evidence> — <file:line of the handler
   in src, if you found it>
```

Keep evidence concrete (HTTP status, alert text, "created then reload → gone").
