# VTU SaaS architecture baseline

## Product boundary
This is a separate Laravel/PHP + MySQL application. It does not share runtime or database tables with the existing `net` application. VTUPress was inspected as a reference package only; its WordPress runtime, credentials and wallet implementation are not copied.

## Architecture
A modular monolith with clear boundaries: Identity/RBAC, Tenant/Domain, Audit, Wallet/Accounting, Payment Collection, Catalogue/Pricing, Transaction Orchestration, Provider Adapters, Developer API, Webhook/Event Delivery, Subscription/Billing, KYC/Compliance, Notifications/Support, Reporting/Reconciliation. The REST API is versioned under `/api/v1`. Blade is the initial first-party web surface to avoid maintaining two frontend stacks at MVP stage.

## Tenant security rules
- Tenant identity is resolved from a verified hostname on the server.
- Tenant hostname resolution requires active tenant status, completed ownership verification and TLS activation.
- Tenant onboarding, domain and API-client actions that carry a tenant route parameter independently verify active membership and tenant-owner role; the route parameter is never accepted as proof of authorization.
- Tenant-owned transaction queries are constrained by the resolved tenant ID.
- Platform roles are distinct from tenant membership roles.

## Implemented source modules in this archive
- Laravel application bootstrap, MySQL configuration, database-backed sessions/cache/queues, responsive Blade application shell, professional typography, role-aware platform/customer/tenant navigation and conservative installable PWA shell.
- Customer registration with email verification, secure password reset, web/API login, platform administrator CLI bootstrap and TOTP MFA for privileged roles.
- Tenant onboarding/provisioning, membership, pending/active/suspended states, DNS TXT ownership challenge and manual TLS activation workflow.
- Wallet provisioning, integer-kobo balances derived from ledger entries, balanced immutable journal posting, linked reversals and maker-checker wallet adjustments.
- Deposit initiation, gateway adapters for Paystack/OPay/Monnify, signed webhook intake, server-side verification, pending requery and idempotent ledger posting; external settlement reconciliation and sandbox certification remain outstanding.
- VTU purchase reservation, request fingerprint/idempotency, immutable price/provider snapshots, provider attempts, pending-state handling, settlement and confirmed-failure reversal.
- Named VTpass and SMEPlug adapters plus a generic configured HTTPS JSON transport. SMEPlug request contract is grounded in the supplied Postman collection; terminal response mapping still requires sandbox examples.
- Direct-customer purchase UI, tenant purchase API and scoped developer purchase/status/balance APIs. Only catalogue services with active pricing and provider mapping are exposed.
- Developer portal with sandbox client/key creation, display-once API secrets, revocation, webhooks and delivery history.
- Tenant subscription plans and entitlements, with paid plan assignment held pending verified payment; KYC review, support tickets, risk review, audit log and transaction CSV export screens.
- Ledger journal-balance reconciliation control, reconciliation history and operational runbooks.

## Not yet production-complete
Concrete upstream VTU supplier adapter and credentials; payment gateway adapter, deposit initiation, signed payment webhook intake and settlement reconciliation; SMS/phone OTP verification; paid subscription billing/renewals/automated activation; tenant end-customer multi-wallet/storefront model and tenant branding editor; custom-domain automated TLS; commissions calculation/settlement; advanced services and validation; complete KYC evidence workflow/retention; support messaging/notifications; fraud velocity rules and chargeback/dispute workflows; gateway/provider/bank settlement reconciliation; independent security review, load tests and disaster recovery verification. These are explicit remaining tasks, not hidden demo implementations.

## Financial invariants
The ledger is the financial source of truth. All NGN amounts use integer kobo. Journal postings must balance. Posted entries are append-only and corrected through linked reversals. Wallet spending is reserved atomically. Provider timeout is an unknown outcome; requery is required before final settlement, refund or failover. See `FINANCIAL_LEDGER.md`.
