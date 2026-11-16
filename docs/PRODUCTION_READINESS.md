# Production readiness and release gates

This checklist is intentionally conservative. A checked source-code task is not equivalent to a verified production control.

## Required external decisions and credentials

- [ ] Complete VTpass and SMEPlug commercial onboarding; obtain sandbox credentials, production approval, current pricing/product codes, idempotency guarantees, requery semantics, outage/escalation process and contracts. VTpass adapter is implemented from public docs. SMEPlug request URLs, Bearer auth, request bodies and status lookup are implemented from the supplied Postman collection; actual sandbox response paths/status values and credentials are still required before activation.
- [ ] Complete merchant onboarding and obtain sandbox/live credentials for Paystack, OPay Cashier and Monnify (if Moniepoint online checkout is intended). Adapters, deposit initiation, signed callback checks, server-side verification and pending requery are implemented; run official sandbox acceptance and confirm refund/dispute/settlement reports and virtual-account capabilities.
- [ ] Obtain Nigerian legal/compliance review of restricted prepaid service credits, payment flow, reseller/API model, customer disclosures, complaints/refunds, NDPA 2023 obligations and tax treatment.
- [ ] Confirm supplier and payment partner contracts permit the intended platform, tenant/reseller and API distribution model.
- [ ] Configure SMTP/mail delivery and an SMS/OTP partner; verify deliverability and account recovery.

## Infrastructure

- [ ] Confirm the selected host supports the required PHP version/extensions, MySQL version, queue worker, cron scheduler, outbound HTTPS, TLS, storage, backups and resource envelope. Hostinger Business documents SSH, Composer 2 and cron, but this does not certify a persistent queue worker or Redis; arrange and test a managed worker or other supervised worker host.
- [ ] Use separate development, staging and production environments, databases, app keys and credentials.
- [ ] Configure `APP_ENV=production`, `APP_DEBUG=false`, a unique strong `APP_KEY`, HTTPS, secure cookies, trusted proxies and strict allowed hosts.
- [ ] Use a durable queue connection and supervised worker; configure scheduler invocation every minute; alert on failed jobs and queue backlog.
- [ ] Configure private object storage if KYC documents are later supported; define encryption, retention, deletion and access policies.
- [ ] Configure encrypted database/files backups and prove point-in-time recovery/restore in an isolated environment.
- [ ] Configure central error tracking, structured logs, redaction, uptime/latency/queue/provider/database alerts and incident escalation.
- [ ] Add outbound network egress controls for webhook delivery and provider calls; protect against DNS rebinding and destination changes.

## Application and security verification

- [ ] Run the GitHub Actions bootstrap workflow, download and commit its generated `composer.lock`, then install dependencies from that committed lock file; run migrations against MySQL 8+; seed only required roles and chart of accounts; create initial platform admin from trusted CLI.
- [ ] Run the complete automated test suite and static analysis. Add MySQL-backed concurrency tests for wallet spending, duplicate deposit callbacks, duplicate provider events and journal posting.
- [ ] Verify every role/permission boundary, tenant host resolution, custom host ownership/TLS, tenant suspension and cross-tenant IDOR resistance.
- [ ] Complete independent code review and penetration testing; remediate critical/high findings.
- [ ] Verify password reset, email verification, MFA enrollment/recovery, session invalidation, CSRF, rate limiting, API scopes, key rotation/revocation and log redaction.
- [ ] Verify KYC lawful basis, strict access, encryption, evidence retention and data-subject request handling before collecting identity documents.
- [ ] Verify all financial journals, reversals, deposits, provider costs, revenue and commissions with a qualified accountant.

## Provider and payment sandbox acceptance

- [ ] Verify VTpass and SMEPlug purchase/requery mapping for every enabled service and product. SMEPlug request contract is implemented; capture real sandbox purchase and status responses to configure exact status/reference paths and terminal values.
- [ ] Test supplier success, explicit failure, malformed response, timeout, delayed success, duplicate response, unknown status and outage.
- [ ] Confirm ambiguous supplier requests are never blindly retried or failed over. The code only switches to the next supplier after an explicit, mapped terminal failure.
- [ ] Test payment signature validation, amount/currency/reference mismatch, repeated callbacks, failed payments, chargeback/reversal and server-side verification.
- [ ] Reconcile gateway settlements, supplier float and bank statements independently of the internal journal-balance check.
- [ ] Confirm all public-facing services are hidden unless validation, provider routing, price, support and refund paths are active.

## Controlled release

- [ ] Deploy to staging and run end-to-end acceptance with non-production funds.
- [ ] Complete backup restore, incident-response, supplier-outage and rollback drills.
- [ ] Establish support staffing, escalation, refund decision process, reconciliation cadence and on-call ownership.
- [ ] Run load and soak tests at an agreed pilot capacity.
- [ ] Start with a controlled pilot, low limits, no unrestricted transfers/withdrawals/cash-out, and daily finance reconciliation.
- [ ] Obtain explicit business, finance, legal/compliance and technical sign-off before public production or live customer funds.

## Current known blockers

VTpass, SMEPlug, Paystack, OPay and Moniepoint/Monnify have been selected. VTpass, Paystack, OPay Cashier and Monnify adapters are implemented, but no merchant credentials or sandbox test evidence were supplied. SMEPlug request contract has been implemented from the supplied Postman collection; its collection contains no saved response examples, so exact response status/reference paths and terminal values require real sandbox responses. None of these integrations is sandbox-certified. These blockers cannot be completed honestly with placeholder credentials or fabricated test results.
