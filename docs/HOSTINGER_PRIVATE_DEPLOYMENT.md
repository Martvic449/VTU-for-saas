# Hostinger Business deployment runbook

**This is the single authoritative Hostinger deployment guide for this repository.** Older Hostinger notes are superseded by this file.

## 1. Deployment layout currently supported

The current Hostinger Business setup uses the Git integration target public_html. The Laravel project root is deployed there, but the root .htaccess forwards web requests into Laravel's public/ directory and denies direct HTTP access to application source, dependencies, runtime storage, and repository metadata.

Production secrets remain outside the Git checkout:

~~~text
/home/u323204665/domains/net.kokaacademyltd.com/
├── private-config/
│   └── .env                 # private production environment; never commit
└── public_html/             # Hostinger Git target; branch main
    ├── .htaccess            # blocks private project paths and routes to public/
    ├── app/
    ├── bootstrap/
    │   ├── cache/
    │   └── hostinger-cron.sh
    ├── config/
    ├── database/
    ├── public/
    │   └── index.php
    ├── resources/
    ├── routes/
    ├── storage/
    ├── vendor/              # installed with Composer on the host
    ├── artisan
    ├── composer.json
    └── composer.lock
~~~

The path above matches the account path seen in the supplied Hostinger error log. The application bootstrap loads ../private-config/.env automatically. The GitHub migration workflow checks for this exact sibling environment file.

**Security note:** on shared hosting this is a compatibility arrangement, not as strong as placing the complete Laravel project outside the document root. The root .htaccess deny rules are mandatory. Never remove them, never put the real .env in public_html, and never use permissions such as 777. A future VPS deployment should move the project outside the web root and expose only Laravel's public/ directory.

## 2. Current deployment facts

- Repository: Martvic449/VTU-for-saas
- Branch: main
- Hostinger Git target: public_html
- Public URL: https://net.kokaacademyltd.com
- Private environment: domains/net.kokaacademyltd.com/private-config/.env
- Database: the existing Hostinger MySQL database configured in that private file.
- The repository contains composer.lock; do not run composer update on production.
- bootstrap/cache/.gitignore and the required storage/**/.gitignore placeholders are committed so Git deployment creates Laravel's runtime directories.
- Keep Hostinger Auto-deployment OFF until the first migration, seeding, admin bootstrap and smoke/security checks have succeeded. Turn it on afterward so future main releases deploy automatically.

Do not use Hostinger's Node.js Web App import flow. This is Laravel/PHP and does not need a fake package.json.

## 3. Production environment

Keep the actual file private. Do not paste it into chat or screenshots. Use Hostinger's exact database values; the existing database host was set to localhost and should not be changed without evidence.

Required production settings:

~~~dotenv
APP_NAME="VTU SaaS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://net.kokaacademyltd.com
APP_TIMEZONE=Africa/Lagos
APP_KEY=<existing generated key; preserve it>

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<Hostinger database name>
DB_USERNAME=<Hostinger database user>
DB_PASSWORD=<private database password>

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=150

TENANT_BASE_DOMAIN=net.kokaacademyltd.com
TENANT_DOMAIN_ENFORCE=true
TENANT_PLATFORM_HOSTS=net.kokaacademyltd.com

VTU_PROVIDER_ENVIRONMENT=sandbox
PAYMENT_GATEWAYS_ENABLED=
~~~

- Preserve the existing APP_KEY; changing it can invalidate encrypted provider credentials and sessions.
- Keep APP_DEBUG=false.
- Production now forces known-domain enforcement even if the old private .env still contains TENANT_DOMAIN_ENFORCE=false. Keep the platform hostname listed explicitly in TENANT_PLATFORM_HOSTS.
- Leave payment gateways disabled until their sandbox acceptance is complete.
- Configure a real SMTP mailer before relying on email verification or password reset. The example MAIL_MAILER=log is not a production mail service.
- Do not add live supplier or payment credentials yet.

## 3A. Wildcard reseller subdomains and HTTPS

