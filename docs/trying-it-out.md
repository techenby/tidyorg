# Trying Tidy Org

This gets Tidy Org running on your own computer with a sample account, so you can look around before deciding anything. Nothing here is sent anywhere, and you can delete the folder when you are done.

Tidy Org is in early development. Expect rough edges, and do not keep real records in a copy set up this way. To run it for an organization, follow the [self-hosting guides](self-hosting/README.md) instead.

## What you need

- PHP 8.4 or newer, with the SQLite extension
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) 22 or newer
- Git

If you would rather not install PHP and Node, the [Docker guide](self-hosting/docker.md) runs everything in containers.

## Set it up

```sh
git clone https://github.com/techenby/tidyorg.git
cd tidyorg
composer setup
php artisan db:seed
```

`composer setup` installs the dependencies, creates a settings file (`.env`) and a SQLite database in `database/database.sqlite`, and builds the interface. `php artisan db:seed` adds a sample account with an organization of its own.

## Start it

```sh
composer dev
```

This starts the web server, the background worker and a live view of the log. Leave it running and open <http://localhost:8000>. Press Ctrl+C in the terminal to stop it.

## Sign in

| | |
| --- | --- |
| Email | `test@example.com` |
| Password | `password` |

You can also register a new account from the sign-in page. Email is not really sent in this setup. Messages such as the "verify your email address" link are written to `storage/logs/laravel.log` and shown in the terminal running `composer dev`, and you can copy the link from either.

## Turn on the modules

A new organization starts with no modules switched on. Go to **Settings → Organizations**, open the organization, and choose **Modules**. Turn on Inventory, Members, or both, and they appear in the sidebar.

What each one does is described in the user guides:

- [Inventory](users/inventory.md)
- [Members](users/members.md)

## Things to try

- Add a few inventory items, give one a photo, and put one item inside another.
- Add people to the Members directory, then check an inventory item out to one of them. This only appears when both modules are on.
- Invite someone to the organization from its settings, and open the invitation link from the log file in a private browser window.
- Create a second organization from the switcher at the top of the sidebar, and see that it has its own modules and its own data.
- Export the organization's data from its settings. The download link arrives as one of those logged emails.

## Run the tests

```sh
composer test
```

## Start again or remove it

To go back to an empty database with the sample account:

```sh
php artisan migrate:fresh --seed
```

To remove everything, delete the `tidyorg` folder.

## Telling us what you find

Bugs and suggestions are welcome as [issues on GitHub](https://github.com/techenby/tidyorg/issues).
