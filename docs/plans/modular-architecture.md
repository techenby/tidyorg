# Implementation plan: modular architecture

Status: phases 0, 1, and 2 complete (2026-11-10). Phase 3 is next.

This plan turns the rules in [../architecture.md](../architecture.md) into working code, using Inventory (ported from Sunny) as the first module. Each phase ends in a state that is shippable and tested. Phases 0–3 should be done in order. Phases 4 (self-hosting) and 5 (Cloud) are independent of each other and can be swapped depending on whether self-hosters or paying customers come first.

## Starting point

- Tessera is the Laravel Livewire starter kit with Teams: Laravel 13, Livewire 4, free Flux, Fortify, Pest. Routes are prefixed with `{current_team}` and guarded by `EnsureTeamMembership`. Roles are the `TeamRole` enum (owner, admin, member) with a fixed `TeamPermission` enum.
- Sunny's inventory lives in `sunny/web`: one `Item` model (self-nesting, typed as location, bin, or item, soft-deleted, with photo, thumbnail, and JSON metadata), six actions, a policy, two Livewire pages with modals (about 2,500 lines including tests), a QR-code deep link, a REST API controller, and eight MCP tools.

## Phase 0: Foundations

Decisions and housekeeping that are cheap now and expensive later.

- [x] `git init`, first commit of the untouched starter kit so later diffs are readable.
- [x] Apply AGPL-3.0: add `LICENSE.md` with the full licence text, set `"license": "AGPL-3.0-or-later"` in `composer.json`, and state it in the README.
- [x] Rename the package in `composer.json` from `laravel/livewire-starter-kit` to `tessera/tessera`.
- [x] Remove personal teams: drop `is_personal`, adjust `CreateNewUser`, registration, and the team switcher, handle a signed-in user with no organization, and add an onboarding step that creates or joins one.
- [x] Change UI copy from "team" to "organization", and the settings URLs to `settings/organizations`. Class, table, route, and route-parameter names stay as `team` so starter-kit updates still apply.
- [x] Remove the `@source` line for `flux-pro` from `resources/css/app.css`.

Done when: the app runs as before, the test suite passes, and the UI says "organization" throughout.

Completed 2026-11-10. A user with no organization lands on `/onboarding`, where they create one or accept a pending invitation. `User::factory()` still gives each user an organization they own; use `->withoutTeam()` for a user with none.

## Phase 1: Module system in core

Completed 2026-11-10. The mechanism exists with no real module yet, proven by two fixture modules in the test suite.

- [x] `App\Modules\Module`: abstract service provider. Subclasses declare `key()`, `name()`, `path()`, and optionally `description()`, `icon()`, `dependencies()`, `permissions()`, `navigation()`, `widgets()`, and the `enabling()`, `disabled()`, `purging()` hooks. Routes, views, migrations, and translations are wired only when the module is active.
- [x] `App\Modules\ModuleRegistry` (singleton, `App\Modules\Facades\Modules`): `installed()`, `active()`, `enabled()`, `enabledFor()`, `navigationFor()`, `widgetsFor()`, `allows()`. The app refuses to boot when an active module's dependency is inactive or dependencies are circular.
- [x] `config/tessera.php`: `modules` (`TESSERA_MODULES`, default `*`) and `single_organization` (`TESSERA_SINGLE_ORGANIZATION`).
- [x] `team_modules` table, `TeamModule` model, `Team::modules()`.
- [x] `EnsureModuleEnabled` middleware, aliased `module`: 404 unless enabled for the route's organization.
- [x] Route wiring: a module's `routes/web.php` is wrapped in `{current_team}/<key>`, the `web`, `auth`, `verified`, `EnsureTeamMembership`, and `module:<key>` middleware, and the `<key>.` name prefix.
- [x] Views: a module's `resources/views` is registered as the `<key>::` namespace for both Blade and Livewire.
- [x] Navigation and dashboard widgets render from the registry, filtered by enablement and permission.
- [x] Permissions: `User::hasTeamPermission()` accepts a `TeamPermission` or a module permission string. A module permission is held only while its module is enabled for that organization.
- [x] Organization settings → Modules section (owners and admins), hidden when no modules are active and in single-organization mode.
- [x] `ModuleEntitlements` contract, default `AllowAllModules`. Bound with `bindIf` so a hosting package's binding wins.
- [x] `EnableModule` and `DisableModule` actions; commands `module:list`, `module:enable`, `module:disable`, `module:purge`.
- [x] Root wiring: `modules/*` Composer path repository, `Modules` PHPUnit suite, Pest and Tailwind sources.
- [x] Architecture test: nothing in `App` uses `Tessera`.
- [x] Fixture modules under `tests/Fixtures/Modules`, registered in tests with the `installModules()` helper.

