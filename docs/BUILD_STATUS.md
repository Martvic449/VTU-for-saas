# Historical build status snapshot — 16 November 2026

> This is a historical record, not the current deployment checklist. Since this snapshot, composer.lock has been committed, Laravel CI has run against MySQL 8, and Hostinger migration automation has been added. For current implementation findings and remaining release blockers, see [ENGINEERING_AUDIT_2026-11-18.md](ENGINEERING_AUDIT_2026-11-18.md), [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md), and [HOSTINGER_PRIVATE_DEPLOYMENT.md](HOSTINGER_PRIVATE_DEPLOYMENT.md).

## Additional implementation delivered in this update

- Added installable PWA metadata, app icons and a network-only navigation service worker that caches only a static offline notice; no authenticated page, API response or financial data is cached.
- Added platform user search/suspend/reactivate controls and API request activity monitoring.
- Reworked the authenticated interface into a dedicated dark-navy application shell with a collapsible responsive sidebar, role-aware navigation, compact top bar and separate public marketing layout. Signed-in users no longer see the public marketing header/footer.
- Applied a consistent fintech typography system: Manrope for headings/brand and DM Sans for interface/body text, with robust system fallbacks; restrained teal/green accents, clearer hierarchy, spacing, status pills and responsive cards/tables.
- Added first-party KYC review queue with recorded decision notes, reviewer identity, timestamp and audit entry. Final approved/rejected decisions cannot be silently overwritten.
- Added customer support ticket submission and platform support queue with priority/status workflow and audited operational changes.
- Added subscription plan creation and tenant assignment. Free plans may activate; paid plans are created as `pending_payment` and do not grant entitlements until a verified billing workflow activates them. Replacing an active subscription is limited to a free-plan assignment in the absence of billing verification.
- Connected subscription entitlements to tenant API and VTU purchase access. Subscription start and end/grace dates are evaluated.
- Added finance dashboard reconciliation run control, recent run history and immutable journal list. The current reconciliation checks posted journal debit/credit balance only; it is not bank, gateway or provider settlement reconciliation.
- Added financial/operations report counts and safe transaction CSV export (no customer payloads or secrets).
- Added audit-log and risk-event review screens with recorded outcomes.
- Added tenant developer portal for sandbox API client/key management, one-time key display, revocation, webhook endpoint setup, delivery history and API reference link. Added atomic webhook delivery claims and a scheduled due/stale delivery re-dispatch command so interrupted workers can recover without concurrent duplicate sends.
- Added direct-customer and tenant-storefront customer purchase flows. Tenant customers can register or join with an existing verified account, receive a tenant-scoped ledger wallet, fund it through configured gateways, purchase tenant-priced services and view deposits/transactions. Both storefronts list only active products with effective pricing and active provider mapping in the configured environment.
- Added web and API email-verification flow; API registration now returns verification-required without issuing a usable token. Added anti-enumeration password reset flow and revocation of existing Sanctum tokens after password reset.
- Exposed the tenant purchase API route and enforced tenant subscription entitlements on tenant and developer VTU purchase requests.


## Supplier and payment integration work added in this update

- Added a VTpass-specific adapter using the published API key/public key/secret key authentication, `/pay` purchase and `/requery` request reference contract. It maps service IDs and per-product request fields from provider-service configuration and normalizes only explicit delivered/success or failed/reversed supplier statuses; unknown responses remain pending.
- Implemented SMEPlug v1 directly from the user-supplied Postman collection: fixed official API base URL, Bearer private-key authentication, network IDs, data-plan purchase, airtime purchase, VTU/share-and-sell, transaction status requery, and account/network/data-plan endpoint documentation. Added a read-only `smeplug:inspect` Artisan command for the documented networks, data plans, account balance and devices endpoints. Exact response status/reference paths remain a required sandbox configuration because the collection has no saved response examples. Synthetic adapter fixtures test the documented request contract only; no real sandbox transaction has been performed.
- Added sequential supplier routing using service mapping priority. A second supplier is attempted only after the first has returned an explicit, mapped failure. A timeout, pending response or unknown status remains on the first supplier and is re-queried; no blind duplicate vending is triggered.
- Added supplier-specific cost-rule support for each provider-service mapping and fallback price validation. Each provider attempt is retained, and the active supplier mapping is snapshotted.
- Added Paystack, OPay Cashier and Monnify (Moniepoint online collections) payment gateway adapters. Added customer wallet funding UI, deposit records, server-side verification, provider-specific webhook signature validation, idempotent ledger posting, failed-payment handling, amount/currency/reference mismatch review, pending-deposit requery and finance re-verification controls.
- Added scheduled recovery for reserved VTU transactions after an explicit supplier failure and scheduled requery for pending deposits.
- Added supplier/payment setup documentation in `PROVIDER_SETUP.md` and `PAYMENT_GATEWAYS.md`.

