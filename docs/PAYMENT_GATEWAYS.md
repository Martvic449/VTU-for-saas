# Payment gateway integration

The application now includes server-side adapters for Paystack, OPay Cashier and Monnify (Moniepoint's online payment collections product). All payment initiation requests originate from the Laravel backend. A wallet is credited only after the gateway adapter verifies the transaction directly with the gateway and confirms the exact reference, NGN currency and expected amount in kobo.

## Environment configuration

Set only the providers you have merchant accounts and credentials for:

```dotenv
PAYMENT_GATEWAYS_ENABLED=paystack,opay,monnify
PAYSTACK_SECRET_KEY=sk_test_REPLACE_ME
PAYSTACK_WEBHOOK_SECRET=whsec_REPLACE_ME
OPAY_ENVIRONMENT=sandbox
OPAY_MERCHANT_ID=
OPAY_PUBLIC_KEY=
OPAY_PRIVATE_KEY=
MONNIFY_ENVIRONMENT=sandbox
MONNIFY_API_KEY=
MONNIFY_SECRET_KEY=
MONNIFY_CONTRACT_CODE=
```

Do not commit real keys. Configure sandbox credentials first. The wallet funding form only displays a gateway after all required credentials for that gateway are present.

## Paystack

Official docs:

- <https://paystack.com/docs/api/transaction/>
- <https://paystack.com/docs/payments/accept-payments/>
- <https://paystack.com/docs/payments/webhooks/>

The adapter initializes a transaction using the amount in kobo, verifies using `/transaction/verify/{reference}`, and validates the current `Paystack-Signature` timestamped HMAC-SHA512 format with `PAYSTACK_WEBHOOK_SECRET` when configured, and retains the legacy `x-paystack-signature` HMAC-SHA512 check with the secret key during migration. Only `charge.success` events are considered for crediting, and the server-side verify response must match amount, currency and reference.

Configure the webhook URL shown by the application route: `/api/payments/paystack/webhook`. Optionally configure Paystack published webhook source IPs in `PAYSTACK_WEBHOOK_IPS` after confirming the current official allowlist and your trusted proxy configuration.

## OPay Cashier

Official docs:

- <https://documentation.opaycheckout.com/cashier-create>
- <https://documentation.opaycheckout.com/query-payment-status>
- <https://documentation.opaycheckout.com/payment-notifications-callbacks>
- <https://documentation.opaycheckout.com/callback-signature>

The adapter uses OPay's staging or live Cashier create/status endpoints. Cashier creation uses the public key and merchant ID. Status requests use HMAC-SHA512 of the JSON request body with the private key. Callback validation uses OPay's documented HMAC-SHA3-512 signature string and private key. The status API is called after a callback before a wallet credit is posted.

Configure `/api/payments/opay/webhook` as the callback URL in the OPay merchant dashboard. If OPay provides a source IP allowlist for your account, configure it in `OPAY_WEBHOOK_IPS`.

## Monnify (Moniepoint)

Official docs:

- <https://developers.monnify.com/docs/collections/quickstart>
- <https://developers.monnify.com/docs/collections/one-time-payments/checkout-api>
- <https://developers.monnify.com/docs/collections/manage-payments/verify-transactions>
- <https://developers.monnify.com/docs/webhooks>

This integration targets Monnify's online collections API (`sandbox.monnify.com` / `api.monnify.com`), not Moniepoint POS terminal APIs. It authenticates with API key + client secret, initializes hosted checkout, verifies by payment reference and validates production `monnify-signature` using HMAC-SHA512. Monnify's sandbox webhook may omit the signature; even then, no wallet credit occurs without server-side transaction verification.

Configure `/api/payments/monnify/webhook` in the Monnify dashboard. Monnify documents source IP `35.242.133.146`; configure `MONNIFY_WEBHOOK_IPS=35.242.133.146` after ensuring Laravel trusts only your actual reverse proxy headers.

## Deposit lifecycle and safety

- `initiated` / `pending`: waiting for gateway outcome.
- `successful`: exact server-side verified payment and one posted balanced journal.
- `failed`: gateway status endpoint confirms failure.
- `under_review`: amount/currency/reference mismatch, or a refund/reversal/dispute signal requiring finance review.

The customer return URL never credits a wallet on its own. Signed callbacks are validated, then the server calls the gateway verify endpoint. A scheduled command re-queries pending deposits. Finance staff can manually reverify a pending/under-review deposit; a deposit that already has a credit journal cannot be credited a second time.

Gateway refunds, chargebacks, settlement reconciliation and reversal accounting still require provider-account sandbox tests and operational approval. A later refund/reversal notification is flagged for review rather than silently changing a wallet that may already have spent the credit.
