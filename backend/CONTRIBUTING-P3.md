# CONTRIBUTING — Phase 3 fan-out playbook

> **Note:** `backend_legacy/` was removed in Phase 5; the `backend_legacy/<X>` example paths below refer to the legacy tree as it existed in git history.

> **Audience:** the agent team cloning the Project vertical to the remaining
> entities (Line / StationOnLine / StopPattern / WorkGroup / Work / …).
>
> **The rule:** `Project` is the canonical worked example. Clone its shape
> file-for-file. Do **not** invent structure — if something here disagrees
> with the `Project` files, the `Project` files win (read them, don't
> reconstruct from memory). Phase 2 commit: `d351f0f`.

---

## 0. READ THESE FIRST (cold-start — do NOT skip)

You are porting ONE entity `X` (e.g. `WorkGroup`). Before you write a
single line, open and read these, in this order. Do not reconstruct any
of them from memory; the `Project` files and the legacy `MODEL_SCHEMA`
are ground truth.

**Legacy (the RED contract + the faithful-port source):**

```
backend_legacy/lib/trvis_backend/model/<X>.php           # MODEL_SCHEMA heredoc — the canonical OpenAPI schema to port byte-faithfully (title/required/properties/order/format/example)
backend_legacy/src/trvis_backend/api/<X>Api.php           # hand-written handlers (legacy generic-base shape)
backend_legacy/src/trvis_backend/service/<X>sService.php  # legacy service (note the plural: <X>s)
backend_legacy/src/trvis_backend/repo/<X>sRepo.php        # legacy repo  (+ <X>sPrivilegesRepo.php if one exists)
backend_legacy/tests/Api/<X>ApiTest.php                   # the RED contract — your tests must assert the SAME behaviour
```

**New backend (the canonical worked example — clone its SHAPE):**

```
backend/src/trvis_backend/model/Project.php
backend/src/trvis_backend/repo/ProjectsRepo.php
backend/src/trvis_backend/service/ProjectsService.php
backend/src/trvis_backend/api/ProjectApi.php
backend/tests/Unit/ProjectDriftTest.php
backend/tests/Api/ProjectApiTest.php
backend/CONTRIBUTING-P3.md   # this file
```

### Scope rules (hard constraints)

- **Only your entity `X`.** Do not touch other entities' files.
- **Do NOT edit `RegisterRoutes::API_CLASSES`** (or any other
  shared/mainline file). It is the single serialisation point and the
  **mainline integrator owns it** — N parallel worktrees all appending
  one line to it is a guaranteed merge conflict. Write your `routes()`
  correctly and stop; the integrator appends `<X>Api::class` and runs
  `RouteSmokeTest` during the serial merge. (§1 / §10 restate this.)
- **Auth and Dump are OUT OF SCOPE for the fan-out.** `Auth` is token
  issuance (special, not CRUD) and `Dump` is a read-only aggregate over
  *all* entities (must come last, after every CRUD entity lands).
  Decided separately — if your assigned entity is `Auth` or `Dump`,
  stop and report back instead of porting.
  **STATUS (2026-05-19): both LANDED — the P3.5 epilogue is complete and
  PHASE 3 IS CLOSED.** `Dump` ported by the mainline integrator after the
  last CRUD entity (vertical `740ee23` / integrator merge `ca2fbfc`):
  single GET `/dump/{workGroupId}`, returns one `TRViS_json_WorkGroup`,
  Api-gate 403 if not signed in. `Auth` resolved as **not ported** — no
  concrete `AuthApi`, no `/auth` route; `MyAuthMiddleware` (Firebase
  ID-token) is the sole auth mechanism, pinned by `tests/Unit/AuthApiTest.php`
  (asserts the absence, not a 501 throw). Nothing here remains to do.

---

## 1. The layering (one entity = these files)

```
src/trvis_backend/model/<Entity>.php             BaseModel subclass + #[OA\Schema]
src/trvis_backend/repo/<Entity>sRepo.php          PDO ⇄ model, returns RetValueOrError
src/trvis_backend/service/<Entity>sService.php    privilege + orchestration
src/trvis_backend/api/<Entity>Api.php             handlers + #[OA\*] + routes()
tests/Unit/<Entity>DriftTest.php                  schema⇄const drift guard (no DB)
tests/Api/<Entity>ApiTest.php                     DB integration (drives the Service)
```

