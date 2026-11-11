# Install with Docker

`compose.yaml` runs everything Tidy Org needs: the web server, a queue worker, the scheduler, and a PostgreSQL database. The image is built with every module and with the front-end files already compiled.

You need Docker with the Compose plugin, or Podman with a Compose provider.

## 1. Get the files

Download and extract a release archive, or clone the repository, and open a terminal in that directory. It contains `compose.yaml` and the `Dockerfile`.

## 2. Create the settings file

Create a file named `.env` next to `compose.yaml`. These two commands write the two secrets it needs:

```sh
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "DB_PASSWORD=$(openssl rand -hex 24)" >> .env
```

Then add the address people will use to open Tidy Org:

```
APP_URL=https://tidyorg.example.org
```

Keep a copy of this file somewhere safe. Without `APP_KEY`, two-factor sign-in secrets in a backup cannot be read.

## 3. Start Tidy Org

```sh
docker compose up -d --build
```

The first run builds the image, which takes several minutes. Once it is up, Tidy Org creates its database tables and listens on port 8080.

## 4. Create your account

```sh
docker compose exec app php artisan tidyorg:install
```

It asks for your name, email address, a password, and the name of your organization. Then open the address from step 2 and sign in.

## 5. Put it behind HTTPS

The container speaks plain HTTP on port 8080. For anything beyond a test on your own computer, put a reverse proxy that handles HTTPS in front of it, such as Caddy, nginx, or Traefik, and add this to `.env`:

```
TIDYORG_TRUSTED_PROXIES=*
```

Then run `docker compose up -d` again. See [configuration.md](configuration.md#address-and-https) for what this setting does.

## Settings

These go in the `.env` file next to `compose.yaml`. Run `docker compose up -d` after changing any of them.

| Setting | Default | What it does |
| --- | --- | --- |
| `APP_KEY` | none, required | Encrypts sessions and secrets. Never change it on a running install. |
| `DB_PASSWORD` | none, required | Password for the bundled database. Changing it later does not change the password inside an existing database. |
| `APP_URL` | `http://localhost:8080` | The public address. |
| `TIDYORG_PORT` | `8080` | The port on the host. |
| `TIDYORG_SINGLE_ORGANIZATION` | `true` | See [configuration.md](configuration.md#one-organization-or-several). |
| `TIDYORG_MODULES` | `*` | See [modules.md](modules.md). |
| `TIDYORG_TRUSTED_PROXIES` | empty | See step 5. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | mail is written to the log | See [configuration.md](configuration.md#outgoing-mail). |

## Everyday commands

```sh
docker compose logs -f app        # what the web server is doing
docker compose logs -f queue      # email and other background work
docker compose exec app php artisan module:list
docker compose down               # stop; your data is kept
```

Your data lives in two Docker volumes: `database` and `uploads`. `docker compose down -v` deletes both.

Next: [back up](backups.md) and [upgrade](upgrading.md).
