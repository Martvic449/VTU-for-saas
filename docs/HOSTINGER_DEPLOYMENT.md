# GitHub and Hostinger deployment guide

This guide targets Hostinger Business shared hosting. Hostinger currently documents SSH access and Composer 2 for Business hosting, and cron jobs for Premium and above. Its shared-hosting background-process support is scheduling-based; do not assume that it provides a supervised, persistent Laravel queue worker. Confirm PHP CLI version, extensions, cron execution limits and worker strategy in the actual hPanel account. The application must not be exposed with its project root as the public document root. References: https://www.hostinger.com/support/1583645-how-to-enable-ssh-access-in-hostinger/ ; https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/ ; https://www.hostinger.com/support/1583765-how-many-cron-jobs-can-you-set-up-in-hostinger/ ; https://www.hostinger.com/support/which-server-capabilities-are-supported-at-hostinger/

## Release prerequisites (do not skip)

1. Use PHP 8.2 or newer and verify `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `session` and `bcmath` are enabled.
2. The repository includes `.github/workflows/laravel-ci.yml`. Push it to a private GitHub repository and allow the first workflow run to resolve dependencies. Download the `composer-lock-bootstrap` artifact, commit the generated `composer.lock`, and push again. All later builds install from the committed lock file. Alternatively, use `composer2 update` over Hostinger SSH, inspect and commit the lock file before deployment. Never deploy without a committed lock file.
3. Run `composer install`, `php artisan test`, `vendor/bin/pint --test`, migrations and the full acceptance checklist against a MySQL database. This project has not yet passed those runtime checks in the development environment.
4. Replace legal placeholders and have the Privacy Policy, Terms, payment model and reseller/API arrangements reviewed by qualified Nigerian counsel.
5. Complete VTpass, SMEPlug, Paystack, OPay and Monnify sandbox acceptance. Keep all providers and gateways in sandbox/inactive mode until their own acceptance is signed off.
6. Configure SMTP, backups, error monitoring, queue/scheduler strategy and incident contacts.

## Prepare the GitHub repository

1. Create a **private** GitHub repository unless the business has intentionally approved public source disclosure.
2. Upload the project root contents (the directory containing `artisan`, `composer.json`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/` and `routes/`). Do not upload the ZIP as the repository's only file; extract it and commit the source tree.
3. Confirm `.env`, provider credentials, payment secrets, database passwords, `vendor/`, logs and generated cache files are not committed. `.env.example` is safe only while it contains placeholders.
4. Commit `composer.lock` after dependency resolution and testing. Keep secrets in Hostinger environment configuration, not GitHub Actions logs, source files or committed workflow variables.
5. Tag each tested release so deployments can be rolled back to a known source revision.

Example local commands after extracting the release:

```bash
git init
git add .
git status --short   # inspect carefully for .env, keys, credentials and generated files
git commit -m "Initial VTU SaaS release"
git branch -M main
git remote add origin YOUR_PRIVATE_GITHUB_REPOSITORY_URL
git push -u origin main
```

## Hostinger Business capabilities and safe Git deployment

Hostinger documents GitHub/GitLab integration for custom PHP/HTML websites, SSH on Premium Web and above, Composer 2 on Business hosting, and cron jobs on Premium and above. See https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/ and the references above. **Do not set the Git deployment target to `public_html` for the full Laravel repository.** That would put application source and potentially `.env`/vendor files in the web root. Deploy the repository into a private directory outside `public_html` using SSH/Git, or configure the Hostinger Git target to a verified private path. Publish only the contents of Laravel `public/` in the domain document root, with `index.php` paths adjusted as described below.

## Recommended Hostinger directory layout

Keep Laravel's application code outside the publicly served `public_html` directory. For example:

```text
/home/ACCOUNT/
  vtu-saas/                 # full Laravel repository, .env, vendor, storage
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    vendor/
    artisan
    .env
  domains/EXAMPLE.COM/public_html/  # only Laravel public/ contents
    index.php
    .htaccess
    build assets / icons / manifest / sw.js
```

The domain's document root should point to the Laravel project's `public/` directory if Hostinger permits changing the document root. If it does not, copy the contents of Laravel `public/` into `public_html` and edit `public_html/index.php` to load the application from the private project directory. Update both the Composer autoload path and `bootstrap/app.php` path in that index file to the correct absolute project path. Never copy `.env`, `app/`, `vendor/`, `storage/`, `database/` or the rest of the Laravel project into the public web root.

Keep `public/.htaccess` and ensure Apache rewrite support is enabled. Force HTTPS at the hosting/domain layer. Verify that direct requests for `.env`, `composer.json`, `artisan`, `vendor/` and `storage/` are not publicly accessible.

