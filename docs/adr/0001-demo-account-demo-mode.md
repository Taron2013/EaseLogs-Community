# ADR-0001: Restrict the demo account to demo-enabled deployments

- **Status:** Accepted
- **Date:** 2026-10-23
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs provides a shared demo identity so visitors can explore the public demo deployment. The demo identity is system-provisioned from environment configuration; it is not an account visitors should create through registration.

The demo account password is the sole owner-approved password-in-repository exception for this public demo purpose. Its presence in a repository or test fixture must not make the identity usable on a non-demo runtime. In particular, turning demo mode off must disable both the demo-specific one-click route and ordinary password authentication for that identity, even if a previously provisioned row remains in the database.

`APP_ENV=production` does not itself enable or isolate demo mode. The explicit `EASELOGS_DEMO_MODE` setting is the runtime gate. The one-click setting controls only direct one-click login.

## Decision drivers

- Keep the demo identity limited to deployments intentionally configured as public demos.
- Do not make the demo identity available through visitor registration.
- Ensure disabling demo mode blocks the demo identity through every authentication path.
- Avoid deleting persistent account data as a side effect of turning demo mode off.
- Keep other legitimate accounts and normal authentication unaffected.
- Do not treat demo mode as proof that the application is read-only.

## Options considered

1. **Gate only one-click login:** Simple, but the existing account may still authenticate through the normal login form after demo mode is disabled.
2. **Gate demo provisioning and all login paths:** Restricts the identity to demo-enabled deployments while preserving other login behavior.
3. **Delete the account when demo mode is disabled:** Prevents login but is destructive and couples a configuration switch to database cleanup.

## Decision

Use option 2.

- Provision or refresh the configured demo account only when `EASELOGS_DEMO_MODE=true` and the demo identity's required environment settings are present. Provision it through the application's demo setup/ensure mechanism, not public visitor registration.
- Permit one-click login only when demo mode is enabled, the account is configured, and `EASELOGS_DEMO_ALLOW_ONE_CLICK_LOGIN=true`.
- When demo mode is disabled, reject normal password login for the configured demo identity even if its account row already exists. Do not automatically delete that row. Normal authentication for non-demo accounts continues unchanged.
- Treat the committed public demo password as the single owner-approved password exception. It is only for the public demo identity; this does not authorize storing any other password, token, key, or secret in code or documentation. A non-demo runtime must not provision or authenticate the demo identity, regardless of where its configuration value is stored.
- Demo-mode action restrictions and read-only behavior are separate requirements. This ADR does not make an installation read-only; the relevant demo capabilities must be separately configured and tested.

## Consequences

### Positive

- The demo identity has one explicit availability rule that applies to one-click and ordinary login.
- Disabling demo mode is sufficient to make the demo identity unusable without destructive database changes.
- Production demo deployments can enable the account independently through their own environment configuration.

### Negative or accepted tradeoffs

- The login flow needs an explicit check for the configured demo identity when demo mode is off.
- Existing demo account rows may remain in a database after the switch is disabled; they are inactive until demo mode is enabled again.
- Tests must cover authentication with demo mode both enabled and disabled, including a persisted demo account.

## Verification

- With demo mode enabled and credentials configured, the demo account is provisioned and normal password login works.
- With demo mode enabled and one-click login enabled, one-click login works without asking the visitor to enter a password.
- With demo mode enabled and one-click login disabled, the one-click route is unavailable.
- With demo mode disabled, both one-click and ordinary password login for the configured demo identity are denied, including when the user row already exists.
- With demo mode disabled, ordinary login for a non-demo account remains unchanged.
- Public registration never creates or claims the configured demo identity.
- Separate tests verify any read-only or demo write restrictions; this ADR alone makes no such claim.

## References

- [Demo configuration](../../config/easelogs.php)
- [Demo account behavior](../../app/Support/DemoUser.php)
- [One-click login](../../app/Http/Controllers/DemoLoginController.php)
- [Ordinary login](../../app/Http/Controllers/LoginController.php)
- [Demo deployment guide](../DEMO_VPS_DEPLOYMENT.md)
- [Demo safeguard tests](../../tests/Feature/DemoModeSafeguardsTest.php)
