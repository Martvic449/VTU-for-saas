# Provider adapter contract

## Contract

Every supplier adapter implements `App\Contracts\Providers\VtuProvider`:

- `purchase(serviceCode, payload, idempotencyReference)`
- `requery(providerReference, idempotencyReference)`

Return `outcome` as `successful`, `failed`, or `pending`, optionally with `provider_reference`, `message`, and `data.actual_cost_minor`.

## Configured HTTP adapter

`configured_http` is a generic JSON/HTTPS transport. It is not a supplier-specific integration. It sends the service code, mapped supplier product code, customer payload and platform transaction reference in the request body, and includes an idempotency header (configurable name, default `Idempotency-Key`). Settings can configure purchase/requery URLs, authentication header/prefix, response JSON paths, and terminal status strings.

Unknown response statuses are pending. Connection failures are pending. Do not add automatic HTTP retries to a purchase request. Supplier-side idempotency and query-by-client-reference behavior must be confirmed in the supplier's official docs before relying on it.

## Before enabling a supplier

1. Obtain official API docs, commercial agreement and sandbox credentials.
2. Confirm service catalogue codes and product mapping.
3. Confirm supplier idempotency semantics and how to query an ambiguous request.
4. Confirm exact terminal success/failure response values and the meaning/units of returned cost.
5. Run sandbox success, definitive failure, timeout, malformed response, delayed success and duplicate submission tests.
6. Confirm supplier float/settlement process and configure monitoring/reconciliation.
7. Keep provider status inactive until operations signs off.

## Safety rule

A timeout, HTTP 5xx, unknown status, or missing supplier reference is not proof of failure. Keep the wallet reservation and transaction pending for requery/manual reconciliation. Never automatically fail over to another provider while the original outcome is ambiguous.

The transaction stores a snapshot of the selected provider ID, provider-service mapping ID, supplier product code and request field mapping at reservation time. A later mapping edit does not silently reroute an already reserved purchase. If the snapshotted provider/mapping becomes inactive before dispatch, the reservation remains for operator review rather than being redirected.
