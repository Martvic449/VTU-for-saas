# Acceptance and debugging checklist

## Financial correctness
- [ ] Duplicate successful payment webhook credits exactly once.
- [ ] Invalid signature, unverified, failed or mismatched amount/currency/reference never credits.
- [ ] Concurrent purchases cannot overspend the same wallet.
- [ ] Confirmed failed VTU transaction reverses the reservation exactly once.
- [ ] Pending provider outcome does not trigger duplicate fulfilment, refund or failover.
- [ ] Every journal balances; ledger totals reconcile to account balances.
- [ ] Historical transaction price snapshots survive pricing changes.
- [ ] Manual adjustments require an authorised role, reason and audit entry.

## Tenant and identity
- [ ] Tenant A cannot read or modify Tenant B users, customers, transactions, wallets, keys, branding or reports.
- [ ] Hostname resolution uses only verified domains attached to active tenants.
- [ ] Client-supplied tenant IDs are ignored for tenant context.
- [ ] Suspended/pending tenants cannot reach tenant application surfaces.
- [ ] Privileged platform roles cannot call admin endpoints without MFA verification.
- [ ] Revoked Sanctum credentials are rejected; login is rate limited.

## Provider and API
- [ ] Provider timeout, malformed response, delayed success and duplicate callback are handled safely.
- [ ] Requery is authorised and rate limited.
- [ ] API keys are shown only once, scoped, revocable and stored securely.
- [ ] Webhook signatures and replay protections are verified; retries are bounded and observable.
- [ ] Logs redact credentials, tokens, payment data and sensitive KYC details.

## Environment caveat
Run the full suite against the supported Laravel/PHP version and a real MySQL instance. SQLite-only test results do not prove MySQL locking/concurrency behavior. Provider and payment sandbox acceptance requires real sandbox credentials and official supplier documentation.

## Provider execution and requery
- [ ] A provider connection uses only the configured environment (sandbox by default).
- [ ] Only registered adapter identifiers can be resolved; no class names are accepted from provider settings.
- [ ] Provider URLs are HTTPS and resolve to public addresses.
- [ ] A mapped product is submitted once with the platform transaction reference as the supplier idempotency reference.
- [ ] Unknown supplier status, HTTP 4xx/5xx, timeout or connection failure does not become a successful or failed transaction by assumption.
- [ ] A provider response mapped to success settles exactly once; an explicit mapped definitive failure reverses the reservation exactly once.
- [ ] A pending transaction with a supplier reference can be requeried without a new purchase submission.
- [ ] A stale submission without supplier reference is flagged for manual reconciliation and is never automatically resubmitted.

## Developer API and webhook delivery
- [ ] Purchase endpoint requires a scoped API key and idempotency key.
- [ ] Both API client and API key must grant the required scope.
- [ ] Webhook destination must be HTTPS and must not resolve to private/reserved IP ranges.
- [ ] Webhook signature validates over `timestamp.body`; receivers deduplicate by delivery ID.
- [ ] Non-2xx webhook delivery retries with bounded exponential backoff and becomes abandoned after the configured limit.

## Current execution evidence
- PHP syntax validation has been run over application, route, migration, seeder, test, bootstrap and config files.
- Composer manifest JSON and OpenAPI YAML parse checks have been run.
- Standalone HMAC signer checks have been run.
- Full Laravel feature tests, migrations, MySQL row-locking tests and queue execution remain unrun until Composer dependencies and a MySQL environment are available.

## Manual finance controls
- [ ] A wallet adjustment request does not mutate the wallet before approval.
- [ ] The requester cannot approve or reject their own request.
- [ ] A different authorised approver posts a balanced journal and links it to the adjustment record.
- [ ] A debit adjustment rechecks the available wallet balance at approval time.
- [ ] Rejected requests do not create ledger entries or change wallet balance.
