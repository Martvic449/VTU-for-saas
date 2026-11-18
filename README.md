# VTU SaaS Platform

A Laravel 12 / PHP 8.2+ modular-monolith application for a multi-tenant Nigerian VTU SaaS platform. It includes a public marketing homepage, Privacy Policy and Terms & Conditions pages, separate authenticated workspaces, MySQL migrations, provider/payment adapters and a versioned API. This project is separate from any other application.

## Current implementation scope

- Web and Sanctum authentication, role/permission seeders, privileged TOTP MFA.
- Tenant onboarding foundation, tenant-host resolution, tenant membership checks, tenant wallets and audit logging.
- Integer-kobo double-entry ledger, immutable journal posting and linked reversals.
- Paystack, OPay Cashier and Monnify payment initiation, callback verification and pending-deposit requery foundations. These remain sandbox-only until merchant credentials, partner acceptance and end-to-end tests are completed.
- Service catalogue, provider-product mappings, effective pricing rules and a configurable HTTPS JSON provider transport adapter.
- Idempotent wallet reservation, queued provider submission, safe pending outcomes, provider requery and confirmed-failure reversal.
- Tenant and developer purchase API endpoints; sandbox API keys, scopes, IP allowlists and request logging.
- Signed webhook delivery with bounded exponential retry and destination URL checks.
- Professional role-aware platform, customer and tenant application shell with installable PWA metadata and offline notice; platform screens for user management, API activity, tenants, transactions, providers, catalogue, finance, KYC, support, subscriptions, audit/risk and reports.
- Direct customer service purchase UI and tenant owner developer portal for sandbox API keys and signed webhooks.
- Email verification, password reset and finance journal-balance reconciliation controls.
- Reconciliation, subscription, KYC, commissions and support data foundations.

VTpass and SMEPlug adapters are included, alongside a configurable HTTPS adapter. Adapter activation requires supplier-specific mappings, real sandbox credentials and verified response/requery behavior.

## Local setup

Requirements: PHP 8.2+, Composer 2, MySQL 8+, and PHP extensions required by Laravel 12 including OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath (recommended for any future decimal calculations), Fileinfo and cURL. The financial ledger itself stores integer minor units (kobo) and does not use floating point.

```bash
cp .env.example .env
composer install
php artisan key:generate
# Configure MySQL connection in .env before migrating.
php artisan migrate --seed
php artisan platform:make-admin admin@example.com "Platform Administrator"
php artisan serve
```

The admin command prompts for an initial password. Never place production credentials in source control.

## Queue and scheduler

Provider submissions and webhook deliveries are asynchronous. Production must use a durable queue connection and a running worker; a queued transaction is not fulfilled until the worker processes it.

For Hostinger Business, use the bounded, overlap-protected runner at bootstrap/hostinger-cron.sh from one Custom cron job every minute. It runs Laravel's scheduler and a database queue worker with stop-when-empty. The full setup and validation procedure is in docs/HOSTINGER_PRIVATE_DEPLOYMENT.md. Do not use the synchronous queue in production, and do not enable VTU purchases until the cron output confirms jobs are actually processed.

For local development, run php artisan queue:work and php artisan schedule:work in separate terminals.
## Provider configuration

The platform admin Integrations screen creates a provider using the `configured_http` adapter. Configure only from official supplier documentation:

- HTTPS purchase and requery URLs.
- Encrypted API token.
- Supplier response status/reference/message/cost JSON paths and accepted terminal status values (via API configuration if the supplier differs from defaults).
- Service-specific provider product mapping and request field map.
- Platform pricing in integer kobo.

Providers, mappings and catalogue items are inactive by default. Activate only after sandbox tests verify request format, authentication, supplier idempotency/reference behavior, response normalization, and requery behavior. Keep `VTU_PROVIDER_ENVIRONMENT=sandbox` until formal approval for live traffic.

## Important transaction behavior

A successful HTTP response is not automatically treated as fulfilment unless its normalized supplier status is explicitly mapped to a success state. Unknown statuses, connection timeouts, server errors and ambiguous responses remain pending. A pending transaction is requeried; it is never blindly submitted again. A confirmed supplier failure reverses the original reservation journal exactly once.

A stale submission with no supplier reference is moved to pending and flagged for manual reconciliation. The system deliberately does not guess whether the supplier received the request.

## Security and deployment

See `docs/DEPLOYMENT.md`, `docs/PRODUCTION_READINESS.md`, `docs/REMAINING_INPUTS.md`, `docs/ARCHITECTURE.md`, `docs/FINANCIAL_LEDGER.md`, `docs/API.md`, `docs/ACCEPTANCE_TESTS.md` and `docs/PROVIDER_ADAPTER_CONTRACT.md`.

This repository has not been declared production-ready. See `docs/PRODUCTION_READINESS.md` for mandatory external integrations and release gates. Run Composer installation, migrations, all PHPUnit/Pest tests, static analysis, security review and supplier/payment sandbox acceptance tests in a proper MySQL-backed environment before pilot or production use.


## Selected supplier and payment integrations

See `docs/PROVIDER_SETUP.md` for VTpass + SMEPlug routing and supplier mapping, and `docs/PAYMENT_GATEWAYS.md` for Paystack, OPay Cashier and Monnify environment setup. Supplier/payment adapters require real sandbox credentials and provider acceptance tests before activation. SMEPlug request paths and payload fields are based on the supplied Postman collection. Its response mappings still require real sandbox response examples.

## Public website and legal pages

The public homepage is available at `/`. The draft Privacy Policy and Terms & Conditions are available at `/privacy-policy` and `/terms-and-conditions`. Before launch, set `LEGAL_ENTITY_NAME`, `LEGAL_ENTITY_ADDRESS`, `SUPPORT_EMAIL` and `PRIVACY_EMAIL` in the production environment, and have a Nigerian legal/privacy professional review the policy text against the actual operating entity, vendors, retention practices, payment model and complaint process. Do not publish with placeholder contact details.

## GitHub and Hostinger

See `docs/HOSTINGER_DEPLOYMENT.md` for repository preparation, the required Composer lock file, Hostinger PHP/MySQL configuration, safe Laravel document-root layout, environment setup, cron and queue limitations, and first-deployment checks. A GitHub Actions dependency/bootstrap workflow is included. On its first successful run without a lock file, download the generated `composer.lock` artifact and commit it; subsequent builds use the committed lock file. Hostinger Business includes SSH and Composer 2 according to Hostinger documentation, but its shared-hosting cron facilities do not by themselves prove that a durable queue worker is available. Keep live transactions disabled until the worker strategy, MySQL runtime, migrations and all supplier/payment sandbox acceptance tests have passed.
