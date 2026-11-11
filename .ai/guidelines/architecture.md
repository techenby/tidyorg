# Tessera Architecture

Tessera is an open-source, self-hosted application for nonprofits (inventory, members, events) with a hosted Cloud edition. It is built as a small core plus removable modules. Read `docs/architecture.md` before adding or moving a feature, and `docs/plans/modular-architecture.md` for the current build-out status.

- Core is `app/`, `resources/`, `routes/`, `database/`. Each feature area is a module: a Composer package in `modules/<key>/` with namespace `Tessera\<Name>`.
- Core must never reference a module: no `Tessera\*` class, module route name, view, table, or config key. If core needs something from a module, add or use an extension point in `app/Modules/`.
- Modules use core only through its public surface (`Team`, `User`, roles and permissions, the app layout, `app/Modules/` contracts). A module may use another module only when it declares that dependency in its `composer.json` and manifest.
- A module owns its tables, prefixed with its key (`inventory_items`), and never alters core tables or another module's tables. Every tenant-owned row has a `team_id`.
- The app must boot and pass the core test suite with zero modules installed. A module's tests live in `modules/<key>/tests`.
- A module's third-party dependencies go in the module's `composer.json`, not the root one.
- Never use Flux Pro components or any dependency a self-hoster would have to pay for. Never require an external service; local drivers must work.
- Never branch on edition (`if cloud`). Cloud-only behaviour lives in the private `tessera/cloud` package and binds its own implementation of a core contract.
- Cloud is one multi-tenant deployment on Laravel Cloud. Treat tenant isolation as a security boundary: scope every tenant query by `team_id`, authorize route-bound models against the current organization, and test that one organization cannot reach another's records.
- The filesystem is ephemeral on Cloud. Store uploads through `Storage` on the configured disk and get URLs from the disk, so both object storage and `local` work.
- Migrations must be safe to run while the previous release is still serving: add before use, remove a release after last use.
- No feature is Cloud-only. Hosting is the paid service.
- The project is licensed AGPL-3.0.
- `Team` in code is "organization" in all user-facing copy. There are no personal teams.
- Disabling or deactivating a module never deletes data. Only `module:purge` does.
- A module's service provider extends `App\Modules\Module`; ask the `App\Modules\Facades\Modules` facade whether a module is enabled. `tests/Fixtures/Modules/Example` is the reference module, and tests install modules with `installModules()`.
- When one module would be better with another but works without it, do not reference it. Put a contract in `App\Modules\Contracts`, have the owner return it from `capabilities()`, and have the consumer call `Modules::capability(Contract::class, $team)` and handle `null`. Test the consumer against a stand-in in `tests/Fixtures/Modules`, not the real module.
- Start a module with `php artisan make:module "Name"`. `tests/Feature/ArchTest.php` enforces the module boundary; do not weaken it to make a change pass.
- `modules/inventory` is the reference for a real module. Module tests create people with `ownerWithModules('inventory')` and `teammate($team, $role)`.
- Get a URL for an uploaded file from `App\Modules\StoredFile::url($path)`, never `Storage::url()` or `temporaryUrl()` directly. Use `<x-file-input>` for file uploads.
- Scope a module's queries by organization at the start (`Item::forTeam($team)`), validate ids from the browser against that organization, and authorize every Livewire action; route middleware does not run on Livewire updates.
- Pass `current_team` explicitly to `route()` inside Livewire components.
- Module permissions are strings prefixed with the module key (`inventory:view`), checked with `$user->hasTeamPermission($team, 'inventory:view')`.
- Single-organization mode (`tessera.single_organization`) is the self-hosted default: registration needs a pending invitation and a second organization cannot be created. Show or hide organization creation with `@can('create', Team::class)`, never by reading the setting in a view.
- Configuration and routes are cached in production. Call `env()` only in `config/`. A setting a self-hoster may change goes in `config/tessera.php`, `.env.example`, `compose.yaml`, and `docs/self-hosting/configuration.md`.
- `php artisan tessera:upgrade` must be the only step after new code is in place. If a change needs anything else at upgrade time, do it in a migration or add it to that command.
- A PHP extension a module needs goes in that module's `composer.json` and in the `Dockerfile`.
- Every module implements `exporting()` and `purging()` for everything it stores, and keeps its files under `Team::storageDirectory($id).'/<key>'`. Deleting an organization is a soft delete; `PurgeTeam` is the only permanent deletion and `ExportTeam` the only export. Loop over `Modules::holdingData()`, not `installed()`, when touching module tables.
- New feature area and unsure where it goes: make it a module.