## Configure the production environment

Create `.env` on the server, never in GitHub. Start from `.env.example` and set at least:

```dotenv
APP_NAME="Your approved platform name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
APP_TIMEZONE=Africa/Lagos
APP_KEY=                       # generate once with php artisan key:generate; preserve on redeploy

DB_CONNECTION=mysql
DB_HOST=YOUR_HOSTINGER_DB_HOST
DB_PORT=3306
DB_DATABASE=YOUR_DATABASE
DB_USERNAME=YOUR_DATABASE_USER
DB_PASSWORD=YOUR_DATABASE_PASSWORD

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true

LEGAL_ENTITY_NAME="YOUR REGISTERED LEGAL BUSINESS NAME"
LEGAL_ENTITY_ADDRESS="YOUR REGISTERED BUSINESS ADDRESS"
SUPPORT_EMAIL=support@YOUR_DOMAIN
PRIVACY_EMAIL=privacy@YOUR_DOMAIN
MAIL_MAILER=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@YOUR_DOMAIN
MAIL_FROM_NAME="Your approved platform name"

VTU_PROVIDER_ENVIRONMENT=sandbox
PAYMENT_GATEWAY_DEFAULT=paystack
PAYMENT_GATEWAYS_ENABLED=
```

Set `TENANT_BASE_DOMAIN`, `TENANT_PLATFORM_HOSTS`, and tenant domain enforcement to the actual production domain/subdomain arrangement. Configure payment and supplier credentials only after the relevant merchant/supplier sandbox is approved. Never reuse the example secrets or paste production credentials into chat.

Run `php artisan key:generate` only for the first installation, after the `.env` exists. Do not regenerate the key during routine deployments; doing so can invalidate encrypted data and sessions.

## Install and initialize

From the private project directory, using SSH/terminal if enabled:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first platform administrator from a trusted CLI:

```bash
php artisan platform:make-admin admin@YOUR_DOMAIN "Platform Administrator"
```

Use a unique strong password and complete mandatory MFA. Do not seed a known/default production administrator password.

Before migrations, take a database backup. Test the exact migration set against a staging copy first. Do not run destructive migration commands on production.

## Queue and scheduler: critical Hostinger limitation

This platform relies on asynchronous VTU submission, supplier requery, deposit requery and webhook retry. Hostinger Business provides cron scheduling, but a scheduled command is not the same as a supervised persistent Laravel queue worker. For live financial operations, use a managed queue worker or a VPS/process supervisor unless Hostinger confirms and you have load-tested a safe worker arrangement on your account. Never change `QUEUE_CONNECTION` to `sync` as a workaround. The database queue `retry_after` defaults to 150 seconds; the longest provider submission job has a 90-second timeout. Keep the retry window longer than the maximum job runtime.

If cron is available, configure the scheduler to run every minute:

```bash
cd /home/ACCOUNT/vtu-saas && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the actual PHP binary and absolute project path provided by Hostinger. This scheduler command only runs scheduled dispatch/requery/retry tasks; it does **not** consume queued jobs. Configure a separate durable worker using `php artisan queue:work --tries=1 --timeout=120` with a process supervisor on a supported worker host. Monitor failed jobs and queue backlog. If the host cannot guarantee a durable worker, keep financial transaction flows disabled until an external worker is configured. Hostinger cron schedules use UTC; convert Africa/Lagos times when creating time-specific schedules.

## First-deployment verification

- HTTPS certificate is valid; HTTP redirects to HTTPS.
- Homepage, login, registration, Privacy Policy and Terms pages load.
- Authenticated dashboards do not show the public marketing navigation/footer.
- Mail delivery, password reset and email verification work.
- MySQL migrations and seeders complete without errors.
- First admin MFA setup works; role boundaries and tenant isolation pass tests.
- Queue worker and scheduler execute independently and are monitored; verify queue retry-after exceeds every job timeout.
- Payment callbacks are signature-checked and server-verified; duplicate callbacks do not double-credit.
- Supplier sandbox purchase, timeout, delayed success, failure, requery and safe fallback cases pass.
- Backups are encrypted and a restoration test succeeds.
- No debug stack traces, secrets, environment files or internal project files are publicly accessible.
- Independent security review and legal approval are recorded before any live customer money or supplier fulfilment is enabled.

## Rollback

Keep the previous tested release and database backup. Roll back code to the previous release if needed. Database rollback must follow a tested, migration-specific plan; never assume every production migration is safely reversible. Preserve the production `APP_KEY` and all required provider/gateway credentials during a code rollback.
