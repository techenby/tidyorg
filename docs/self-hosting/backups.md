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
tar -czf tidyorg-files.tar.gz -C /var/www/tidyorg storage/app
```

Database, depending on which one you use:

```sh
# SQLite
sqlite3 /var/www/tidyorg/database/database.sqlite ".backup '/backups/tidyorg.sqlite'"

# PostgreSQL
pg_dump --username tidyorg --format custom --file /backups/tidyorg.dump tidyorg

# MySQL or MariaDB
mysqldump --user tidyorg --password --single-transaction tidyorg > /backups/tidyorg.sql
```

For SQLite, use the `.backup` command as shown. Copying the file while Tidy Org is running can produce a damaged copy.

### Restore

1. Install the same version of Tidy Org you backed up, following [server.md](server.md) as far as extracting the files. Do not run the installer.
2. Put your `.env` back.
3. Restore the database:

   ```sh
   # SQLite
   cp /backups/tidyorg.sqlite /var/www/tidyorg/database/database.sqlite

   # PostgreSQL, into an empty database
   pg_restore --username tidyorg --dbname tidyorg /backups/tidyorg.dump

   # MySQL or MariaDB, into an empty database
   mysql --user tidyorg --password tidyorg < /backups/tidyorg.sql
   ```

4. Restore the files: `tar -xzf tidyorg-files.tar.gz -C /var/www/tidyorg`
5. Make sure the web server's user owns `storage`, `bootstrap/cache`, and `database`.
6. Run `php artisan tidyorg:upgrade`, which also brings an older backup's database up to date.

## With Docker

Settings: copy the `.env` file that sits next to `compose.yaml`.

Database:

```sh
docker compose exec -T db pg_dump --username tidyorg --format custom tidyorg > tidyorg.dump
```

Uploaded files:

```sh
docker compose exec -T app tar -czf - -C /app/storage app > tidyorg-files.tar.gz
```

### Restore

1. Put `compose.yaml` and your `.env` in place.
2. Start only the database, then load the backup into it:

   ```sh
   docker compose up -d db
   docker compose exec -T db pg_restore --username tidyorg --dbname tidyorg < tidyorg.dump
   ```

3. Start everything, then put the files back:

   ```sh
   docker compose up -d
   docker compose exec -T app tar -xzf - -C /app/storage < tidyorg-files.tar.gz
   ```
