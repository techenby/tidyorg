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
- Module permissions are strings prefixed with the module key (`inventory:view`), checked with `$user->hasTeamPermission($team, 'inventory:view')`.
- New feature area and unsure where it goes: make it a module.
