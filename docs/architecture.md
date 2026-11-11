# Tessera architecture

Tessera is an open-source, self-hostable application that helps nonprofits manage their inventory, members, events, and similar day-to-day operations. A hosted version (Tessera Cloud) runs the same code for organizations that would rather not run a server.

This document is the standing guide for how the codebase is shaped. Read it before adding a feature, and update it when a decision here changes. The step-by-step build-out lives in [plans/modular-architecture.md](plans/modular-architecture.md).

## Goals

1. **An organization only carries what it uses.** Inventory, members, events, and every future feature area is a module. A module that is switched off has no routes, navigation, scheduled work, or database tables. A module that is not installed has no code on disk.
2. **Self-hosting is a first-class way to run Tessera.** A volunteer with basic server skills can install it, pick modules, and upgrade without a build pipeline or a paid licence for anything.
3. **Cloud is the same product, hosted.** Hosting is the service, not the features. Cloud adds billing, provisioning, and operations around the open-source app. It does not fork it and it has no exclusive features.
4. **The structure is enforced by tests, not by memory.** The rules below are backed by architecture tests and CI jobs so that a contributor or agent who has not read this document still cannot break them silently.

## Vocabulary

| Term | Meaning |
| --- | --- |
| **Core** | Everything in `app/`, `resources/`, `routes/`, `database/`: accounts, organizations, roles, settings, layout, and the module system itself. Always present. |
| **Module** | A self-contained feature area (Inventory, Members, Events) shipped as a Composer package under `modules/`. |
| **Installed** | The module's package is present in `vendor/` and autoloadable. Decided per deployment, by Composer. |
| **Active** | The module is switched on for this deployment. Its service provider boots, its migrations run. Decided per deployment, by configuration. |
| **Enabled** | The module is turned on for a particular organization. Decided per organization, in the database. |
| **Organization** | The tenant. In code this is the starter kit's `Team` model; in the UI it is always called an organization. |
| **Edition** | *Community* (this repository, self-hosted) or *Cloud* (this repository plus the private cloud package). |

Installed ⊇ active ⊇ enabled. Each level narrows the one before it.

## The three switches

| Level | Scope | Controlled by | Effect when off |
| --- | --- | --- | --- |
| Installed | Deployment | `composer require` / `composer remove`, or which modules a Docker image is built with | No code, no third-party dependencies of that module |
| Active | Deployment | `TESSERA_MODULES` in `.env` (default: every installed module) | Provider does nothing: no routes, nav, policies, schedule, or migrations |
| Enabled | Organization | Organization settings → Modules | Routes return 404 for that organization, nav entries and dashboard widgets are hidden |

A typical self-hosted install has one organization and uses the first two switches. Cloud installs and activates everything and relies on the third, with plan entitlements deciding which modules an organization may enable.

## Repository layout

```
app/                     Core
app/Modules/             The module system: base provider, registry, middleware, contracts
modules/
  inventory/             One Composer package per module
    composer.json        name: tessera/inventory, type: tessera-module
    src/                 Tessera\Inventory\ — models, actions, policies, enums, InventoryModule provider
    database/            migrations, factories, seeders
    resources/views/     Livewire pages and Blade components
    routes/web.php
    lang/
    tests/
docs/                    This guide, plans, and later user documentation
```

The root `composer.json` declares `modules/*` as a path repository and requires each first-party module. Removing a module from a deployment is `composer remove tessera/<module>`; its own third-party dependencies leave with it. That is the reason modules are Composer packages rather than folders under `app/`: Composer is the only thing that can remove a feature's dependencies along with the feature.

No module framework package (nwidart/laravel-modules, internachi/modular) is used. Path repositories plus Laravel's package discovery already provide everything needed, with nothing extra to keep compatible.

## Dependency rules

These are the rules that keep modules removable. They are the most important part of this document.

