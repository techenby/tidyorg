# Self-hosting Tessera

Tessera runs on your own server at no cost. Everything in this repository works when self-hosted, and nothing needs a paid licence or an outside service.

There are two ways to run it. Pick one:

| | Docker | On a server with PHP |
| --- | --- | --- |
| You need | Docker with Compose | PHP 8.4 or newer, a web server, and a way to run a background process |
| Database | PostgreSQL, included | SQLite (nothing to install), PostgreSQL, MySQL, or MariaDB |
| Upgrades | Pull the new image and restart | Extract the new release, run one command |
| Guide | [docker.md](docker.md) | [server.md](server.md) |

If you have no preference, use Docker. It bundles the web server, the database, and the background jobs.

## After installing

- [configuration.md](configuration.md): your address and HTTPS, outgoing mail, where uploads are kept, and whether the install serves one organization or several.
- [modules.md](modules.md): choosing which modules are available, and removing one completely.
- [backups.md](backups.md): what to back up and how to restore it.
- [upgrading.md](upgrading.md): moving to a new version.

## What an install looks like

A self-hosted install normally serves one organization. The installer creates that organization and its first administrator. Everyone else joins by invitation, and nobody can sign up on their own.

Tessera sends invitations and password resets by email, so set up [outgoing mail](configuration.md#outgoing-mail) before inviting anyone.
