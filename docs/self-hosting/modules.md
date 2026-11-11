# Choosing and removing modules

Tidy Org ships with all of its modules. You decide which ones your install offers.

## See what is installed

```sh
php artisan module:list
```

With Docker, put `docker compose exec app` in front of every `php artisan` command on this page.

## Choose which modules are available

Set `TIDYORG_MODULES` in `.env`:

```
TIDYORG_MODULES=*
```

`*` means every installed module. To offer only some, list their keys:

```
TIDYORG_MODULES=inventory,members
```

Then apply the change. On a server install:

```sh
php artisan tidyorg:upgrade
```

With Docker, `docker compose up -d` does the same.

A module that is left out has no pages, no sidebar entry, and creates no database tables. If it was in use before, its data stays where it is and comes back when you list the module again.

Some modules use others when they are available. Inventory, for example, offers check-out to people only while Members is on. Each module works without the others.

## Turn a module on or off for one organization

This applies only when the install hosts several organizations. An owner or admin uses **Settings → Organizations → Modules**, or from the command line:

```sh
php artisan module:enable inventory --team=riverside-food-bank
php artisan module:disable inventory --team=riverside-food-bank
```

Turning a module off hides it and keeps its data.

## Remove a module completely

Leaving a module out of `TIDYORG_MODULES` hides it but keeps its data. To delete the data as well:

1. [Back up](backups.md) first. This cannot be undone.
2. Delete the module's records, files, and database tables:

   ```sh
   php artisan module:purge inventory
   ```

   It asks for confirmation. When the install hosts several organizations it refuses unless you add `--force`, or you can delete one organization's data and keep the tables with `--team=<slug>`.

3. Take the module out of `TIDYORG_MODULES` and apply the change as above. If you skip this step, the module's empty tables are created again.

The module's code stays on disk, unused. That is harmless, and it keeps upgrades simple.