1. **Core never references a module.** Nothing in `app/`, `resources/`, `routes/`, `database/`, or `tests/` may mention a `Tessera\*` class, a module's route name, view, table, or config key. If core needs something from a module, the module registers it through an extension point.
2. **Modules depend on core only through its public surface:** `Team`, `User`, the role and permission system, the app layout, Flux components, and the contracts in `app/Modules/`. Modules do not reach into core internals such as Fortify actions or settings pages.
3. **A module may depend on another module only if it declares it**, both in its `composer.json` `require` and in its manifest. Dependencies must be acyclic. Enabling a module for an organization requires its dependencies to be enabled.
4. **Prefer optional integration to hard dependency.** If Inventory can lend items to members when the Members module exists, Inventory checks `Modules::enabled('members')` and degrades cleanly when it is absent. The check lives in the module that benefits, never in core.
5. **A module owns its tables.** Table names are prefixed with the module key (`inventory_items`). A module's migrations never alter core tables or another module's tables. Foreign keys may point at core tables and at declared dependencies only.
6. **Every tenant-owned row has a `team_id`** and is reached through the organization. Policies check membership and permission; queries are scoped to the current organization.
7. **No edition checks.** There is no `if (cloud)` anywhere in core or modules. Cloud behaviour is supplied by binding a different implementation of a core contract.

Enforcement:

- Pest architecture tests: `App` must not use `Tessera`; each module may use only core and its declared dependencies.
- CI runs the core test suite with zero modules installed, and each module's suite with only that module and its dependencies.
- A test boots every combination of "module active / not active" that matters and asserts the route list and migration set match.

## Extension points

Core exposes a small, stable set of places where a module plugs in. A module's service provider extends `App\Modules\Module` and describes itself; the base class does the wiring only when the module is active.

| Extension point | What a module provides |
| --- | --- |
| Manifest | Key, name, description, icon, dependencies |
| Routes | A route file, automatically wrapped in the organization prefix, auth, membership, and `module:<key>` middleware |
| Navigation | Sidebar entries, shown only when enabled for the current organization and permitted for the user |
| Permissions | Named abilities (`inventory:view`, `inventory:manage`) with a default role for each |
| Dashboard widgets | Livewire components rendered on the organization dashboard |
| Settings | A settings page per module under organization settings |
| Views | A Livewire namespace (`inventory::`) pointing at the module's `resources/views` |
| Migrations, translations, commands, schedule | Loaded from the module directory |
| Lifecycle hooks | `enabling`, `disabled`, and `purging` callbacks per organization |
| Stored files | `App\Modules\StoredFile::url()` for a link to an upload on whichever disk is configured, and `<x-file-input>` for the upload control |

When a module needs something that no extension point offers, add an extension point to core. Do not special-case the module in core.

`tests/Fixtures/Modules/Example` is a complete, minimal module and the reference for how one is put together. `modules/inventory` is the reference for a real one. Tests install modules with the `installModules()` helper in `tests/Pest.php`.

### Permissions

The starter kit's `TeamPermission` enum cannot be extended from a package, so module permissions are strings registered with the module registry, each mapped to the minimum `TeamRole` that holds it by default. Core's own permissions stay in the enum. Policies in modules ask `User::hasTeamPermission()` in the same way core does.

## Data lifecycle

| Action | Data |
| --- | --- |
| Disable for an organization | Nothing is deleted. Re-enabling restores everything. |
| Deactivate for a deployment | Tables stay. Nothing reads or writes them. |
| Purge for an organization (`module:purge <key> --team=`) | Explicit, confirmed, destructive: deletes that organization's rows and stored files for the module. Tables stay. This is the only purge used on Cloud. |
| Purge for a deployment (`module:purge <key>`) | Explicit, confirmed, destructive: rolls back the module's migrations and deletes its stored files. Required before uninstalling if the tables should go. Self-hosted only. |
| Uninstall (`composer remove`) | Code and dependencies removed. Refuses while the module is active. |

Deleting data is never a side effect of flipping a switch.

## Editions

**Community** is this repository, complete, licensed AGPL-3.0. Every module here is fully functional when self-hosted. Nothing is held back or limited to push people toward Cloud. If that ever changes it is a deliberate product decision recorded here, not something a feature quietly introduces.