The **mainline integrator** (not you) then appends `<Entity>Api::class`
to `RegisterRoutes::API_CLASSES` during the serial merge — that single
line is the only shared-file edit and you must **not** make it yourself
(see §0 scope rules). There is no shared mutable route table, no shared
Parameters file, no generated `Abstract*` parent.

Dependency direction: `Api → Service → Repo → Model`. The `Api` ctor is
`__construct(private readonly PDO $db, private readonly LoggerInterface $logger)`
(autowired by PHP-DI when Slim resolves the route callable) and `new`s its
Service; the Service `new`s its Repo(s). Nothing below the Api is in the
container.

---

## 2. The model — `src/trvis_backend/model/Project.php`

- `class <Entity> extends \dev_t0r\BaseModel` with **two consts only**:
  `protected const OAS_PROPERTIES = [...];` and
  `protected const OAS_REQUIRED = [...];`.
- Properties live on a **single class-level `#[OA\Schema(...)]` attribute**,
  **never** as PHP properties (they would collide with BaseModel
  `__get`/`__set`). `OAS_*` consts drive the runtime model; the attribute
  drives swagger-php. `<Entity>DriftTest` asserts they stay in lock-step.
- `OAS_PROPERTIES` order **must** equal the `#[OA\Schema(properties:)]` order.
- Omit `required:` from the attribute when there are none (and
  `OAS_REQUIRED = []`) — swagger-php leaves an omitted arg as a non-array
  `UNDEFINED` sentinel; the drift test already normalises this.
- Faithful port: copy `title`/`required`/`properties`/order/`format`/
  `example` 1:1 from the legacy OpenAPI-Generator `MODEL_SCHEMA`.

### String-enum properties — the hardcoded-array rule (proven)

For a string property backed by a PHP `BackedEnum` (e.g. `privilege_type`
over `InviteKeyPrivilegeType`), the `#[OA\Property(enum: ...)]` arg **must be
the hardcoded literal string array** copied from the legacy `MODEL_SCHEMA`:

```php
new OA\Property(property: 'privilege_type', type: 'string',
    description: '権限の種類', readOnly: true, example: 'admin',
    enum: ['read', 'write', 'admin']),
```

- `EnumClass::cases()` → **PHP fatal** (method call in an attribute).
- `EnumClass::class` → emits backing **ints** `[0,1,2,3]` incl. `none` (wrong).
- Hardcoded array → byte-faithful. The runtime still uses the real enum in
  repo/service; only the generation-time attribute is hardcoded.

The drift test checks property **names + required only, never enum values**,
so the hardcoded list can never conflict with the consts.

---

## 3. The drift test — `tests/Unit/<Model>DriftTest.php`

Clone `ProjectDriftTest` verbatim, swap the class name. Two methods:
`testSchemaPropertyAttributesMatchOasPropertiesConst` and
`testSchemaRequiredMatchesOasRequiredConst`. It reflects the
`#[OA\Schema]` attribute and asserts:

- `array_map(property, schema->properties) === <Model>::OAS_PROPERTIES`
- `(is_array(schema->required) ? schema->required : []) === <Model>::OAS_REQUIRED`

No DB, no network — runs in the `Unit` suite.

**One drift test per `#[OA\Schema]`-bearing model — NOT per entity.** An
entity with a privilege bridge has TWO schema models and therefore TWO
drift tests: e.g. WorkGroup → `WorkGroupDriftTest` +
`WorkGroupsPrivilegeDriftTest` (Phase-2 precedent: Project →
`ProjectDriftTest` + `ProjectsPrivilegeDriftTest`). The canonical clone
templates are **`ProjectDriftTest` / `ProjectsPrivilegeDriftTest`** — and
the test's docblock must name the **actual** template you cloned, not a
stale `ApiInfoDriftTest` cliché.

---

## 4. The Api file — `src/trvis_backend/api/ProjectApi.php`

- `declare(strict_types=1);`, standalone class (no `Abstract*` parent).
- Each handler carries a full `#[OA\Get/Post/Put/Delete(...)]` attribute.
- **Parameters are written INLINE** (`new OA\PathParameter(...)` /
  `new OA\QueryParameter(...)`) — **never** `$ref` into
  `components.parameters`. A shared Parameters file would serialise the
  fan-out; inline keeps every entity's Api file independent.