Left for the phase that first needs them:

- Per-module settings pages and per-organization module settings (the `settings` column exists; nothing reads it yet).
- Module console commands and scheduled work; a module registers these itself in `bootModule()`.
- `modules/` in `phpstan.neon`, added with the first real module since there is nothing there to analyse yet.
- A module booting through Composer package discovery was not exercised until phase 2, which proved it.

## Phase 2: Inventory module

Done. Sunny's inventory now lives in `modules/inventory` (`tessera/inventory`, namespace `Tessera\Inventory`), installed through the path repository and found by package discovery.

Brought across:

- [x] `modules/` added to `phpstan.neon` (tests and views excluded); the module boots through package discovery.
- [x] `Item` model, `ItemType` enum, `ItemFactory`, and one squashed migration for `inventory_items`.
- [x] Actions: `CreateItem`, `UpdateItem`, `DuplicateItem`, `MoveItemToTeam`, `GenerateItemQrCode`, plus `StoreItemPhoto` and `CreateThumbnail`.
- [x] `ItemPolicy` on module permissions: `inventory:view` (member), `inventory:manage` (member), `inventory:delete` (admin).
- [x] Index and show pages with their modals, as `inventory::index` and `inventory::show`. They share the abstract `Tessera\Inventory\Livewire\InventoryPage` class, a class rather than a trait so PHPStan analyses it.
- [x] The `i/{item}` QR deep link, registered by the module and limited to numeric ids so it cannot shadow an organization whose slug is `i`.
- [x] Dashboard widget of recently added items.
- [x] User documentation in `docs/users/inventory.md`.

Changed on the way in:

- [x] **No Flux Pro.** The listbox selects became native selects, and the file upload became `x-file-input`, a core Blade component around a native file input. The date picker was only used by the Amazon import, which was not ported.
- [x] **Photos on any disk.** `App\Modules\StoredFile::url()` returns a signed temporary URL when the disk provides one (object storage, and the private local disk) and the public URL otherwise. Photos are stored under `teams/{id}/inventory`.
- [x] **Tenant isolation.** `modules/inventory/tests/TenantIsolationTest.php` covers every page and action. Every query starts from `Item::forTeam()`, and parents are validated against the organization, which Sunny did not do. An item also cannot be placed inside itself or its own contents.
- [x] **Portable SQL.** `Item::descendantIds()` uses the model's table name. The whole suite passes on SQLite, PostgreSQL, and MySQL.
- [x] `bacon/bacon-qr-code` is required by the module's `composer.json`. `spatie/simple-excel` was not added; nothing uses it until import and export exist.
- [x] Pennant feature class dropped.
- [x] Thumbnails need the GD extension; without it the full photo is shown instead.

Behaviour that differs from Sunny:

- Members cannot delete items; admins and owners can.
- Moving an item to another organization needs `inventory:manage` in both, needs Inventory enabled in the destination, takes the photo along, and leaves the item's contents behind.
- A QR link returns 404, not 403, to someone who may not view the item.
- The parent pickers load their options only once the form is opened or items are selected, so a large inventory is not loaded on every render.

Left behind for now:

- Amazon order import (household-specific).
- REST API, MCP tools, and `client_uuid` mobile sync. These come back in phase 6 once core has an API story.
- A searchable parent picker. The native select is fine for hundreds of items and will want replacing for thousands.

Verified: `composer remove tessera/inventory` leaves an app whose core suite passes. Two core tests assumed no module was installed and now set that up themselves.

After the straight port, a separate pass should fit the model to nonprofits, which is a product conversation rather than a porting task: quantities and units, condition, value and funding source for grant and insurance reporting, and check-out to people (which will want the Members module).

## Phase 3: Prove the boundary with a second module

One module cannot show whether the extension points are right. Build the smallest useful version of **Members** (a directory of people connected to the organization, distinct from user accounts) directly as a module.

- [ ] `modules/members` with model, pages, permissions, nav, and tests.
- [ ] One optional integration: Inventory shows "checked out to" when Members is enabled, and nothing when it is not.
- [ ] Architecture tests per module: each uses only core and its declared dependencies.
- [ ] CI matrix: core only; each module alone with its dependencies; everything. Run on PostgreSQL (what Cloud uses), SQLite, and MySQL.
- [ ] Revise `docs/architecture.md` with whatever the second module forced to change.
- [ ] `php artisan make:module <name>` scaffold, written only now that two real modules agree on the shape.