**Cloud** is this repository plus a private `tessera/cloud` package that plugs in through the same extension points and contracts as any module: subscription billing, plan limits, organization provisioning, and operator tooling. Core defines the contracts with permissive defaults (for example, `ModuleEntitlements` allows everything); the cloud package rebinds them. Because the cloud package runs in the same process as AGPL code, assume its source may have to be offered to Cloud users, and keep secrets and anything commercially sensitive in configuration, not code.

### How Cloud is hosted

Paying customers are hosted on [Laravel Cloud](https://cloud.laravel.com/) as **one multi-tenant deployment** in which each customer is an organization. There is no instance per customer. Every first-party module is installed and active; organizations choose what to enable.

This shapes core and every module, whether or not a given change is "for Cloud":

- **The filesystem is ephemeral.** Anything a user uploads goes through Laravel's `Storage` on the configured disk, which is object storage on Cloud and may be `local` when self-hosted. Never write user data to a path on disk, and never build a URL to a file by hand; ask the disk for it so both drivers work.
- **Tenant isolation is a security boundary.** Organizations on Cloud are strangers to each other. Every query for tenant data is scoped by `team_id`, every route-bound model is authorized against the current organization, and every module's tests include a case proving one organization cannot read or change another's records.
- **Deploys are zero-downtime, so migrations must be compatible with the code already running.** Add columns and tables before the code that needs them; remove them only in a later release than the code that stopped using them.
- **No deployment-wide destructive commands.** On Cloud, purging is per organization. Dropping a module's tables is a self-hosted operation.
- **Work that takes time goes on the queue; recurring work goes on the scheduler.** Web processes may be stopped or scaled at any time, so nothing may rely on in-memory state or a long-lived request.
- **PostgreSQL is the Cloud database** and therefore the primary one in CI. SQLite and MySQL/MariaDB remain supported for self-hosters.
- **The Cloud deployment is built from a private deployment repository** that tracks this one and adds only the `tessera/cloud` requirement and its lock entry. This repository never mentions the cloud package.

Self-hosted deployments are multi-tenant-capable but normally hold one organization.

## Constraints that follow from being open source

- **No dependency that a self-hoster must pay for or cannot obtain.** In particular, Flux Pro components cannot be used in core or first-party modules, because `composer install` would fail without a licence. Only the free `livewire/flux` package is allowed.
- **No required external service.** Mail, storage, queues, and cache must work with Laravel's local drivers. S3, Redis, and similar are optional.
- **SQLite, MySQL/MariaDB, and PostgreSQL are all supported.** Avoid driver-specific SQL; where it is unavoidable, cover each driver in tests.
- **Upgrades are `git pull` (or a new image), `composer install`, `php artisan migrate`.** Migrations are forward-only and never edited after release.
- **All user-facing strings go through `__()`** so translations can be contributed per module.

## Adding a feature: where does it go?

- It serves every organization regardless of what they use Tessera for (accounts, roles, audit log, notifications plumbing, file storage helpers) → **core**.
- It is a distinct area some organizations will not want → **a module**. When unsure, make it a module; promoting to core later is easy, extracting from core is not.
- It only makes sense for the hosted service (billing, plan limits, support tooling) → **the cloud package**, behind a core contract.
- It connects two modules → it lives in the module that depends on the other, guarded by an enabled check.

## Decisions

Settled on 2026-11-10:

1. **Licence: AGPL-3.0**, the same as Snipe-IT. Anyone may self-host and modify Tessera; anyone offering a modified version as a service must publish their changes.
2. **No personal teams.** A user belongs to zero or more organizations. The starter kit's personal-team behaviour is removed in phase 0.
3. **Full set first, slim later.** Tessera ships with every first-party module installed and uses the activation switch to choose among them. `composer remove` works for those who want the code gone. Packagist-published modules or per-module Docker builds wait until self-hosters ask.
4. **Hosting is the service, not the features.** No module or capability is Cloud-only at this time.
5. **Cloud runs on Laravel Cloud** as a single multi-tenant deployment.

Still open:

1. **Per-organization enablement on self-hosted.** A single-organization install does not need the third switch in its UI. Recommendation: keep the mechanism everywhere, hide the screen when the deployment is in single-organization mode.