The platform assigns reseller addresses such as `slug.net.kokaacademyltd.com`. These are platform-owned subdomains, not customer-owned custom domains. Configure the DNS zone for `net.kokaacademyltd.com` once in Hostinger DNS (or the authoritative DNS provider):

- Add an `A` record with host/name `*` pointing to the same Hostinger server IPv4 address used by `net.kokaacademyltd.com`.
- If the application uses a stable IPv6 address, add the matching wildcard `AAAA` record too; do not add one unless Hostinger supplies the correct IPv6 target.
- Keep the existing root/base-domain records intact. Remove conflicting wildcard records only after confirming they are obsolete.
- Ensure Hostinger's web server routes unknown subdomains to this same Laravel `public/` entry point. A wildcard DNS record alone does not configure virtual-host routing.

Issue/install a wildcard TLS certificate for `*.net.kokaacademyltd.com` (and retain a certificate for `net.kokaacademyltd.com`). A wildcard certificate normally requires DNS-based ACME validation; use Hostinger's supported wildcard SSL workflow or a DNS-provider ACME integration. Do not assume the base-domain certificate covers subdomains.

After DNS and TLS are configured, test an approved tenant hostname over HTTPS. The application treats platform-managed subdomains as verified through the platform wildcard and marks TLS active only when that hostname is reached over HTTPS (the browser/server TLS handshake must succeed first). No individual TXT ownership challenge is required for these platform-owned subdomains.

**Custom domains are different:** a reseller-owned domain must have its own DNS pointing/verification and certificate provisioning before it is activated. Never treat a custom domain as covered by the platform wildcard certificate.

## 4. Composer and runtime directories

Hostinger Git transfers source files; it does not install Composer dependencies or run migrations. The repository now tracks the required runtime directory placeholders. The migration workflow refuses to continue unless vendor/autoload.php, the private environment file and writable bootstrap/cache are present.

If vendor/autoload.php is missing, install from the committed lock file in the project root:

~~~bash
composer2 install --no-dev --prefer-dist --optimize-autoloader
~~~

Do not run composer update. Do not delete the cache directories. Do not use 777.

## 5. Database migration and safe initial seeding

The GitHub Actions workflow named **Hostinger production migrations** is guarded:

1. It runs only after Laravel CI succeeds on main, or when manually dispatched.
2. On automatic workflow_run events, it safely skips deployment if the six SSH/deployment secrets have not yet been configured. A manual Run workflow attempt fails clearly until all six secrets exist.
3. It waits until Hostinger's Git checkout is the exact commit that passed CI.
4. It verifies artisan, vendor/autoload.php, ../private-config/.env, and writable bootstrap/cache.
5. It clears stale Laravel optimization/config caches with php artisan optimize:clear.
6. It runs php artisan migrate --force.
7. It runs php artisan db:seed --class=DatabaseSeeder --force.
8. It rebuilds the production configuration cache from the private environment file.

The reviewed database seeder is idempotent and creates only platform roles/permissions, the chart of accounts, and **inactive** catalogue templates. It does not create demo users, demo balances, transactions, or active provider mappings. The workflow never runs migrate:fresh, db:wipe, or destructive seeders.

The earlier migration workflow run failed at the initial secrets check. It did not connect to Hostinger and did not run migrations. The reported missing tenant_domains table is resolved by applying the committed migrations to the configured database.

### Required GitHub repository secrets

Add these under GitHub → repository **Settings → Secrets and variables → Actions**:

| Secret | Value |
|---|---|
| HOSTINGER_SSH_HOST | SSH hostname/IP shown in Hostinger hPanel |
| HOSTINGER_SSH_USER | Hostinger SSH username |
| HOSTINGER_SSH_PORT | SSH port shown in hPanel |
| HOSTINGER_SSH_PRIVATE_KEY | Dedicated private deployment key; its matching public key goes into Hostinger SSH authorized keys |
| HOSTINGER_SSH_KNOWN_HOSTS | Hostinger SSH host-key line after fingerprint verification |
| HOSTINGER_DEPLOY_PATH | /home/u323204665/domains/net.kokaacademyltd.com/public_html |

