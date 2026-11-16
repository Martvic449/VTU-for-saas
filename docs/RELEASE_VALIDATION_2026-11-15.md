# Release validation — 15 November 2026

## Included in this release

- Public marketing homepage at `/` with customer, reseller and developer positioning.
- Public Privacy Policy at `/privacy-policy` and Terms & Conditions at `/terms-and-conditions`.
- Public and authenticated application shells remain separate; authenticated pages use the workspace layout and legal links without the public marketing navigation.
- Legal operator name, address, privacy contact and support contact are environment-configurable. These must be populated and the draft legal text reviewed before public launch.
- GitHub source-control ignore rules and a Hostinger deployment guide covering private application storage, public document root, environment setup, cron/worker constraints and first-deployment checks.

## Static checks

Run PHP syntax checks across application, routes, migrations, seeders, configuration and tests. Validate JSON/YAML and JavaScript assets. Validate the ZIP archive after packaging.

## Not certified by this release note

Composer dependency resolution and `composer.lock` generation; Laravel boot; Blade compilation; route-cache generation; MySQL migrations; Pest execution; queue-worker and scheduler operation; browser testing; supplier/payment sandbox tests; penetration testing; Hostinger compatibility; and legal review have not been completed in this environment. Do not interpret static checks or the presence of tests as passing runtime tests.

## Go-live gates

Do not enable live payments or supplier fulfilment until the production dependency lock is committed, Laravel and database tests pass on the target PHP/MySQL environment, supplier and payment sandbox acceptance is complete, legal details and policies are approved, a durable queue worker and scheduler are operating, and security/backup/restore checks are signed off.
