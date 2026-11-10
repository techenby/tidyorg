# Implementation plan: modular architecture

Status: proposed, 2026-11-10. Nothing below is built yet.

This plan turns the rules in [../architecture.md](../architecture.md) into working code, using Inventory (ported from Sunny) as the first module. Each phase ends in a state that is shippable and tested. Phases 0–3 should be done in order. Phases 4 (self-hosting) and 5 (Cloud) are independent of each other and can be swapped depending on whether self-hosters or paying customers come first.

## Starting point

- Tessera is the Laravel Livewire starter kit with Teams: Laravel 13, Livewire 4, free Flux, Fortify, Pest. Routes are prefixed with `{current_team}` and guarded by `EnsureTeamMembership`. Roles are the `TeamRole` enum (owner, admin, member) with a fixed `TeamPermission` enum.
- It is not yet a git repository.
- Sunny's inventory lives in `sunny/web`: one `Item` model (self-nesting, typed as location, bin, or item, soft-deleted, with photo, thumbnail, and JSON metadata), six actions, a policy, two Livewire pages with modals (about 2,500 lines including tests), a QR-code deep link, a REST API controller, and eight MCP tools.

## Phase 0: Foundations

Decisions and housekeeping that are cheap now and expensive later.

- [ ] `git init`, first commit of the untouched starter kit so later diffs are readable.
- [ ] Apply AGPL-3.0: add `LICENSE.md` with the full licence text, set `"license": "AGPL-3.0-or-later"` in `composer.json`, and state it in the README.
- [ ] Rename the package in `composer.json` from `laravel/livewire-starter-kit` to `tessera/tessera`.
- [ ] Remove personal teams: drop `is_personal`, adjust `CreateNewUser`, registration, and the team switcher, handle a signed-in user with no organization, and add an onboarding step that creates or joins one.
- [ ] Change UI copy from "team" to "organization". Leave class, table, and route-parameter names alone so starter-kit updates still apply.
- [ ] Remove the `@source` line for `flux-pro` from `resources/css/app.css`.

Done when: the app runs as before, the test suite passes, and the UI says "organization" throughout.

## Phase 1: Module system in core

Build the mechanism with no real module yet, proven by a fixture module in the test suite.

- [ ] `app/Modules/Module.php`: abstract service provider. Subclasses declare `key()`, `name()`, `description()`, `icon()`, `dependencies()`, and optionally `permissions()`, `navigation()`, `widgets()`, `settingsPage()`. `register()` adds the module to the registry; `boot()` wires routes, views, migrations, translations, and commands only if the module is active.
- [ ] `app/Modules/ModuleRegistry.php` (singleton, `Modules` facade): `installed()`, `active()`, `enabled(string $key, ?Team $team = null)`, `enabledFor(Team $team)`, `enable()`, `disable()`. Validates that dependencies are present and acyclic at boot and fails loudly if not.
- [ ] `config/tessera.php`: `modules` (from `TESSERA_MODULES`; `*` means all installed), `single_organization`.
- [ ] Migration `team_modules` (`team_id`, `module`, `settings` JSON, timestamps; unique on team and module). `Team::modules()` relation. Per-request caching of the enabled set.
- [ ] `EnsureModuleEnabled` middleware, aliased `module`. Returns 404 when the module is not enabled for the route's organization.
- [ ] Route wiring: the base provider wraps a module's `routes/web.php` in `{current_team}/<key>`, the `auth`, `verified`, `EnsureTeamMembership`, and `module:<key>` middleware, and the `<key>.` name prefix.
- [ ] Views: `Livewire::addNamespace($key, viewPath: ...)` plus `loadViewsFrom` for plain Blade components.
- [ ] Navigation: the sidebar renders `Modules::navigationFor($team, $user)` in place of the hard-coded "Platform" group. The dashboard renders registered widgets the same way.
- [ ] Permissions: a string-keyed registry for module permissions, each with a minimum `TeamRole`. Extend `HasTeams::hasTeamPermission()` to accept either a `TeamPermission` or a registered string.
- [ ] Organization settings → Modules page: list active modules with description, enable and disable (owner and admin only), enforce dependencies. Hidden in single-organization mode, where every active module is enabled automatically.
- [ ] `ModuleEntitlements` contract with a default implementation that allows everything. The enable action consults it.
- [ ] Commands: `module:list` (installed, active, enabled counts), `module:enable <key> --team=`, `module:disable`, `module:purge <key> --team=` (confirmed, deletes one organization's data for the module through its `purging` hook), `module:purge <key>` (confirmed, rolls back that module's migrations; refuses when more than one organization exists unless forced).
- [ ] Root `composer.json`: add the `modules/*` path repository. `phpunit.xml`: add a `Modules` test suite over `modules/*/tests`. `app.css`: add `@source '../../modules/*/resources/views'`. `phpstan.neon` and `pint.json`: include `modules/`.
- [ ] Architecture test: nothing in `App` uses `Tessera`.
- [ ] Fixture module under `tests/Fixtures/modules/example`, used to test: inactive module registers no routes or migrations; active-but-not-enabled returns 404 and shows no nav; enabled works; dependency enforcement; purge.

