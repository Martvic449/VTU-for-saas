# Hostinger private Laravel deployment

This repository is a Laravel 12 application. Deploy the **application root** outside the domain's public document root. Do not deploy the repository root to `public_html`.

## Target layout

For `net.kokaacademyltd.com`, the intended layout is:

```text
/home/ACCOUNT/domains/net.kokaacademyltd.com/
├── laravel-app/                 # Hostinger Git deployment target
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── storage/
│   ├── vendor/
│   ├── .env                     # created on Hostinger only; never committed
│   ├── artisan
│   └── composer.json
└── public_html -> laravel-app/public
```

The `public_html` symlink exposes only Laravel's public directory. The Git working tree, `.env`, `vendor`, application code, database files and logs remain outside the web root.

Hostinger's Git integration supports selecting a deployment root directory. Its help article says the default is `public_html` and that another valid directory can be selected. Hostinger web hosting does not let you change the account's home directory; this deployment uses a sibling application directory and a public symlink instead.

References:
- https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/
- https://www.hostinger.com/support/1583494-what-is-the-path-to-your-website-s-root-home-directory-and-how-to-change-it/

## Important: pause the current deployment before changing its target

The current Hostinger Git integration points to `public_html`. Turn **Auto-deployment OFF** before merging deployment changes into `main` or changing the target. Otherwise, a push to `main` will redeploy the full Laravel repository into the current public directory.

Do not click Redeploy while the target is still `public_html`.

## First-time Hostinger setup

1. Back up the current `public_html` directory before moving or renaming anything. Keep the backup outside the public web root if possible.
2. In Hostinger File Manager, choose **Access all files of your web hosting**, then navigate to `domains/net.kokaacademyltd.com/` (the directory containing `public_html`).
3. Create a sibling directory named `laravel-app`. Do not create it inside `public_html`.
4. In hPanel → Websites → Manage site → Advanced → Git, change the repository root directory from `public_html` to the valid account-relative path `domains/net.kokaacademyltd.com/laravel-app`. If Hostinger rejects that path, stop and contact us with a screenshot; do not use `public_html/laravel-app` as a substitute for the private directory.
5. Deploy the repository to that directory. Confirm the repository files, including `artisan` and `composer.json`, are inside `laravel-app`.
6. Enable SSH in hPanel if it is not already enabled. Connect to the account and navigate to the domain directory. Use the exact home path shown in Hostinger's FTP/SSH details.
7. Confirm `laravel-app/public/index.php` and `laravel-app/public/.htaccess` exist.
8. Rename the old `public_html` to a backup name only after confirming the backup is complete. Then create a symlink so `public_html` points to `laravel-app/public`.

Example commands (run only after checking the current directory and backup):

```bash
cd ~/domains/net.kokaacademyltd.com
pwd
ls -la
# Verify laravel-app exists and contains artisan before proceeding.
test -f laravel-app/artisan
test -f laravel-app/public/index.php
test -f laravel-app/public/.htaccess

# Preserve the current web root; do not delete it.
mv public_html public_html_legacy_backup
ln -s laravel-app/public public_html

# Verify the link and its target.
readlink public_html
test -f public_html/index.php
```

If the symlink command is rejected or the website does not serve through it, stop. Restore the previous web root with:

```bash
rm public_html
mv public_html_legacy_backup public_html
```

Do not run the restore command if `public_html` is a real directory containing new data; inspect it first. Do not use `rm -rf`.

## Configure production secrets privately

Create `laravel-app/.env` by copying `laravel-app/.env.example` inside the private application directory. Never upload the real file to GitHub, paste it into chat, or put it in `public_html`.

At minimum, set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://net.kokaacademyltd.com`
- Hostinger's exact MySQL host, database name, database username and password
- Production-safe cache, session, queue and mail settings
- Sandbox provider credentials until each integration is separately approved

Use the exact DB host/name/user Hostinger displays. Do not use MySQL `root`. Keep the file owner-readable only where supported (typically mode `600`); directories should be owner-writable and not world-writable. Never use `777`.

Keep `.env` out of Git. The repository's `.gitignore` already excludes `.env` and other environment files.

## Install and prepare Laravel (SSH)

Run from the private `laravel-app` directory after `.env` is configured:

```bash
cd ~/domains/net.kokaacademyltd.com/laravel-app

mkdir -p bootstrap/cache \
  storage/app/private storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

chmod -R u+rwX bootstrap/cache storage

composer install --no-dev --prefer-dist --optimize-autoloader
```

A committed `composer.lock` is required before production installation. Do not run `composer update` on the server.

Generate the application key once only, after the production `.env` exists:

```bash
php artisan key:generate
```

Preserve that key for all future deployments. Never regenerate it on routine deploys.

Before database migrations, verify the database is the intended production database and take a backup. Then run:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not run seeders until their contents and the production administrator/bootstrap process have been reviewed. Do not seed demo data.

## Security checks after setup

- Opening `https://net.kokaacademyltd.com/.env` must return 403 or 404, never file contents.
- `https://net.kokaacademyltd.com/vendor/`, `/app/`, `/storage/logs/` and `/.git/` must not expose files.
- `public_html` must resolve to `laravel-app/public`.
- `laravel-app/.env` must exist only in the private application directory.
- `APP_DEBUG=false` in production.
- Do not enable live payments or VTU purchases until queue workers, scheduler, sandbox tests, security checks, legal/compliance and financial acceptance tests are complete.

## Deployment updates

Hostinger Git auto-deployment updates the private `laravel-app` working tree. Because `public_html` is a symlink to `laravel-app/public`, public assets and the public entry point update with the same deployment. Keep the symlink intact.

Do not let Hostinger's Git root point back to `public_html`. Do not store production secrets or runtime logs in GitHub.