- Responses `$ref` the shared schemas
  (`#/components/schemas/{<Entity>,…,ApiErrorData}`). `ApiErrorData` is the
  one shared error-body schema (peer of `ApiInfo`) — reuse it, don't clone.
- List endpoints: `new OA\JsonContent(type:'array', items: new
  OA\Items(ref:'#/components/schemas/<Entity>'))` + the `X-Total-Count`
  `OA\Header`.
- User id: `$userId = MyAuthMiddleware::getUserIdOrAnonymous($request);`
  returns a **non-null `string`** (anonymous ⇒ `Constants::UID_ANONYMOUS`).
  Pass it straight through: `userId: $userId`. **Do not** write
  `$userId ?? Constants::UID_ANONYMOUS` — that null-coalesce is dead in the
  new backend (it was a faithful-port artifact of the legacy nullable-uid
  signature; see §7).

### `routes()` — the FQCN-registry contract

Every Api class exposes `public static function routes(): array` returning a
list of:

```php
['methods' => ['GET'], 'basePath' => '/api/v1', 'path' => '/projects',
 'handler' => [self::class, 'getProjectList'], 'name' => 'getProjectList']
```

`basePath . path` **must agree byte-for-byte** with the operation's
`#[OA\*](path:)` (server base is `/api/v1`; Slim is strict about the
trailing slash). `name` must be globally unique. `RegisterRoutes` iterates
`API_CLASSES`, `->map()`s each, `->setName()`s it; the OPTIONS
`/{routes:.*}` CORS catch-all is registered first.

**Naming invariant — `name === operationId === <verb><Entity><Action>`,
even when the legacy spec used a bare name.** The route `name`, the
`#[OA\*](operationId:)`, and the swagger-generated operationId must all be
the **entity-prefixed** `<verb><Entity><Action>` form. The legacy spec is
inconsistent here (Project: `getProjectPrivilege`; WorkGroup: bare
`getPrivilege`/`updatePrivilege`) — the port **normalizes** the bare names
to `getWorkGroupPrivilege`/`updateWorkGroupPrivilege` to match the
ProjectApi pattern-lock and to collision-proof the fan-out (bare
`getPrivilege` from WG/InviteKey/Line would otherwise collide on
`RouteSmoke`, forcing a rename in the mainline-owned smoke test). The PHP
**method** name is **independent** — it follows the legacy
`#[CoversMethod]` test contract, not the operationId: WG keeps PHP methods
`getPrivilege()`/`updatePrivilege()` while exposing operationId/name
`getWorkGroupPrivilege`/`updateWorkGroupPrivilege`.

---

## 5. The integration test — `tests/Api/<Entity>ApiTest.php`

- **TRANSLATE the legacy test, do not copy it verbatim.** The legacy
  `backend_legacy/tests/Api/<Entity>ApiTest.php` is the *behavioural*
  RED contract — same scenarios, same expected statuses/values — but it
  drives the legacy **generic** base shape
  (`MyServiceBase::create(parentId, senderUserId, [models])`,
  `selectList(...)`, `selectOne(...)`, etc.). The new backend has **no
  generic base**: every service is bespoke per entity with its own
  signatures (e.g. `ProjectsService::selectProjectOne($projectsId,
  $currentUserId)`, `createProject($userId, $name, $desc)`). You must
  rewrite each test body against your new `<Entity>sService`'s actual
  methods/parameters — read `tests/Api/ProjectApiTest.php` for the
  shape, and your own `service/<Entity>sService.php` for the exact
  signatures. Preserve the *assertions* (e.g. non-member ⇒ 404,
  creator ⇒ admin `privilege_type=3`, `updated_at` advances), port the
  *call sites*.
- `extends IntegrationTestCase` (classmap-autoloaded via
  `autoload-dev.classmap: ["tests/Integration/"]` — **no `require_once`**;
  a `require_once` + class decl in one file trips
  `PSR1.Files.SideEffects`, which is **not** in the phpcs carve-out).
- Drives the **Service** directly (not HTTP through middleware). Route
  registration / OA parsing / container glue are covered separately by the
  drift tests, the byte-stable `openapi.json` regen, and
  `tests/Unit/RouteSmokeTest.php`.
- `IntegrationTestCase` skip-guards itself (`markTestSkipped`) when the DB
  is unreachable, so the suite stays green on a DB-less box. It creates a
  Project + admin (`privilege_type=3`) fixture in `setUp` and does FK-safe
  reverse-order teardown; use `register()` for rows you create.

