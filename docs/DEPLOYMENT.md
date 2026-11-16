# Development and deployment notes

## Local prerequisites
- PHP 8.2+ with PDO MySQL, BCMath, Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer and XML.
- Composer 2, MySQL 8+, and a supported Node runtime only if a separate asset build is later introduced.
- Separate development, staging/sandbox and production secrets.

## First setup
1. Generate and commit `composer.lock` in a Composer-capable development environment; then run `composer install` from that lock file.
2. Copy `.env.example` to `.env`; set a long random `APP_KEY` via `php artisan key:generate`.
3. Create an empty MySQL database and set DB credentials.
4. Set `APP_ENV=local`, `APP_DEBUG=true` only locally; disable debug in staging/production.
5. Run `php artisan migrate --seed`.
6. Create the first administrator using `php artisan platform:make-admin admin@example.test "Platform Administrator"` from a trusted CLI.
7. Start local development with `php artisan serve`; use a worker for queued work once jobs are implemented.
8. Run `php artisan test` and `vendor/bin/pint --test`.

## Production safety
- Configure a real SMTP mailer and verified sender domain before enabling registration, email verification or password reset. The example `.env` uses the log mailer for local development only.
- Generate and commit `composer.lock`; deploy with `composer install --no-dev --prefer-dist --optimize-autoloader` and do not resolve dependency versions during production deployment.
- Do not deploy with debug enabled or use development credentials.
- Enforce HTTPS, trusted proxy configuration and a restrictive host allowlist.
- Configure encrypted backups and prove restoration before live transactions.
- Keep payment/provider secrets out of source control and frontend bundles.
- Run migrations with a backup and tested rollback plan.
- Configure queue workers/scheduler only on infrastructure that supports durable processes. Shared hosting must not be assumed to support Redis or long-running workers.
- No live deposits or purchases until legal review, payment/provider contracts, sandbox tests, operational monitoring and go-live approval are complete.

## Queue and scheduler requirements

Run a durable queue worker with `php artisan queue:work --tries=1 --timeout=120` (the VTU submission job timeout is 90 seconds) and configure the system scheduler to invoke `php artisan schedule:run` every minute. Reserved transaction recovery and due webhook-delivery recovery run every minute; pending transaction and deposit requery run every five minutes. Monitor queue backlog and failed jobs. The provider purchase job intentionally has one queue attempt; safe supplier fallback is handled only after an explicit supplier failure, never after an ambiguous worker failure.

## Required runtime extensions

Laravel 12 requires PHP 8.2 or newer and the standard Laravel extensions. Verify PDO MySQL, OpenSSL, Mbstring, cURL and other Laravel requirements in the target environment. The current development container did not include Composer, PDO MySQL, Mbstring or a MySQL server, so database migrations and framework tests could not be executed there.

Set `APP_DEBUG=false`, use a strong `APP_KEY`, enable secure cookies/HTTPS, and keep `VTU_PROVIDER_ENVIRONMENT=sandbox` until supplier sandbox acceptance is signed off. The initial platform admin is created through the trusted CLI command `php artisan platform:make-admin admin@example.com "Platform Administrator"`; MFA is mandatory at first sign-in.
