# Remaining external inputs and unfinished product scope

This file separates work that can be completed in source from decisions and access that must come from the platform owner or contracted partners.

## Required decisions from the platform owner

1. **VTpass + SMEPlug supplier access:** the supplier choices are confirmed. Supply VTpass sandbox API key/public key/secret key, approved live access after sandbox sign-off, and product/variation catalogue. SMEPlug Postman collection received and implemented: official Bearer private-key authentication, base URL, airtime/data/VTU purchase contracts and transaction status lookup are now coded. Still required: SMEPlug sandbox private key, current network/data-plan/cost catalogue, and actual purchase/status response examples to set exact terminal status/reference paths and test delayed success/failure safely. Do not send live secrets in chat.
2. **Payment gateway access:** Paystack, OPay and Moniepoint online collections (Monnify) are selected in code. Add sandbox credentials to the server environment and complete each provider merchant/KYB onboarding. Confirm whether Moniepoint means Monnify hosted online checkout or the separate Moniepoint POS terminal API. Supply production credentials only through server environment configuration after sandbox acceptance. Confirm refund/dispute, settlement report and virtual-account capabilities.
3. **Initial subscription policy:** free/trial plan features, paid plan prices, billing interval, grace period, quotas and which entitlements apply to VTU purchases, API clients, custom domains, seats and reporting. Paid subscriptions currently remain `pending_payment` until a verified billing integration exists.
4. **Brand identity:** final platform name is intentionally deferred; the public and authenticated wordmark now read the `APP_NAME` environment setting, so the name can be changed later without code edits. Logo/final icon, approved colour palette, support email/phone, public domain and legal business details still need owner approval. The UI uses Manrope/DM Sans.
5. **Email/SMS providers:** SMTP credentials and sender domain for email verification/password reset; an SMS/OTP provider and approved sender ID for phone verification.

## Code still requiring a partner contract or product decision

- Payment gateway sandbox certification, production credentials, refund/dispute reversal accounting, and bank/gateway settlement reconciliation. Payment initiation, callback signature validation, server-side verification, pending requery and finance re-verification are now implemented.
- SMEPlug request contract is implemented from the supplied Postman collection. Its response mapping and sandbox acceptance remain blocked on actual response examples/credentials. VTpass and SMEPlug sandbox acceptance, service-specific airtime/data/electricity/cable/exam PIN/gift-card validation and reconciliation remain incomplete.
- Phone OTP verification, MFA recovery codes, and email/SMS/in-app notification delivery.
- Paid subscription invoicing, payment collection, renewals, grace-period automation, cancellation and automatic paid-plan activation.
- Advanced tenant branding editor, logo/favicon upload, tenant theme controls, custom-domain DNS ownership verification/TLS automation and tenant-specific support/notification templates. Basic tenant customer registration, tenant-scoped wallet, gateway funding and reseller-priced purchase/transaction history are now implemented.
- Commission calculation/settlement, agent hierarchy, advanced fraud velocity limits, chargeback workflows and full KYC evidence collection/retention.
- Reconciliation against gateway settlements, supplier float and bank statements. The current run control verifies internal journal balance only.

## Build-environment limitations

The current environment does not contain Composer, Laravel `vendor/`, PDO MySQL, Mbstring or a MySQL server. Therefore no Laravel boot, Blade compile, migration, automated test, queue, MySQL concurrency or end-to-end sandbox result can be honestly reported. Run the included GitHub Actions workflow to generate and download the initial `composer.lock` artifact, then commit it. The workflow and resulting tests have not yet been executed against the user's GitHub repository.

## Do not bypass these controls

- Do not insert test balances or activate real-money deposits using manual wallet adjustments as a customer funding substitute.
- Do not enable live provider mode until official supplier sandbox tests and production credentials/approval are complete.
- Do not activate paid subscription entitlements based on a browser redirect or unverified payment.
- Do not enable withdrawals, transfers, cash-out or unrestricted third-party stored value without the required Nigerian legal and partner approvals.
