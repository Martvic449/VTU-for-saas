# VTUPress archive: preliminary technical triage

Archive inspected: `vtupress.zip`. It contains VTU Press Ultimate 3.1.7, a WordPress plugin with a standalone compatibility runtime. It is PHP code, but it is not a Laravel application. Its standalone runtime emulates parts of WordPress and uses a custom wpdb-like MySQL layer. The bundled `database.sqlite` is zero bytes and contains no schema or data.

## Reuse decision
Do not deploy this package as the new multi-tenant SaaS core. The new platform is being started as a clean Laravel application. VTUPress may be used as a reference for service catalogue concepts and provider workflows only after a deeper source/license/security review. Do not copy its secrets, runtime, wallet implementation or assumptions into the new application.

## Findings requiring action
- `plugin.php`, `standalone/class-updater.php`, and `standalone/installer.php` contain hard-coded GitHub personal access tokens. The token owner should revoke/rotate them immediately. Do not repeat or reuse them.
- `admin/items-includes/footer.php` embeds a third-party chat widget API key. Confirm ownership and rotate it if it is a live credential; remove it from public source and move secrets to server-side configuration.
- The user wallet class updates a `balance` column directly using floating-point values and writes separate wallet history records. This is not the immutable double-entry journal required by the SaaS blueprint.
- The transaction processor includes reference checks and locking, but the archive alone does not establish the end-to-end idempotency, atomic ledger, provider ambiguity, webhook replay, tenant isolation or reconciliation guarantees required by the blueprint.
- The package requires WordPress semantics and includes a standalone shim; this increases migration and maintenance complexity versus a native Laravel codebase.
- The package readme identifies GPLv3-or-later licensing. Any code reuse must be reviewed for license compatibility and obligations before distribution.

## Scope limitation
This is a static archive triage, not a complete penetration test, code audit, dependency audit or runtime verification. PHP linting and application-level tests have not been run against the full package.
