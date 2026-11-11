# Configuration

Settings live in the `.env` file in Tidy Org's directory. With Docker they live in the `.env` file next to `compose.yaml` instead.

After changing a setting on a server install, rebuild the cached configuration:

```sh
php artisan optimize
```

With Docker, restart instead: `docker compose up -d`.

## Address and HTTPS

`APP_URL` is the address people use to open Tidy Org. Links in emails and QR codes are built from it, so it must be the real public address, including `https://`.

When a reverse proxy or load balancer handles HTTPS in front of Tidy Org, tell Tidy Org to trust it. Otherwise it builds `http://` links and pages may fail to load their styles.

```
TIDYORG_TRUSTED_PROXIES=*
```

`*` trusts whichever proxy passed the request on. Use it only when Tidy Org cannot be reached except through the proxy. Otherwise list the proxy's addresses, separated by commas.

## Outgoing mail

Tidy Org emails invitations, address confirmations, and password resets. Until mail is set up, those messages are only written to the log and nobody receives them.

Any SMTP service works, including the one that comes with most email hosting:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=tidyorg@example.org
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=tidyorg@example.org
MAIL_FROM_NAME="Riverside Food Bank"
```

The account the installer creates is already confirmed, so you can sign in before mail works. People you invite must confirm their address by email.

## One organization or several

```
TIDYORG_SINGLE_ORGANIZATION=true
```

With this on, which is what the installer sets unless you say otherwise:

- Nobody can register without an invitation.
- Nobody can create a second organization.
- Every available module is on for the organization, with no switches to manage.
- The home page goes straight to sign-in.

Turn it off to host several organizations in one install. Anyone can then register and create an organization, and each organization's admins choose its modules under **Settings → Organizations → Modules**.

## Uploaded files

By default uploads are kept on the server in `storage/app/private`. Include that directory in your [backups](backups.md).

To keep uploads in S3-compatible object storage instead, a server install needs one extra library (`composer require league/flysystem-aws-s3-v3`), and then:

```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_ENDPOINT=https://...
```

`AWS_ENDPOINT` is only needed for services other than Amazon S3. The Docker image does not include this library.

## Deleted organizations

When an owner deletes an organization, it is closed straight away and its data is kept for 30 days before being permanently deleted. Change the delay with:

```
TIDYORG_CLOSED_ORGANIZATION_DAYS=30
```

To delete one immediately, or to take a copy of everything an organization has stored:

```sh
php artisan organization:purge riverside-food-bank
php artisan organization:export riverside-food-bank
```

The export is a zip file in `storage/app/private/teams/<number>/exports`. It is removed after a week.

## Source code link

Tidy Org is licensed under the AGPL. If you change its code and let other people use your version, the licence requires you to offer them your changes. Publish your version and set its address, and Tidy Org links to it from the sign-in page and the sidebar:

```
TIDYORG_SOURCE_URL=https://example.org/our-tidyorg
```

## Modules

`TIDYORG_MODULES` chooses which modules the install offers. See [modules.md](modules.md).
