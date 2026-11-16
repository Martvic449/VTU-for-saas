# Upstream supplier setup: VTpass + SMEPlug

## Routing policy

The application supports both suppliers as ordered provider mappings for each catalogue item. Lower mapping priority numbers are attempted first. Configure VTpass as priority `10` and SMEPlug as priority `20` when VTpass is the preferred supplier.

The transaction engine reserves the customer's wallet once. It may move to the next supplier only after the current supplier returns a definitive, documented failure. It does **not** fail over after a timeout, connection error, malformed response, or pending/unknown status. Those outcomes remain pending and are re-queried against the original supplier. This is deliberate protection against duplicate fulfilment.

Each supplier attempt is retained in `transaction_attempts`; the selected supplier, product mapping and price are snapshotted on the transaction. Supplier-specific cost rules can be created against a `provider_service_id` in the integrations screen. A fallback supplier must have an effective cost rule no greater than the transaction sell price.

## VTpass

Official docs:

- <https://vtpass.com/documentation/integrating-api/>
- <https://vtpass.com/documentation/authentication/>
- <https://vtpass.com/documentation/re-query-services/>
- <https://vtpass.com/documentation/service-ids/>

Create a provider connection using adapter `vtpass`, environment `sandbox`, and the three supplier-issued values: API key, public key and secret key. The adapter selects `https://sandbox.vtpass.com/api` for sandbox and `https://vtpass.com/api` for live. The application sends API key + secret key for POST purchase/requery requests; the GET service-discovery flow is not currently exposed in the admin UI.

For each supplier product mapping, configure JSON such as:

```json
{
  "service_id": "mtn",
  "request_fields": {
    "phone": "phone",
    "amount": "amount"
  }
}
```

For a data bundle, configure the exact service ID and variation field supplied by VTpass, for example:

```json
{
  "service_id": "mtn-data",
  "request_fields": {
    "phone": "phone",
    "variation_code": "variation_code"
  }
}
```

These are mapping examples, not a complete catalogue. Use the actual service IDs, variation codes, validation fields and current supplier product definitions in your VTpass account. Create one catalogue/mapping/price combination per sellable product where the price differs.

The platform transaction reference is generated in VTpass-compliant form: the first 12 digits are the Africa/Lagos `YYYYMMDDHHMM` timestamp followed by a random suffix. The adapter submits that value as VTpass `request_id`, uses `/pay` for purchase and `/requery` for status. It considers explicit delivered/success statuses successful, explicit failed/reversed statuses failed, and all other states pending. It converts VTpass `total_amount` from naira to integer kobo when present.

VTpass states that sandbox tests should be completed before requesting live API provisioning. Do not activate a live provider record until VTpass has approved live access and every enabled product has passed sandbox tests.

## SMEPlug v1

The uploaded official Postman collection (`SMEPlug API v1`) establishes the following contract:

- Base URL: `https://smeplug.ng/api/v1`
- Authentication: `Authorization: Bearer <private_key>`
- Network lookup: `GET /networks`; IDs documented in the collection are `1=MTN`, `2=Airtel`, `3=9Mobile`, `4=Glo`.
- Data plans: `GET /data/plans`.
- Data purchase: `POST /data/purchase` with `network_id`, `plan_id`, `phone`, optional `customer_reference`.
- Airtime: `POST /airtime/purchase` with `network_id`, `phone`, `amount`, optional `customer_reference`.
- VTU/share-and-sell: `POST /vtu` with `network_id`, `phone`, `amount`, optional `customer_reference`.
- Transaction status: `GET /transactions/{transaction_reference}`.
- Account balance: `GET /account/balance`.
- Devices: `GET /devices`.
- The collection also documents bank-list, account-resolution and bank-transfer endpoints. These are not enabled in the platform; bank transfer is a money-movement feature and needs separate legal, compliance and partner approval.

Create a provider using adapter `smeplug`, environment `sandbox`, and enter the SMEPlug private key in the private-key/API-token field. The adapter uses the fixed official API host and Bearer authentication. Do not add a custom endpoint or change the host. Provider credentials are encrypted at rest.

For each service mapping, use the supplier's current product catalogue. Example mapping for a data plan:

```json
{
  "network_id": 1,
  "plan_id": 123,
  "smeplug_endpoint": "data/purchase"
}
```

For airtime:

```json
{
  "network_id": 1,
  "smeplug_endpoint": "airtime/purchase"
}
```

Use `smeplug_endpoint: "vtu"` only for the documented VTU Topup / Share & Sell channel. `network_id` and `plan_id` must be taken from SMEPlug's live account catalogue; the example `plan_id` is illustrative, not a real plan. The application sends its transaction reference as `customer_reference` to make support/reconciliation easier.

**Response-schema gap:** the supplied Postman collection has empty saved `response` examples for every request. It documents request URLs, authentication and request bodies, but does not establish exact JSON status/reference fields or terminal success/failure values. The adapter therefore requires these values to be captured from a real SMEPlug sandbox purchase and status response before the provider can be activated:

- `response_status_path`: dot path to the returned status value;
- `response_reference_path`: dot path to SMEPlug's transaction reference (required for `/transactions/{transaction_reference}` requery);
- `response_message_path`: optional dot path for the human-readable message;
- `success_statuses`: exact values SMEPlug uses for final success;
- `failed_statuses`: exact values SMEPlug uses for final failure;
- `response_cost_path`: optional dot path to the actual supplier cost in naira, if returned.

Until the status and reference mappings are grounded in actual sandbox responses, the adapter intentionally returns `pending` and will not trigger supplier failover or refund. Do not guess these fields or mark queued/processing responses as terminal. SMEPlug's collection says status updates are pushed to a configured dashboard webhook, but it does not include a webhook URL, signature/authentication rules or event schema; no inbound SMEPlug webhook has been invented or enabled. The platform uses the documented transaction status endpoint for requery.

The adapter tests use explicitly synthetic fixture payloads only to verify request construction and normalization. They are not evidence of a real SMEPlug response schema or a successful supplier sandbox transaction.
To inspect the live catalogue using the configured sandbox key (provider may remain inactive), run these read-only commands from the application directory:

```bash
php artisan smeplug:inspect smeplug-sandbox networks
php artisan smeplug:inspect smeplug-sandbox data-plans
php artisan smeplug:inspect smeplug-sandbox balance
php artisan smeplug:inspect smeplug-sandbox devices
```

Replace `smeplug-sandbox` with the provider code saved in the platform. The command prints the supplier response to the terminal and does not alter catalogue/pricing records or write account balances. Use the returned network and plan IDs when creating provider-service mappings. Do not run this with a live key in a shared terminal or paste the output if it contains sensitive account information.

## Supplier credentials and activation

Provider credentials are stored using Laravel's encrypted model cast. Never place live keys in source control, frontend code, support tickets or logs. Create separate sandbox and live provider records. `VTU_PROVIDER_ENVIRONMENT` selects which environment can fulfil transactions.

For every product: configure service validation, supplier mapping, provider-specific or generic cost, platform selling price, and any tenant price; test it in supplier sandbox; then activate the provider, mapping and service. The app will not show inactive/unpriced services for purchase.
