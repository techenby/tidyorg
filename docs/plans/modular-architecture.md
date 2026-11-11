# Implementation plan: modular architecture

Status: phases 0 to 4 complete (2026-11-10). Phase 5 is in progress: the parts in this repository are done, the private package and the deployment are not started.

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

Done. Members is the second module, and making Inventory use it without depending on it produced the one new extension point.

- [x] `modules/members` (`tessera/members`): a directory of people who do not need accounts, with name, email, phone, join date, and notes. `members:view` is held by members, `members:manage` by admins.
- [x] Optional integration: an item can be checked out to a person and checked back in. Inventory offers this only while a module enabled for the organization provides a people directory, and shows nothing otherwise.
- [x] **Capabilities**, the extension point this forced. Core owns `App\Modules\Contracts\PeopleDirectory`; Members provides it through `Module::capabilities()`; Inventory asks `Modules::capability()`. Neither module names the other, so there is no dependency to declare and either installs alone.
- [x] Architecture tests generated from each `modules/*/composer.json`: no use of an undeclared module, and no reaching into core outside its public surface.
- [x] CI matrix in `.github/workflows/tests.yml`: no modules, each module alone, and all modules, each on SQLite, PostgreSQL, and MySQL. Every combination was run locally and passes; the workflow itself has not run yet because nothing has been pushed.
- [x] `docs/architecture.md` revised with capabilities and how the boundary is enforced.
- [x] `php artisan make:module "Name"` scaffolds a module with a provider, route, page, and tests. Its own test installs the generated module and loads its page.

Notes:

- Inventory stores the person's id in `inventory_items.checked_out_to` with no foreign key, since it cannot know the provider's table. A person removed from the directory shows as "someone no longer in the directory" and the item can still be checked in.
- Removing a member is permanent. There is no archive or history yet.
- Not built, and worth deciding with real users first: check-out history, due dates, checking out several items at once, and a member's page listing what they hold (which needs a second contract in the other direction).

## Phase 4: Self-hosting

Done. The guides are in [../self-hosting/](../self-hosting/README.md).

- [x] `php artisan tessera:install`: checks PHP extensions and writable directories, asks for the address, database (testing the connection), one organization or several, and modules, writes `.env`, migrates, and creates the first administrator and organization. After writing `.env` it finishes in a new process so the new settings apply. It skips the `.env` step when settings come from the environment, and never creates a second administrator.
- [x] `php artisan tessera:upgrade`: maintenance mode, migrate, clear and rebuild caches, restart queue workers. It stays in maintenance mode when a step fails.
- [x] `Dockerfile`, `compose.yaml`, and `docker/`: one image (FrankenPHP, PHP 8.5, unprivileged user) run as the web server, the queue worker, and the scheduler, with PostgreSQL. Every first-party module is installed, `TESSERA_MODULES` chooses which are active, and assets are built in the image.
- [x] `.github/workflows/release.yml`: a version tag publishes a ready-to-run archive (code, PHP dependencies, built assets) and pushes the image to the GitHub container registry.
- [x] Single-organization mode: registration only with a pending invitation, no second organization, the home page goes to sign-in.
- [x] `TESSERA_TRUSTED_PROXIES`, for installs behind a reverse proxy that handles HTTPS.
- [x] Self-hosting documentation: Docker install, server install, configuration, modules (choosing and removing completely), backups and restoring, upgrading.
- [ ] Revisit slim distribution (Packagist modules, per-module image builds) only if self-hosters ask for it.

Checked by hand on 2026-11-10: the installer, an upgrade with cached configuration and routes, and sign-in on a clean copy with SQLite; the compose stack under Podman, including the installer, a queued invitation email, invitation-only registration, file links, and the documented backup commands.

Not yet exercised, because nothing has been pushed: the release workflow, the published image, and installing from a release archive. Do a trial release before pointing anyone at the guides.

Notes for later phases:

- The lock file needs PHP 8.4.1 or newer (Symfony 8), although `composer.json` says `^8.3`. The guides say 8.4. Either raise the constraint or pin `config.platform.php` and re-lock.
- The S3 driver is not a dependency, so the image cannot use object storage yet. Phase 5 needs it.
- The home page is still the starter kit's welcome page when several organizations are hosted, and the sidebar still links to the starter kit's repository and documentation.
- An invited person must confirm their email address, so a self-hosted install with more than one user needs working outgoing mail.

## Phase 5: Cloud on Laravel Cloud

Paying customers are hosted on Laravel Cloud as one multi-tenant deployment. Use the `deploying-to-cloud` skill for the mechanics.

Done in this repository (2026-11-10):

- [x] Per-organization deletion: `PurgeTeam` runs every module's `purging` hook and removes the organization and its files. Deleting in settings closes the organization; `organization:prune` purges it after `TESSERA_CLOSED_ORGANIZATION_DAYS`; `organization:purge` does it at once. Before this, deleting an organization left all of its module data and files behind.
- [x] Per-organization export: `ExportTeam` and `organization:export`, with an `exporting` hook on every module. Run by an operator for now.
- [x] The source offer AGPL requires: `TESSERA_SOURCE_URL` and `TESSERA_SOURCE_REVISION` link the repository and the running commit from the sign-in pages and the sidebar.
- [x] Migration review: every migration to date either creates a table or adds nullable columns (`inventory_items.checked_out_to`, `checked_out_at`), so all are safe to run while the previous release is serving.
- [x] Audit for local disk and in-memory state. Uploads, thumbnails, exports, and Livewire's temporary uploads all go through the default disk. Sessions, cache, and the queue are database-backed. Nothing is held between requests. Two settings matter on Cloud: `APP_MAINTENANCE_DRIVER=cache`, because the default writes a file to local disk, and `TESSERA_TRUSTED_PROXIES=*`.
- [x] No new core contract was needed for billing. A hosting package can add its own middleware to the `web` group to block an unpaid organization, and `ModuleEntitlements` already covers which modules a plan includes. Add a limits contract only when a plan with a limit exists.

Still to do, outside this repository:

- [ ] Private `tessera/cloud` package: subscription billing, plan limits, a `ModuleEntitlements` implementation (permissive for now, since no feature is paid-only), operator admin. It also requires the S3 driver (`league/flysystem-aws-s3-v3`), which core does not.
- [ ] Private deployment repository that tracks this one and adds the `tessera/cloud` requirement and lock entry. Laravel Cloud deploys from it; Composer credentials for the private package are supplied as a build secret.
- [ ] Laravel Cloud environment: PostgreSQL, object storage as the default filesystem disk, a queue worker, the scheduler, a cache store, and transactional mail. `TESSERA_MODULES=*`, single-organization mode off, and the settings named above.
- [ ] Deploy command runs `php artisan tessera:upgrade`.
- [ ] Self-serve sign-up already works with single-organization mode off (register, then create an organization). Decide where payment sits in that flow.
- [ ] Customer-facing: an owner-run export from organization settings, restoring a closed organization inside the retention period, and database backups (Laravel Cloud's own).
- [ ] Replace the starter kit's welcome page, which is what Cloud visitors see at `/`.

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
| `composer remove` on a git checkout makes upgrades conflict | The self-hosting guides never use it: the activation switch hides a module, `module:purge` deletes its data, and its code stays on disk |
| One organization reads another's data on Cloud | Isolation test required per module (phase 2 onward); route-bound models always authorized against the current organization |
| A migration breaks the release still serving traffic during a Cloud deploy | Expand-then-contract rule in the architecture guide; reviewed per migration |
| The private deployment repository drifts from this one | It carries only the `composer.json` and lock change; updating it is a merge from this repository's release tag |
