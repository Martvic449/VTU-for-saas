# API contract (implemented and planned surface)

Base path: `/api/v1`. JSON responses use `data` for successful payloads. Laravel validation responses use standard validation envelopes. Developer API responses include `X-Request-ID` for successfully authenticated requests.

## Account authentication
- `POST /auth/register` — creates a direct customer and wallet, sends an email verification link, and does not issue an API token until the account is verified.
- `POST /auth/register-tenant` — reseller owner onboarding; creates a pending tenant and tenant wallet, sends owner email verification, and requires platform approval/domain activation.
- `POST /auth/login` — email/password; privileged platform users must configure TOTP MFA or provide a valid TOTP before receiving an admin-capable token.
- `POST /auth/logout` — revoke current Sanctum token.
- `GET /auth/me` — current account profile.
- `POST /auth/mfa/setup` and `POST /auth/mfa/confirm` — privileged TOTP enrollment.

## Platform administration
Requires Sanctum authentication, the appropriate platform role and `mfa:verified` token ability.
- `GET /platform/dashboard`
- `GET /platform/tenants`
- `POST /platform/tenants`
- `PATCH /platform/tenants/{id}/status`
- `POST /platform/domains/{id}/verify-platform-domain` — platform-owned subdomain verification assertion after DNS is confirmed.
- `PATCH /platform/domains/{id}/tls` — operator confirmation after TLS certificate issuance/validation.

## Tenant owner management
Requires Sanctum authentication and an active `tenant-owner` membership for the specified tenant. The tenant ID in the path is only a lookup key; membership is checked server-side.
- `POST /tenants/{tenant}/domains`
- `POST /tenants/{tenant}/domains/{domain}/verify` — DNS TXT challenge verification.
- `POST /tenants/{tenant}/api-clients` — sandbox client creation (optional IP allowlist).
- `POST /tenants/{tenant}/api-clients/{client}/keys` — create a scoped key; secret is returned once.
- `DELETE /tenants/{tenant}/api-clients/{client}/keys/{key}` — revoke a key, including after API entitlements expire.
- `PATCH /tenants/{tenant}/webhooks/{endpoint}/status` — activate/deactivate an owned webhook; reactivation requires active API entitlement and destination revalidation.

## Tenant context
Tenant identity is resolved from a verified, TLS-active hostname and active membership.
- `GET /tenant`
- `GET /tenant/transactions`
- Web storefront: `/`; tenant dashboard: `/tenant/dashboard`.

## Developer API key authentication
Send `X-API-Key: <prefix>.<secret>`. Secrets are stored as SHA-256 hashes and returned only at creation. Keys can be scoped, revoked, IP-restricted and rate-limited per key.
- `GET /developer/account` — authenticated client and scopes.
- `GET /developer/balance` — requires `balance:read`.
- `GET /developer/transactions/{reference}` — requires `transactions:read` and is restricted to the client's tenant or personal wallet.

## Not implemented / not advertised
Service-specific airtime/data plan discovery, electricity/cable validation and purchase, payment initiation, payment webhook intake and gateway settlement reconciliation are not exposed as completed integrations. They require selected partner documentation, adapters and sandbox acceptance.


## Tenant purchase
- `POST /api/v1/tenant/purchases` — requires an authenticated active tenant member, resolved tenant hostname, `vtu_enabled` subscription entitlement and `Idempotency-Key`. Body: `{ "service_code": "airtime", "customer": { ... } }`.
The response is `202 Accepted`; it does not claim fulfilment. Poll `GET /api/v1/tenant/transactions` or the developer transaction lookup for final status.

## Developer purchase
Tenant developer purchase also requires active `api_enabled` and `vtu_enabled` subscription entitlements. Personal developer clients are not tenant-subscription gated.
`POST /api/v1/developer/transactions` requires `X-API-Key`, the `transactions:write` scope on both the client and key, and an `Idempotency-Key` header. Body: `{ "service_code": "airtime", "customer": { ... } }`. The response is `202 Accepted` and can be pending until supplier requery confirms the outcome.

## Webhooks
Tenant owners can manage endpoints through `GET/POST /api/v1/tenants/{tenant}/webhooks`. The secret is returned once at creation. Events are signed using HMAC-SHA256 over `timestamp.body`; headers include `X-VTU-Timestamp`, `X-VTU-Signature`, `X-VTU-Event`, and `X-VTU-Delivery`. Receivers should verify signatures within a short timestamp tolerance and deduplicate by delivery ID.
