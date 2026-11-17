# Hostinger deployment (public_html Git target)

This repository is a Laravel 12 application. Hostinger's Git integration will deploy the repository root into `public_html`. The root `.htaccess` added for this deployment forwards web requests into Laravel's `public/` directory and denies HTTP access to application source, dependencies, logs, and repository metadata.

**Important security distinction:** this is a shared-hosting compatibility arrangement. Laravel's non-public files physically reside under `public_html`, but Apache/LiteSpeed access rules deny direct HTTP requests to those paths. A document root pointed directly at Laravel's `public/` directory, with the rest of the project outside the web root, remains the stronger architecture. Do not remove or bypass the root `.htaccess`.

## Target layout

For `net.kokaacademyltd.com`:

```text
/home/ACCOUNT/domains/net.kokaacademyltd.com/
├── private-config/
│   └── .env                 # production secrets; outside Git and public_html
└── public_html/             # Hostinger Git deployment root
    ├── .htaccess            # routes requests into public/ and blocks private paths
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/
    │   ├── .htaccess
    │   ├── index.php
    │   └── assets...
    ├── resources/
    ├── routes/
    ├── storage/
    ├── vendor/              # installed with Composer on Hostinger
    ├── artisan
    ├── composer.json
    └── composer.lock
```

The production environment file is deliberately outside the Git checkout and outside the web root. `bootstrap/app.php` detects `../private-config/.env` relative to the application root and loads it automatically. Local development and CI continue using the normal project-root `.env`.

## Before deploying

1. Keep Hostinger Auto-deployment **OFF** until the first deployment and verification are complete.
2. Back up the current `public_html` before deploying. Hostinger warns that deployments overwrite files in the selected target directory. Keep a copy outside `public_html` if possible.
3. Confirm the connected repository is `Martvic449/VTU-for-saas`, branch `main`, and root directory exactly `public_html`. Do not use the Node.js deployment flow.
4. The deployment replaces repository-tracked files with the `main` branch contents. Do not keep production secrets, uploaded user files, or manually maintained content inside `public_html`.

Hostinger's Git guide documents PHP/HTML Git deployment, the `public_html` default, custom root selection, manual redeploy, and overwrite behavior:
https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/

## Create the private production environment file

Use Hostinger File Manager. Navigate to the directory containing `public_html`:

1. Create a sibling folder named `private-config` (not inside `public_html`).
2. Inside it, create a file named exactly `.env`.
3. Copy the required variable names from `public_html/.env.example` and enter the real production values in `private-config/.env`.
4. Restrict folder/file permissions to the hosting account owner where the File Manager permits it (typically folder `700`, file `600`). Do not use `777`.

Never commit, upload into `public_html`, paste into chat, or share the real `.env`. Do not send database passwords or provider keys in screenshots.

Set at minimum:

- `APP_NAME` to the chosen configurable product name
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://net.kokaacademyltd.com`
- `APP_KEY` (generate once after dependencies are installed; preserve it thereafter)
- Hostinger's exact MySQL host, database name, username, and password
- Production-safe cache, session, queue, and mail settings
- Sandbox supplier/payment credentials only

Use the exact database host/name/user shown by Hostinger. Do not use MySQL root. Keep all secrets in the private file.

## Install Composer dependencies and prepare Laravel

Hostinger Git deployment transfers the repository; it does not install Composer dependencies or run Laravel migrations.

Connect with Hostinger SSH and run from the deployed project directory:

```bash
cd ~/domains/net.kokaacademyltd.com/public_html

mkdir -p bootstrap/cache \
  storage/app/private storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

chmod -R u+rwX bootstrap/cache storage

composer2 install --no-dev --prefer-dist --optimize-autoloader
```

If the `composer2` command is not found, check Hostinger's SSH/Composer documentation or the Composer binary shown in hPanel. Do not run `composer update`; the committed `composer.lock` fixes the dependency versions.

If Composer specifically reports that `proc_open` is disabled while executing scripts, use:

```bash
composer2 install --no-dev --prefer-dist --optimize-autoloader --no-scripts
php artisan package:discover --ansi
```

Do not broadly remove PHP disabled-function restrictions.

Generate the application key once, after `private-config/.env` exists:

```bash
php artisan key:generate
```

Preserve this key for every future deployment. Never regenerate it routinely.

Before running migrations, confirm the selected database is the intended database and take a backup. Then:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not run seeders until their contents and the production administrator bootstrap process have been reviewed. Do not seed demo data.

## Verify web-root protection before enabling customers

After deployment, open these paths in a private/incognito browser. They must return 403 or 404, never source contents:

- `https://net.kokaacademyltd.com/.env`
- `https://net.kokaacademyltd.com/.env.example`
- `https://net.kokaacademyltd.com/composer.json`
- `https://net.kokaacademyltd.com/composer.lock`
- `https://net.kokaacademyltd.com/artisan`
- `https://net.kokaacademyltd.com/vendor/`
- `https://net.kokaacademyltd.com/app/`
- `https://net.kokaacademyltd.com/storage/logs/`
- `https://net.kokaacademyltd.com/.git/`

The normal domain root must load Laravel, and Laravel's public assets must load without exposing the `public/` folder as the site URL.

If the site returns 403/500 after deployment, do not disable the protection rules or enable `APP_DEBUG` publicly. Check Hostinger's PHP error log and verify both root `.htaccess` and `public/.htaccess` exist.

## Operational restrictions

- Do not enable live VTU purchases or live deposits until the database, queue worker, scheduler, supplier/payment sandbox tests, reconciliation, security, legal/compliance, and financial acceptance checks are complete.
- A cron scheduler is not a durable queue worker. Verify Hostinger's supported process/queue options before processing financial transactions.
- Keep user uploads and other runtime data in persistent storage locations that are backed up and are not overwritten by Git deployments.
- Keep Auto-deployment OFF until the first manual deployment and smoke/security checks succeed. Re-enable it only after confirming deployments do not erase runtime data or the private environment file.
