# Engineering audit and release status — 18 November 2026

## Scope and evidence

Reviewed the current GitHub repository tree (265 tracked paths at main commit 2cb1442321d3dd930def429a4d920f1381b42d56), the Laravel bootstrap/configuration, routes, tenant middleware, API-key authentication, core ledger/deposit/VTU transaction services, provider/payment adapters, migrations, seeders, tests, deployment workflows, and Hostinger runbooks. The governing VTU SaaS Product & Technical Blueprint remains the acceptance authority.

This is a source and workflow audit. It is not a production penetration test, provider certification, or a live-host inspection. The assistant cannot inspect Hostinger hPanel, read private server files, or set the user's GitHub/Hostinger secrets.

## Verified implementation

- Laravel 12 / PHP 8.2+ modular monolith with MySQL migrations and a committed Composer lock file.
- Identity, role/permission foundation, privileged TOTP MFA, tenant onboarding/domain verification, tenant membership and tenant-aware request resolution.
- Integer-kobo double-entry ledger services, journal balancing, wallet balance derivation, wallet reservation, linked reversal and maker-checker adjustment services.
- Payment adapters for Paystack, OPay and Monnify, including initiation, signature validation, server-side verification and pending requery paths.
- VTpass and SMEPlug adapters, provider mapping, transaction attempts, idempotency and conservative handling of ambiguous supplier outcomes.
- Developer API keys/scopes, API activity logging, signed webhook delivery and retry foundations.
- Platform, customer and tenant Blade interfaces; KYC/support/subscription/reporting foundations.
- CI on the last main commit passed 28 tests (88 assertions) using a MySQL 8 service. Composer audit reported no known advisories.

These are implemented code paths, not proof that all product requirements have passed acceptance tests.

## Material gaps found in the deployment plan

1. The repository tree did not contain Laravel's storage runtime directories. CI created them during its own run, but Hostinger Git deployment does not execute that preparation step. This could leave the deployed app without required writable storage paths.
2. The application uses a database queue, but the Hostinger runbook did not provide a bounded, overlap-protected queue worker command suitable for scheduled shared-hosting execution. Without a worker, VTU submissions and webhook deliveries remain queued.
3. A tenant developer API key remained usable after the user who owned its API client lost active membership in that tenant. This is an authorization gap.
4. Hostinger documents contradicted one another: some described a private project directory while the deployed workflow and actual site used public_html. One guide also instructed keeping Auto-deployment off while describing an automatic deployment-commit wait. That is not a coherent release procedure.
5. The migration workflow created schema but did not run the reviewed foundational seeders. The first platform administrator requires the super-admin role, and financial operations require the chart of accounts.
6. Unknown-host enforcement defaulted to false even in production unless explicitly configured.
7. Webhook and configurable-provider URL validation checked DNS addresses, but the subsequent HTTP client performed a fresh DNS lookup. That left a DNS-rebinding window to private network destinations.

## Remediation included in this audit branch

- Added tracked .gitignore placeholders for Laravel storage runtime directories so Git deployment creates the paths without committing runtime data.
- Added bootstrap/hostinger-cron.sh, a bounded Hostinger runner for schedule:run and database queue:work. It checks PHP/runtime prerequisites and uses flock to prevent overlapping workers.
- Updated CI to syntax-check the runner and verify the relevant deployment protections.
- Updated tenant API-key middleware to reject tenant-owned API clients whose owner is no longer an active member; added a regression test.
- Changed the tenant-domain enforcement default to fail closed when APP_ENV=production and enabled it in .env.example, while retaining the explicit environment override.
- Pin webhook and configurable-provider HTTP connections to the public DNS address validated by the URL policy, closing the validation/connect DNS-rebinding gap; added focused regression tests.
- Updated the Hostinger migration workflow to run the reviewed, idempotent DatabaseSeeder after successful migrations. It creates roles/permissions, the chart of accounts and inactive service templates only; it does not create demo balances, transactions or users.
- Consolidated the Hostinger instructions into one authoritative runbook and marked older contradictory guides as superseded.

These changes must pass the pull-request CI before merge. Do not describe them as deployed until the PR is merged and Hostinger has deployed that commit.

## Repository visibility

The GitHub repository is currently public. No production .env or credentials were intentionally added to this audit branch, but the complete application source is publicly readable. If the code is proprietary, change repository visibility to private and verify Hostinger's GitHub App retains access. This audit does not change repository visibility because that is a business/IP decision and can affect the existing deployment integration.

## Confirmed deployment blocker

The Hostinger production migration workflow run 44200659507 failed in its first step because all six SSH/deployment secrets were empty. The SSH setup and remote migration steps were skipped. It did not connect to Hostinger and did not modify the database.

The earlier application error naming the missing tenant_domains table is consistent with the migrations not having been applied. The migration workflow is deliberately non-destructive: it uses migrate --force and the reviewed idempotent seeder, never migrate:fresh or db:wipe.

## Still required before a controlled pilot

### Hosting and operations
- Configure the six GitHub Actions secrets documented in HOSTINGER_PRIVATE_DEPLOYMENT.md.
- Ensure the exact current main commit is deployed, then run the guarded migration workflow.
- Create the first platform administrator using the trusted Artisan command and enroll MFA.
- Configure the single Hostinger cron job for bootstrap/hostinger-cron.sh; inspect its first output and verify it actually processes a queued test job. If flock is unavailable or cron execution is unreliable, move queue processing to a supported worker host/VPS before enabling purchases.
- Configure real SMTP delivery, automated encrypted backups and a tested restore, monitoring/alerts and an incident procedure.
- Verify PHP CLI and web PHP versions/extensions, file permissions and HTTP deny rules on the actual Hostinger site.

### Product, partner and compliance acceptance
- Obtain and test VTpass and SMEPlug sandbox credentials and real response examples. SMEPlug terminal status/reference mappings remain unverified from live sandbox responses.
- Complete Paystack/OPay/Monnify merchant onboarding and official sandbox tests, including repeated callbacks, amount mismatches, timeouts, requery, refunds/disputes and settlement reports.
- Reconcile actual gateway settlements, provider float and bank statements. The current finance reconciliation screen checks internal journal balance only.
- Complete Nigerian legal/compliance and accountant review for prepaid credits, reseller/API distribution, customer disclosures, NDPA, complaints/refunds and tax.
- Complete phone OTP/SMS, paid subscription billing/renewal automation, commissions settlement, full KYC evidence handling, advanced service validation, custom-domain TLS automation, fraud/chargeback workflows and support notifications as product scope requires.
- Run MySQL concurrency tests, independent security review/penetration testing, load/soak tests and backup/restore drills.

## Release decision

**Not approved for live customer funds or live VTU fulfilment yet.** The repository has a meaningful tested foundation, but partner sandbox certification, reliable queue execution, hosting controls, financial reconciliation, compliance review and independent security testing remain release gates. Keep provider environment sandbox and payment gateways disabled until those gates are signed off.