---

## 6. The route regression net — `tests/Unit/RouteSmokeTest.php`

Already covers **all** entities automatically: it reflects
`RegisterRoutes::API_CLASSES`, flattens every `routes()` entry, and asserts
each is mounted with the right name/pattern/methods, names are unique, there
are no `(method, full-path)` collisions, and the collector holds exactly
`Σ routes() + 1` (the OPTIONS catch-all). **You do not edit this test when
adding an entity** — but a bad `routes()` shape, a `basePath/path` that
disagrees with the mounted pattern, a duplicate `name`, a path collision
between two entities, or an FQCN typo in `API_CLASSES` fails it immediately.
Run the `Unit` suite after every entity.

---

## 7. "Delete during port" — faithful-port artifacts to NOT carry forward

The legacy backend had a nullable-uid auth signature. The new
`MyAuthMiddleware::getUserIdOrAnonymous()` returns a non-null `string`, so
some legacy idioms are now **dead code**. Phase 2's first pass copied two
verbatim from the legacy source; review flagged them and the follow-up
removed them. **Do not replicate these idioms in your port** — strip them
as you go:

1. **`$userId ?? Constants::UID_ANONYMOUS`** when `$userId` came from
   `getUserIdOrAnonymous()` — drop the `?? …`, pass `$userId` directly
   (keep `Constants` imported only if used elsewhere, e.g. the
   privilege-target branches).
2. **`use Enum;`** in a validator that only references `\UnitEnum::class`
   (fully qualified) and the literal string `"Enum"` in a message — dead
   import; delete the `use` line.

Rule of thumb: if the legacy code guards a `null` that the new type system
makes impossible, the guard is dead — remove it in the port, don't replicate.

---

## 8. Cross-entity deferred cascades & fan-out ordering

A legacy service may cascade-delete into another entity's repo
(`WorkGroupsService::deleteWorkGroup` → `InviteKeysRepo::deleteByWorkGroupId`).
If that child entity is **not yet ported**, its repo class does not exist:
`use`-ing it fails autoload; silently dropping the cascade loses data
integrity. The rule (general — it outlives any one cascade):

- **DROP the cascade call. Do NOT import the missing class.** Leave a
  `// TODO(P3.x): cascade … when <Repo> is ported` block that quotes the
  exact legacy call — args **and** error-tolerance (which status it
  tolerates, when it rolls back) — so the child's pass re-adds it verbatim.
  Point the TODO at **this section of CONTRIBUTING-P3.md**, never at a
  throwaway scratch file (scratch is deleted at merge; the breadcrumb must
  survive).
- **Record the deferred cascade as a fan-out ORDERING constraint, stated
  explicitly** — not left to be inferred. The child entity's pass MUST
  re-add the cascade, so the child must be ported **after** its parent and
  the fan-out plan must say which entity goes last and why.

**Worked example (keep alongside the general rule):**
`WorkGroupsService::deleteWorkGroup` defers a cascade into `invite_keys`.
Therefore in the **Tier-1 fan-out, InviteKey is ported LAST** — after
Line / ProjectStation / StopPattern. The InviteKey pass MUST re-add
`WorkGroupsService::deleteWorkGroup`'s `invite_keys` cascade; the exact
legacy call (args + `HTTP_NOT_FOUND`-tolerant, rollback-on-other-error) is
quoted verbatim in the `// TODO(P3.4)` block in `WorkGroupsService.php`.

