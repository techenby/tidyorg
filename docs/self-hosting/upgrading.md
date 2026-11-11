# Upgrading

[Back up](backups.md) before every upgrade.

Read the release notes for the version you are moving to. Upgrade one release at a time unless the notes say you can skip.

## With Docker

```sh
docker compose pull
docker compose up -d
```

The new container updates the database before it starts serving. If you build the image yourself, run `git pull` and `docker compose build` in place of `docker compose pull`.

## On a server install

1. Download the new `tessera-<version>.tar.gz` and extract it over the existing install. Your `.env`, uploads, and SQLite database are not in the archive, so they are left alone:

   ```sh
   tar -xzf tessera-<version>.tar.gz --strip-components=1 -C /var/www/tessera
   ```

2. Finish the upgrade as the web server's user:

   ```sh
   cd /var/www/tessera
   sudo -u www-data php artisan tessera:upgrade
   ```

`tessera:upgrade` shows visitors a maintenance page, updates the database, rebuilds the caches, restarts the queue worker, and brings Tessera back.

If a step fails, Tessera stays on the maintenance page so nobody uses a half-upgraded install. The command says which step failed and why. Fix the cause and run it again. To bring Tessera back without finishing, run `php artisan up`.

A git checkout upgrades the same way, with these in place of step 1:

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```
