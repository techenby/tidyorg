# Install on a server with PHP

This guide installs Tessera from a release archive. The archive already contains the PHP libraries and the built front-end files, so the server needs neither Composer nor Node.

## What the server needs

- PHP 8.4 or newer with these extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer`, `xml`, and the PDO driver for your database (`pdo_sqlite`, `pdo_pgsql`, or `pdo_mysql`).
- The `gd` and `exif` extensions are recommended. Without them, uploaded photos are kept at full size and are not rotated.
- A web server that can run PHP, such as Caddy or nginx with PHP-FPM.
- A database. SQLite needs nothing installed and suits a single organization. PostgreSQL, MySQL, and MariaDB also work.
- Shell access, to run the installer and to keep one background process running.

The installer checks the extensions and tells you which are missing.

## 1. Put the files in place

Download `tessera-<version>.tar.gz` from the project's releases page and extract it where your sites live:

```sh
mkdir -p /var/www/tessera
tar -xzf tessera-<version>.tar.gz --strip-components=1 -C /var/www/tessera
cd /var/www/tessera
```

The web server's user must be able to write to `storage/`, `bootstrap/cache/`, and, when you use SQLite, `database/`. On most Linux servers that user is `www-data`:

```sh
chown -R www-data:www-data storage bootstrap/cache database
```

## 2. Run the installer

Run it as the same user the web server uses, so the files it creates are writable later:

```sh
sudo -u www-data php artisan tessera:install
```

The installer asks for:

1. The address people will use to open Tessera, for example `https://tessera.example.org`.
2. The database. For anything other than SQLite, create an empty database and a user for it first; the installer tests the connection before going on.
3. Whether the install serves one organization. Answer yes unless you plan to host several.
4. Which modules you want.
5. Your name, email address, a password, and the name of your organization.

It writes the answers to `.env`, creates the database tables, and creates your account. You can run it again safely: it never creates a second administrator.

To run it from a script, pass the answers as options and add `--no-interaction`. Without a `.env` file it then uses SQLite. See `php artisan tessera:install --help`.

## 3. Point the web server at it

Serve the `public/` directory, never the directory above it. Send every request that is not a file to `public/index.php`.

With Caddy, which also sets up HTTPS for you:

```
tessera.example.org {
	root * /var/www/tessera/public
	encode zstd gzip
	php_fastcgi unix//run/php/php8.4-fpm.sock
	file_server
}
```

For nginx, use the server block from the [Laravel deployment guide](https://laravel.com/docs/deployment#nginx) with `root` set to `/var/www/tessera/public`.

Uploaded photos can be up to 5 MB. Check that `upload_max_filesize` and `post_max_size` in `php.ini`, and `client_max_body_size` in nginx, allow at least that.

## 4. Keep the background work running

Tessera sends email from a queue and tidies up once a day. Both need a little setup.

Add one line to the web server user's crontab (`sudo crontab -u www-data -e`):

```
* * * * * cd /var/www/tessera && php artisan schedule:run >> /dev/null 2>&1
```

Keep one queue worker running. With systemd, save this as `/etc/systemd/system/tessera-queue.service`:

```ini
[Unit]
Description=Tessera queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/tessera
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

Then start it:

```sh
sudo systemctl enable --now tessera-queue
```

## 5. Sign in

Open the address you gave the installer and sign in with your email address and password. Next, set up [outgoing mail](configuration.md#outgoing-mail) so you can invite others.

## Installing from a git checkout instead

Developers can clone the repository instead of using the archive. That route needs Composer and Node:

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan tessera:install
```