## Previously implemented foundation retained

- Laravel 12 modular monolith, MySQL migrations, RBAC, privileged TOTP MFA, tenant hostname resolution, membership checks and audit logging.
- Integer-kobo double-entry ledger, balanced immutable journals, linked reversals and derived wallet balances.
- Maker-checker wallet adjustments, verified-deposit posting service contract and duplicate event protection.
- Catalogue, pricing, provider mapping, provider routing and immutable transaction/provider snapshots.
- Idempotent wallet reservation, queued supplier submission, conservative pending-state handling, provider requery and confirmed-failure reversal.
- Scoped developer API keys, hash-only secret storage, IP allowlists, rate limits, request logs, signed webhook delivery and bounded retry.

## GitHub and Hostinger preparation added — 16 November 2026

- Added a GitHub Actions workflow with a MySQL 8 service, PHP 8.3, Composer dependency resolution/installation, Laravel feature tests and Pint formatting checks. On the first run without `composer.lock`, the workflow uploads the generated lock file as `composer-lock-bootstrap`; it must be downloaded and committed before treating subsequent builds as reproducible. The workflow has not yet run because the repository has not been connected to the user's GitHub account.
- Made the public and authenticated wordmark read `APP_NAME`, so the final platform name can be selected later without code edits. The default remains a temporary working name.
- Updated Hostinger Business deployment guidance to reflect Hostinger's documented SSH, Composer 2, Git integration and cron capabilities, while preserving the warning that scheduled cron is not a supervised durable queue worker.
- Increased the database queue `retry_after` default to 150 seconds, above the longest provider job timeout of 90 seconds, to reduce the risk of a job being reserved again while its first worker is still running.

## Static validation performed in this update

- PHP syntax lint passed for all 151 PHP files across application, routes, migrations, seeders, tests, bootstrap and configuration.
- Blade route-name scan found no missing named routes.
- `composer.json` and the PWA manifest parse as valid JSON; OpenAPI parses as valid YAML (22 paths) and its public copy matches the source.
- Service worker JavaScript syntax check passed; hard-coded token pattern scan found no matching secrets.
- `composer.lock` is not present yet; the new GitHub Actions bootstrap workflow can generate it and upload it as an artifact, after which it must be committed.

## Not executed / not certified

- Composer install, Laravel boot, Blade compilation, PHPUnit/Pest tests, MySQL migrations, database locking/concurrency and queue execution are not run in this environment. The local runtime lacks Composer, vendor dependencies, PDO MySQL, Mbstring and a MySQL server. The GitHub Actions workflow has not yet run.
- Supplier and payment adapter unit/feature tests have been added but have not been executed because Composer/vendor dependencies and MySQL are unavailable. No supplier/payment credentials were provided, so no live sandbox request has been made.
- SMEPlug request contract is implemented from the uploaded collection. Actual sandbox credentials, data plan/cost catalogue and real response examples are still required to configure terminal statuses/requery reference paths and certify the integration.
- Payment adapters and deposit initiation/webhook/verification code are present, but payment sandbox acceptance, refunds/disputes, gateway settlement reconciliation and bank-transfer/virtual-account settlement reconciliation are not complete. The selected payment gateways still require merchant credentials and sandbox acceptance.
- Phone OTP verification is not implemented because no SMS/OTP provider is selected. Email delivery must be configured with a real SMTP/mail provider in staging/production.
- Subscription invoices, payment collection, renewals, grace-period automation, cancellations and automatic paid-plan activation are not complete.
- Tenant customer wallet and storefront purchase flows are implemented. Tenant branding editor/custom-domain DNS ownership and TLS automation, agent hierarchy, commission calculation/settlement, advanced service integrations, notification delivery, fraud velocity rules, full dispute/chargeback accounting and KYC evidence upload/retention are not complete.
- Ledger reconciliation does not yet reconcile payment gateway settlement statements, supplier float/provider settlement or bank statements.
- No independent penetration test, security review, load test, backup restoration test or disaster-recovery drill has been performed.

## Production boundary

This source is an expanded development/integration build, not a production-certified release. Do not enable live deposits or customer-funded transactions until a licensed payment partner and supplier are selected, contracts and legal review are completed, adapters are implemented from official docs, sandbox acceptance passes, MySQL-backed automated tests pass, and security/operations release gates are signed off. See `PRODUCTION_READINESS.md`.
