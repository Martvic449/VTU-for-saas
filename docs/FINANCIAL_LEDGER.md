# Financial ledger and transaction accounting

## Money representation
All NGN amounts are integer minor units (kobo). PHP floats are prohibited for money. Amounts are validated as positive integers before posting.

## Core invariants
- `ledger_journals` and `ledger_entries` are the financial source of truth; wallet balances are derived from ledger entries.
- Every journal has at least two entries and total debits equal total credits.
- Posted journal entries are append-only. Corrections use a new reversal journal linked by `reversal_of_id`.
- Business references and idempotency keys have database uniqueness constraints.
- Account rows are locked in deterministic ID order before a journal is posted.
- Wallet spending and the transaction reservation happen in one database transaction.
- Provider `submitted` and `pending` outcomes are ambiguous; do not retry or refund until requery establishes a definitive result.

## Initial chart of accounts
- `PAYMENT_CLEARING_NGN` — asset / debit normal.
- `PROVIDER_FLOAT_NGN` — asset / debit normal.
- `PROVIDER_PAYABLE_NGN` — liability / credit normal.
- `SERVICE_REVENUE_NGN` — income / credit normal.
- `PROVIDER_COST_NGN` — expense / debit normal.
- `PLATFORM_SUSPENSE_NGN` — liability / credit normal.
- `WALLET_{id}` — one liability account per wallet / credit normal.

The initial chart is a technical starting point, not an accountant-approved final chart. A Nigerian accountant must review the final chart, revenue recognition, provider float treatment, taxes and settlement policy.

## Deposit posting
A gateway adapter must validate the webhook signature and independently verify payment server-to-server. The posting service checks amount, currency, gateway and business reference, records a unique gateway event, then posts debit payment clearing / credit wallet liability. Duplicate event IDs are ignored. A browser redirect alone must never credit a wallet.

## VTU purchase posting
1. Lock the wallet and wallet ledger account.
2. Resolve active service and effective price; snapshot sell price, cost and rule ID.
3. Check available credit from posted ledger entries.
4. Create transaction with a unique `(wallet_id, idempotency_key)`.
5. Reserve: debit customer wallet liability / credit platform suspense liability.
6. Submit to provider using the platform transaction reference as idempotency reference where supported.
7. Confirmed success: debit suspense; credit provider payable for actual provider cost; credit service revenue for margin.
8. Confirmed failure: reverse the original reservation journal exactly once.
9. Ambiguous timeout: mark pending and requery; no blind retry, refund or provider failover.

Provider cost and sell price are immutable transaction snapshots. Actual settlement cost may be updated only from a verified provider result and within the reserved sale amount; any exception goes to finance review.

## Manual adjustments
Manual wallet adjustments are maker-checker controlled. A request stores wallet, amount in kobo, direction, reason, evidence reference and requester. A different authorised finance user must approve or reject it. Approval posts a balanced journal using dedicated adjustment control accounts and records the approver and decision reason. A debit request is checked at request time and again at approval time. Rejection never changes the ledger.
