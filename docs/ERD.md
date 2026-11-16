# Initial entity relationship map

```mermaid
erDiagram
  USERS ||--o{ TENANT_USER : membership
  TENANTS ||--o{ TENANT_USER : has
  TENANTS ||--o{ TENANT_DOMAINS : resolves
  TENANTS ||--o{ WALLETS : owns
  USERS ||--o{ WALLETS : owns
  WALLETS ||--|| WALLET_LEDGER_ACCOUNTS : maps
  LEDGER_ACCOUNTS ||--o{ WALLET_LEDGER_ACCOUNTS : backs
  LEDGER_JOURNALS ||--o{ LEDGER_ENTRIES : contains
  LEDGER_ACCOUNTS ||--o{ LEDGER_ENTRIES : posts
  WALLETS ||--o{ WALLET_ADJUSTMENTS : requests
  USERS ||--o{ WALLET_ADJUSTMENTS : requests_or_approves
  LEDGER_JOURNALS ||--o| WALLET_ADJUSTMENTS : posts
  WALLETS ||--o{ DEPOSITS : receives
  DEPOSITS ||--o| LEDGER_JOURNALS : credits
  PROVIDERS ||--o{ PROVIDER_SERVICES : offers
  SERVICE_CATALOGUE ||--o{ PROVIDER_SERVICES : maps
  PROVIDERS ||--o{ TRANSACTIONS : snapshotted_provider
  PROVIDER_SERVICES ||--o{ TRANSACTIONS : snapshotted_product
  SERVICE_CATALOGUE ||--o{ PRICING_RULES : priced
  WALLETS ||--o{ TRANSACTIONS : funds
  TRANSACTIONS ||--o{ TRANSACTION_ATTEMPTS : attempts
  PROVIDERS ||--o{ TRANSACTION_ATTEMPTS : receives
  TENANTS ||--o{ SUBSCRIPTIONS : subscribes
  PLANS ||--o{ SUBSCRIPTIONS : defines
  API_CLIENTS ||--o{ API_KEYS : authenticates
  API_CLIENTS ||--o{ API_REQUESTS : logs
  API_CLIENTS ||--o{ WEBHOOK_ENDPOINTS : configures
  WEBHOOK_ENDPOINTS ||--o{ WEBHOOK_DELIVERIES : delivers
  TENANTS ||--o{ AUDIT_LOGS : scopes
  TENANTS ||--o{ RISK_EVENTS : flags
  USERS ||--o{ AUDIT_LOGS : acts
```

The migration set also includes payment event idempotency, KYC records, risk events, reconciliation runs, notifications, outbox events, support tickets, Spatie role/permission tables, Sanctum tokens, queue tables and audit logs. Tenant-owned reads must always derive tenant identity from the verified request hostname and active membership, never a caller-supplied `tenant_id`.