Done when: any subset of {inventory, members} installs, activates, and passes its tests.

## Phase 4: Self-hosting

- [ ] `php artisan tessera:install`: interactive first run. Checks requirements, writes `.env`, migrates, creates the first admin and organization, asks which modules to activate.
- [ ] `php artisan tessera:upgrade`: maintenance mode, migrate, clear and rebuild caches, restart queue.
- [ ] Docker image and `docker-compose.yml` (app, queue worker, scheduler, database) with all first-party modules installed and `TESSERA_MODULES` choosing which are active. Assets are prebuilt in the image so self-hosters never need Node.
- [ ] For non-Docker installs, commit or release-attach built assets for the same reason.
- [ ] Single-organization mode: registration closed after the first admin, invitations only.
- [ ] Self-hosting documentation: install, upgrade, backup and restore, choosing modules, removing a module completely.
- [ ] Revisit slim distribution (Packagist modules, per-module image builds) only if self-hosters ask for it.

Done when: a clean machine goes from nothing to a working single-organization install by following the documentation.

## Phase 5: Cloud on Laravel Cloud

Paying customers are hosted on Laravel Cloud as one multi-tenant deployment. Use the `deploying-to-cloud` skill for the mechanics.

- [ ] Private `tessera/cloud` package: subscription billing, plan limits, a `ModuleEntitlements` implementation (permissive for now, since no feature is paid-only), self-serve organization sign-up and provisioning, operator admin.
- [ ] Private deployment repository that tracks this one and adds the `tessera/cloud` requirement and lock entry. Laravel Cloud deploys from it; Composer credentials for the private package are supplied as a build secret.
- [ ] Laravel Cloud environment: PostgreSQL, object storage as the default filesystem disk, a queue worker, the scheduler, a cache store, and transactional mail. `TESSERA_MODULES=*`, single-organization mode off.
- [ ] Deploy command runs `php artisan migrate --force`; confirm every migration to date is safe to run while the previous release is still serving.
- [ ] Audit for anything written to local disk or held in memory between requests.
- [ ] Any contract Cloud needs that core lacks (usage limits, for example) is added to core with a permissive default, never as an edition check.
- [ ] Offer the running source to Cloud users as AGPL requires: a footer link to the repository and the deployed commit.
- [ ] Customer-facing operations: backups and restore, per-organization data export, per-organization deletion on cancellation (which exercises every module's `purging` hook).

Done when: a new customer can sign up, pay, create an organization, enable modules, and use them on the Cloud deployment, and a cancelled organization's data can be exported and removed.

## Phase 6: Platform

- [ ] API and MCP extension points in core, then restore Inventory's API controller and MCP tools from Sunny on top of them.
- [ ] Further modules: Events, then whatever early users ask for (volunteers, donations, documents).
- [ ] Module API versioning once third parties might write modules: the root package provides a virtual `tessera/module-api` version that modules require. Confirm Composer honours `provide` on the root package before relying on it.

## Risks

| Risk | Mitigation |
| --- | --- |
| Core quietly grows a reference to a module and removal breaks | Architecture test plus the core-only CI job, from phase 1 onward |
| Extension points designed around one module do not fit the next | Phase 3 exists for this; the scaffold command waits until after it |
| Starter-kit updates conflict with Tessera's changes | Keep `Team` naming in code, keep core changes to the kit small and additive, record the upstream commit Tessera started from |
| Module migrations and core migrations interleave unpredictably | Modules only reference core tables that exist in the initial schema or declare the dependency; purge rolls back by module path, not by batch |
| Flux Pro creeps back in through copied Sunny code | Flux Pro is not in `composer.json`, so any Pro tag fails in tests immediately |
| `composer remove` on a git checkout makes upgrades conflict | Documented as an advanced path; activation switch is the default; revisit in phase 4 |
| One organization reads another's data on Cloud | Isolation test required per module (phase 2 onward); route-bound models always authorized against the current organization |
| A migration breaks the release still serving traffic during a Cloud deploy | Expand-then-contract rule in the architecture guide; reviewed per migration |
| The private deployment repository drifts from this one | It carries only the `composer.json` and lock change; updating it is a merge from this repository's release tag |
