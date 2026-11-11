# Backups and restoring

A complete backup is three things:

1. **The database.** Every record.
2. **Uploaded files.** Photos and anything else people upload.
3. **Your settings**, above all `APP_KEY`. Two-factor sign-in secrets are encrypted with it and cannot be read without it.

Keep backups somewhere other than the server, and try restoring one before you need to.

## On a server install

Settings: copy `.env`.

Uploaded files: copy the `storage/app` directory.

```sh
tar -czf tessera-files.tar.gz -C /var/www/tessera storage/app
```

Database, depending on which one you use:

```sh
# SQLite
sqlite3 /var/www/tessera/database/database.sqlite ".backup '/backups/tessera.sqlite'"

# PostgreSQL
pg_dump --username tessera --format custom --file /backups/tessera.dump tessera

# MySQL or MariaDB
mysqldump --user tessera --password --single-transaction tessera > /backups/tessera.sql
```

For SQLite, use the `.backup` command as shown. Copying the file while Tessera is running can produce a damaged copy.

### Restore

1. Install the same version of Tessera you backed up, following [server.md](server.md) as far as extracting the files. Do not run the installer.
2. Put your `.env` back.
3. Restore the database:

   ```sh
   # SQLite
   cp /backups/tessera.sqlite /var/www/tessera/database/database.sqlite

   # PostgreSQL, into an empty database
   pg_restore --username tessera --dbname tessera /backups/tessera.dump

   # MySQL or MariaDB, into an empty database
   mysql --user tessera --password tessera < /backups/tessera.sql
   ```

4. Restore the files: `tar -xzf tessera-files.tar.gz -C /var/www/tessera`
5. Make sure the web server's user owns `storage`, `bootstrap/cache`, and `database`.
6. Run `php artisan tessera:upgrade`, which also brings an older backup's database up to date.

## With Docker

Settings: copy the `.env` file that sits next to `compose.yaml`.

Database:

```sh
docker compose exec -T db pg_dump --username tessera --format custom tessera > tessera.dump
```

Uploaded files:

```sh
docker compose exec -T app tar -czf - -C /app/storage app > tessera-files.tar.gz
```

### Restore

1. Put `compose.yaml` and your `.env` in place.
2. Start only the database, then load the backup into it:

   ```sh
   docker compose up -d db
   docker compose exec -T db pg_restore --username tessera --dbname tessera < tessera.dump
   ```

3. Start everything, then put the files back:

   ```sh
   docker compose up -d
   docker compose exec -T app tar -xzf - -C /app/storage < tessera-files.tar.gz
   ```