Never store the database password or the production .env in GitHub Actions secrets for this workflow. Do not paste SSH private keys into chat. Do not use the database password as an SSH password.

After the secrets exist, manually redeploy the current main commit from Hostinger if necessary, then run **Actions → Hostinger production migrations → Run workflow**. It will refuse to migrate if Hostinger is on an older commit. After migration and initial smoke/security checks succeed, enable Hostinger Auto-deployment for main; subsequent successful CI runs will then trigger the guarded migration workflow automatically.

## 6. Create the first platform administrator

After migration and seeding succeed, create the first platform administrator once from Hostinger's trusted SSH/Terminal:

~~~bash
cd /home/u323204665/domains/net.kokaacademyltd.com/public_html
php artisan platform:make-admin ADMIN_EMAIL "Platform Administrator"
~~~

The command securely prompts for a temporary password and requires at least 12 characters. Use a unique password. Privileged accounts must enroll in MFA at first sign-in. Never create the administrator by inserting a raw database row.

## 7. Scheduler and queue worker — one Hostinger cron job

The application uses a database queue. A successful HTTP response only means a VTU request was accepted; the supplier job must be processed by a queue worker. The scheduler also dispatches reserved transactions, re-queries pending supplier/payment outcomes, and recovers webhook deliveries.

This repository now includes bootstrap/hostinger-cron.sh. It:

- checks that CLI PHP is 8.2 or newer;
- checks Laravel dependencies and writable storage;
- runs php artisan schedule:run;
- starts a bounded database queue worker using --stop-when-empty;
- uses flock to prevent overlapping queue workers;
- exits after a bounded run rather than leaving an unmanaged daemon running.

Create one Hostinger **Custom** cron job with this command, scheduled every minute:

~~~bash
bash /home/u323204665/domains/net.kokaacademyltd.com/public_html/bootstrap/hostinger-cron.sh
~~~

Check the cron output after its first run. If it reports that flock is unavailable, do not process VTU transactions on this shared host until a supported worker mechanism or VPS is arranged. A cron entry is not proof that the worker is healthy; verify that a test job is actually consumed before enabling purchases.

## 8. Release and safety gates

Do not enable live purchases, deposits or public reseller onboarding until all of the following are independently verified:

- database migrations and safe seeders complete on the intended Hostinger database;
- first platform admin is created and MFA works;
- PHP CLI and web PHP both meet Laravel's PHP 8.2+ requirement and required extensions are enabled;
- scheduler and queue worker process jobs reliably, and failed jobs/queue backlog can be inspected;
- SMTP email verification and password reset work;
- Hostinger backups are enabled and a restore has been tested;
- .env, Composer files, artisan, vendor/, app/, storage/, and .git/ cannot be fetched over HTTP;
- VTpass/SMEPlug and Paystack/OPay/Monnify sandbox transactions, callbacks, requery, duplicates, timeouts and reconciliation pass real partner sandbox tests;
- Nigerian legal/compliance review, supplier/payment contracts, privacy terms and finance/accounting review are complete;
- independent security review and load/soak tests are complete.

The current automated CI run on 17 November 2026 passed 28 tests (88 assertions) against a MySQL 8 service and Composer reported no known security advisories. That is useful evidence, but it is not a substitute for production-host, concurrency, provider sandbox, settlement, legal, backup/restore or penetration testing.

## 9. Official Hostinger references

- Git deployment and root directory selection: https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/
- SSH access: https://www.hostinger.com/support/1583645-how-to-enable-ssh-access-in-hostinger/
- Cron jobs: https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/
- Background-process capabilities on Web/Cloud hosting: https://www.hostinger.com/support/which-server-capabilities-are-supported-at-hostinger/
- PHP version and extensions: https://www.hostinger.com/support/1583467-how-to-check-the-php-information-of-your-website-at-hostinger/