**§0 carve-out (the one documented exception).** When you are the deferred
entity (the child whose `<X>sRepo` was missing at the parent's port),
restoring the cascade IS part of your pass — this is the **one documented
exception** to §0's "only your entity" rule. You edit the parent's service to
delete the `// TODO(P3.x)` block and replace it with the legacy call verbatim
(args + error-tolerance, both quoted in the TODO). No other cross-entity edits
are permitted.

**Shared infrastructure lands on the integration branch FIRST (P3.4 lesson).**
Validators, base classes, and any other class **not owned by a single entity**
must NOT be re-ported from `backend_legacy/` independently by fan-out agents —
that produces N divergent copies (or worse, one agent references a class no
branch creates). In P3.4 StopPattern ported `Int`/`UuidValidationRule` while
InviteKey *referenced* `Int`- + `DateTimeValidationRule` it never created;
`phplint`/`phpcs`/service-level `phpunit` did NOT catch the dangling `use`
(only `RouteSmokeTest`, which instantiates every Api class, would — and it runs
once at integration). Rule: before launching a fan-out wave, the integrator
identifies the shared classes each entity will need (grep legacy `use
…\validator\…` / base classes) and lands them on the integration branch up
front; agents only port **entity-owned** files. When an agent must reference a
shared class not yet landed, it states so in its `*-SCRATCH.md` and the
integrator (a) lands it before that branch merges and (b) diffs its
constructor signature against every referencing call site — `RouteSmoke`
catches a *missing* class but never a *signature mismatch*.

**Regenerate `openapi.json` AS PART OF the merge commit, not separately
(P3.4 lesson).** `openapi.json` is the tracked code-first SoT and must
always equal `swagger-php(src/)`. In P3.4 the per-entity merges deferred
the regen to a planned "byte-stable final regen" step that was then never
committed — `c25d1f6` shipped an `openapi.json` missing the
`Line`/`ProjectStation`/`ProjectStation_location_lonlat` schemas its own
merged code defined. A stale spec passes every gate (phplint/phpcs/phpunit
never read it) yet silently breaks the downstream TS-client generation.
Rule: every commit that adds or changes a `#[OA\*]`-bearing class MUST
include the regenerated `openapi.json` in the **same** commit; the §9 regen
is not an optional epilogue, it is part of the entity's (or merge's) diff.

---

## 9. Quality gates (run ALL before committing an entity)

Host has **no** php/composer/node — Docker only. **Always use the absolute
mount path** (a `cd backend` shifts `$PWD` and breaks the mount):

```sh
B=/Users/tetsu/git/TRViS_TimetableServer/backend
docker run --rm -v "$B":/app -w /app php:8.2-cli php vendor/bin/phplint ./ --exclude=vendor
docker run --rm -v "$B":/app -w /app php:8.2-cli php vendor/bin/phpcs
docker run --rm -v "$B":/app -w /app php:8.2-cli php vendor/bin/phpunit --testsuite Unit
# DB suite: join the throwaway MySQL net, install pdo_mysql:
docker run --rm --network trvis-test-net -v "$B":/app -w /app php:8.2-cli \
  sh -c "docker-php-ext-install pdo_mysql >/dev/null 2>&1; php vendor/bin/phpunit"
# regenerate the spec and confirm it is byte-stable across two runs.
# NOTE: php:8.2-cli ships NO composer — invoke the binary directly
# (`composer openapi` exits 127). This is the verified invocation:
docker run --rm -v "$B":/app -w /app php:8.2-cli \
  php vendor/bin/openapi src/ --bootstrap vendor/autoload.php -o openapi.json --format json
```

### phpcs code-style contract (LOCKED — do not grow)

Tab indentation is kept. `phpcs.xml.dist` excludes **exactly these 4** rules
and no more:

- `Generic.Files.LineLength.TooLong`
- `PSR2.Classes.PropertyDeclaration.Underscore`
- `Generic.WhiteSpace.DisallowTabIndent`
- `Generic.WhiteSpace.ScopeIndent`

Everything else is enforced. Write **PSR12-shaped from the start**:

- classes/methods → opening brace on its **own** line; control structures
  (`if`/`for`/`try`/`catch`) → brace on the **same** line.
- multiline signature → `) {` on its own line.
- `} elseif (` (not `} else if (`).
- method names **must not** be underscore-prefixed
  (`PSR2.Methods.MethodDeclaration.Underscore` is enforced — it is **NOT**
  in the 4-exclude carve-out, which is **properties-only**
  (`PSR2.Classes.PropertyDeclaration.Underscore`, for snake_case OAS
  props) and must never grow to absorb methods). Legacy repos use
  `_`-prefixed private/static helpers as a visibility hint
  (`_fetchResultToWorkGroup`, `_selectProjectsIdByWorkGroupsId`); on port,
  **drop the leading `_` on the declaration AND every internal call
  site**. These helpers are private/static so the rename is not a
  public-API break — safe. Canonical precedent:
  `ProjectsRepo::fetchResultToProject` (no `_`).
- no `require_once` + symbol declaration in one file
  (`PSR1.Files.SideEffects` is enforced).

### TDD

t_wada-style RED → GREEN → REFACTOR. Write the drift test and the integration
test first; watch them fail; implement; refactor under green.

---

## 10. Per-entity checklist