Done when: the fixture module can be activated, enabled, disabled, and purged in tests, and the app with zero modules behaves exactly like the end of phase 0.

## Phase 2: Inventory module

Port Sunny's inventory as `modules/inventory` (`tessera/inventory`, namespace `Tessera\Inventory`).

Bring across:

- [ ] `Item` model, `ItemType` enum, `ItemFactory`, and one squashed migration for `inventory_items` (the Sunny history of containers merging into items is not needed).
- [ ] Actions: `CreateItem`, `UpdateItem`, `DuplicateItem`, `MoveItemToTeam`, `GenerateItemQrCode`.
- [ ] `ItemPolicy`, rewritten on module permissions: `inventory:view` (member), `inventory:manage` (member), `inventory:delete` (admin). Sunny's policy only checks membership.
- [ ] Index and show pages with their modals and tests, moved to the `inventory::` Livewire namespace.
- [ ] The `i/{item}` QR deep link.
- [ ] Dashboard items widget.
- [ ] Sunny's user documentation for inventory, as the start of `docs/`.

Change on the way in:

- [ ] **Replace Flux Pro components.** The Sunny pages use `flux:table`, `flux:date-picker`, and `flux:file-upload`, and possibly others; audit every `flux:` tag against the free component list. Rebuild with free Flux, native inputs, and a shared core table component (it will be needed by every module).
- [ ] **Photos on any disk.** Sunny calls `Storage::temporaryUrl()`, which assumes S3. Cloud uses object storage and self-hosters may use `local`, so put this behind one core helper that returns a URL for a stored file on whichever disk is configured, and test it against both.
- [ ] **Tenant isolation tests.** For every page and action, a test that a member of one organization cannot see or change another organization's items. Sunny's `viewAny` and `create` return `true` unconditionally; tighten them.
- [ ] **Portable SQL.** `Item::descendantIds()` uses a recursive CTE with a hard-coded table name. Confirm it on SQLite, MySQL, MariaDB, and PostgreSQL, and use the model's table name.
- [ ] Module-owned dependencies: `bacon/bacon-qr-code` and `spatie/simple-excel` go in the module's `composer.json`, not the root. (Adding them needs sign-off as a dependency change.)
- [ ] Drop the Pennant feature class; the module system replaces it.

Leave behind for now:

- Amazon order import (household-specific).
- REST API, MCP tools, and `client_uuid` mobile sync. These come back in phase 6 once core has an API story.

After the straight port, a separate pass should fit the model to nonprofits, which is a product conversation rather than a porting task: quantities and units, condition, value and funding source for grant and insurance reporting, and check-out to people (which will want the Members module).

Done when: Inventory can be enabled for an organization and used end to end; `composer remove tessera/inventory` leaves an app whose core suite passes; the module's suite passes on SQLite and one server database.

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
