# Development and deployment

## Local prerequisites

- PHP 8.2+ with Laravel 12 required extensions, including PDO MySQL, OpenSSL, Mbstring, cURL, Fileinfo, Tokenizer and XML.
- Composer 2.
- MySQL 8+.
- Separate local, staging/sandbox and production credentials.

## Local setup

1. Copy .env.example to .env.
2. Configure a local MySQL database.
3. Run composer install using the committed composer.lock.
4. Generate a local APP_KEY with php artisan key:generate.
5. Run php artisan migrate --seed.
6. Create a local platform administrator with php artisan platform:make-admin.
7. Run php artisan test and vendor/bin/pint --test.

## Production release rules

- Never deploy APP_DEBUG=true or development credentials.
- Keep provider/payment secrets outside Git and outside the public document tree.
- Run only additive, reviewed migrations with a backup and rollback plan. Never run migrate:fresh or db:wipe in production.
- Do not activate live VTU or payment operations before real partner sandbox acceptance, financial reconciliation, legal/compliance review and security sign-off.
- Use a durable queue and scheduler. A database queue does not process itself.
- Verify backup restoration, monitoring, incident response and resource capacity before a customer pilot.

## Hostinger Business

The current Hostinger layout, exact deployment path, GitHub Actions migration workflow, reviewed initial seeders, first-admin bootstrap, and one-job cron runner are documented only in [HOSTINGER_PRIVATE_DEPLOYMENT.md](HOSTINGER_PRIVATE_DEPLOYMENT.md). Follow that guide rather than older deployment notes.
