# Hostinger import correction and deployment checklist

## Why Hostinger complained about `package.json`

This repository is a Laravel/PHP application. Its root contains `artisan` and `composer.json`. It does not currently contain `package.json`, `vite.config.*`, or a frontend build configuration. That is not, by itself, a defect: this source tree does not currently declare a Node/Vite asset build.

The `package.json` warning means the repository was likely selected in Hostinger's Node.js Web App import flow. Do not create an empty or fake `package.json`, and do not choose the static-website fallback.

Use Hostinger's custom PHP Git deployment flow instead:

1. Open hPanel → Websites → Manage site.
2. Open Advanced → Git.
3. Connect GitHub and authorize the repository.
4. Select `Martvic449/VTU-for-saas`, branch `main`.
5. Do not deploy the complete Laravel project into the public document root.
6. Keep the Laravel project in a directory outside `public_html`; expose only the contents of Laravel's `public/` directory through the domain document root. If Hostinger permits changing the document root, point it to the project's `public/` directory. Otherwise follow the private-project-directory plus public-entry-point arrangement below.

Hostinger's Git integration documentation distinguishes custom PHP/HTML deployment from Node.js Web App deployment:
https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/

## Important repository checks

The audited repository currently has:
- `artisan` and `composer.json`: present.
- `package.json` and Vite configuration: absent; no Node build is currently declared.
- `composer.lock`: absent at the time of audit.
- `.github/workflows/laravel-ci.yml`: absent at the time of audit; this patch adds it.

The CI workflow resolves dependencies on its first run, uploads the generated `composer.lock` as the `composer-lock-bootstrap` artifact, then runs the Laravel test suite and Pint. After the first run:

1. Open the workflow run in GitHub Actions.
2. Download `composer-lock-bootstrap`.
3. Extract `composer.lock` into the repository root beside `composer.json`.
4. Commit and push `composer.lock`.
5. Confirm the next workflow run installs from the committed lock file.

Do not treat a failed test run as production approval. Fix test failures before proceeding.

## Safe Laravel directory layout

Preferred layout:

```text
/home/ACCOUNT/
  vtu-saas/                         # full Laravel project; not web-accessible
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    storage/
    vendor/
    artisan
    composer.json
    composer.lock
    .env
  domains/YOUR_DOMAIN/public_html/  # only Laravel public/ contents
    index.php
    .htaccess
    icons / manifest / static assets
```

If the domain document root cannot be changed to `/home/ACCOUNT/vtu-saas/public`, copy only the contents of that `public/` directory into `public_html`. Edit `public_html/index.php` so both the Composer autoload include and `bootstrap/app.php` include point to the private `/home/ACCOUNT/vtu-saas` project directory. Use the actual absolute account path from Hostinger.

Never place `.env`, `app/`, `vendor/`, `storage/`, `database/`, `bootstrap/` or the full Laravel project inside the publicly served directory.

## Server-side setup after source is in place

From the private Laravel project directory, after configuring the production `.env` and creating the MySQL database:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run `php artisan key:generate` only on the first installation. Preserve the generated `APP_KEY` on all future deployments. Back up the database before migrations. Create the first platform administrator through the trusted CLI command documented in the main README.

Production `.env` must include `APP_ENV=production`, `APP_DEBUG=false`, a valid HTTPS `APP_URL`, Hostinger MySQL credentials, real SMTP settings and approved legal/contact details. Keep payment and VTU provider credentials out of GitHub and out of chat.

## Queue worker is a separate go-live gate

The application queues VTU submissions and webhook deliveries. A cron entry that runs `schedule:run` does not consume queued jobs. Hostinger shared hosting must be confirmed to support a reliable, supervised queue worker, or a separate managed worker/VPS must be configured. Do not switch to the synchronous queue as a workaround. Keep deposits and VTU purchases disabled until a durable worker, scheduler, monitoring and retry behavior have been tested.

## Do not go live yet

The repository's own production-readiness notes correctly identify further blockers: no committed Composer lock at audit time, no executed CI/test evidence, no MySQL-backed staging verification, no certified VTpass/SMEPlug or payment-gateway sandbox runs, no verified queue-worker arrangement, and unfinished legal/compliance and operational approvals. Deployment to a staging URL is not the same as approval to accept customer funds or submit live VTU orders.
