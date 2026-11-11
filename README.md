# Tidy Org

Open-source, self-hosted software that helps nonprofits manage their inventory, members, events, and other day-to-day operations.

Tidy Org is built as a small core plus modules. An organization turns on only the modules it uses, and a self-hosted install can leave out the rest entirely. See [docs/architecture.md](docs/architecture.md) for how the codebase is structured and [docs/plans/modular-architecture.md](docs/plans/modular-architecture.md) for what is being built.

Tidy Org is in early development and is not ready for production use.

## Trying it

To run Tidy Org on your own computer with a sample account, see [docs/trying-it-out.md](docs/trying-it-out.md).

## Self-hosting

Tidy Org runs with Docker or on any server with PHP 8.4 or newer. See [docs/self-hosting](docs/self-hosting/README.md).

## Local development

Requires PHP 8.4 or newer, Composer, and Node.

```sh
composer setup
composer dev
```

Run the checks with `composer test`.

## Licence

Tidy Org is licensed under the [GNU Affero General Public License v3.0 or later](LICENSE.md). You may run, study, and modify it. If you offer a modified version to others over a network, you must make your changes available to them under the same licence.