- [ ] `model/<Entity>.php` — `OAS_*` consts + single `#[OA\Schema]`, order matches, hardcoded enum arrays
- [ ] `repo/<Entity>sRepo.php` — `fetchResultToX`, returns `RetValueOrError`
- [ ] `service/<Entity>sService.php` — privilege checks, orchestration **— verify the privilege-fail RETURN SHAPE against legacy per §11, NOT by copying a sibling/pilot service**
- [ ] `api/<Entity>Api.php` — handlers, inline params, `$ref` schemas, `routes()`, no dead `?? UID_ANONYMOUS`
- [ ] `tests/Unit/<Entity>DriftTest.php` — cloned from `ProjectDriftTest`
- [ ] `tests/Api/<Entity>ApiTest.php` — extends `IntegrationTestCase`, no `require_once`, drives the Service
- [ ] `RegisterRoutes::API_CLASSES` — **NOT yours**: the mainline integrator appends `<Entity>Api::class` during the serial merge (§0/§1). Do not edit this file.
- [ ] phplint + phpcs(4-exclude) + Unit + DB suite + byte-stable `openapi.json` regen all green
- [ ] §11 privilege-fail-shape audit: `grep -n "HTTP_FORBIDDEN\|::admin" service/<Entity>sService.php` cross-checked against **legacy**, not a sibling

## 11. Privilege-fail return shape — verify against LEGACY, never a sibling

**Root cause of the W1/P3.4/W2 faithful-port regression (fixed in `4fd9afe..b9bf101`):**
the P3.2 `WorkGroup` pilot legitimately returns `403 HTTP_FORBIDDEN` +
"You don't have permission…" on write-fail because **legacy
`WorkGroupsService` overrides `delete` and is one of only three services
that legitimately use 403**. Every later agent pattern-matched the pilot's
service template **without re-checking legacy**, propagating a 403+message
shape into `Works/Colors/StopPattern/StopPatternRow/ProjectStation/Train`
where legacy returns `404` `Utils::errContentNotFound()`. That both broke
faithful-port AND leaked existence info (a LOCKED security constraint:
`errContentNotFound()` deliberately omits entity info). The integrated RED
tests never exercised the read-only-member write path, so it passed every
gate undetected.

**The faithful SoT is `backend_legacy/src/trvis_backend/service/`, not the
integrated tree.** For EVERY privilege-fail `return` in your service
(`create`/`update`/`delete`, read-fail AND write-fail), verify BOTH axes:

1. **Privilege level** — does legacy require `read`/`write`/`admin`?
   `MyServiceBase::create/update/delete` all call `checkPrivilegeToWrite`
   ⇒ **`write`**. `::admin` is correct ONLY if the legacy `<Entity>sService`
   **overrides** that method (grep proves it: only `WorkGroupsService` &
   `ProjectsService` override `delete`).
2. **Response shape** — does legacy return `403` or `404`?
   `checkPrivilegeToWrite` returns `Utils::errContentNotFound()` (404,
   "存在情報を漏らさないため NotFound"). `403 HTTP_FORBIDDEN`+message is
   faithful ONLY for `WorkGroup` / `Project` / `InviteKey` (the legacy
   FORBIDDEN list: `grep -rn HTTP_FORBIDDEN backend_legacy/src/trvis_backend/service/`).

**Mechanical rule for every non-(WorkGroup|Project|InviteKey) entity:**
the write-fail block must return the **same NotFound the same method's
read-fail returns** (entity-specific `errXxxNotFound()` is the accepted
faithful-equivalent variant):

```php
if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
    return Utils::err<X>NotFound();
}
if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {   // NEVER ::admin unless legacy overrides
    return Utils::err<X>NotFound();                          // NEVER 403+message
}
```

A passing test suite is NOT evidence of faithfulness here — the existing
RED tests do not cover this branch. Verify by reading legacy.

**Deliberate test gap (named, not an oversight):** the read-only-member →
write path (member holds `read` but not `write`, POST/PATCH/DELETE) has
**no automated test** — legacy doesn't test it either, so strict
faithful-port does not mandate one and we do not add one. This is the
exact branch the W1/P3.4/W2 regression shipped through undetected. That
makes the §11 manual legacy-verification **load-bearing, not optional**:
it is the only safeguard. If you add an entity, run the §10 grep audit
and read legacy — do not rely on green gates.
</content>
</invoke>
