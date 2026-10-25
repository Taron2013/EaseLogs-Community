# ADR-0003: Dedicated demo accounts are not a current Community requirement

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** ADR-0001 (dedicated demo-account requirement in Community only)

## Context
EaseLogs Community is primarily intended for self-hosted use by individual artists and small studios. A special public demo identity is not necessary for the current Community product scope. The earlier Community ADR-0001 imposed demo-account requirements that are no longer approved for this edition. EaseLogs Pro retains its own independent demo-account decision.

## Decision
- Community does not require a dedicated demo account, public demo mode, or demo-specific authentication behavior in the current scope.
- A Community demo feature may be proposed later if a concrete use case justifies it, but requires a new owner-approved decision.
- No demo-specific implementation task is currently authorized for Community.
- This is a **requirements/documentation decision only**. It does not authorize removing, weakening, or changing existing Community authentication code or other application behavior.
- Normal self-hosted account authentication, authorization, file safety, and security controls remain necessary.
- Pro's ADRs, requirements, and implementation are unchanged.
- Previous Community demo ADR-0001 is superseded; its prior text remains recoverable in Git history rather than maintained as a current Community ADR file.

## Verification
- Community ADR index and agent instructions identify this scope decision, not demo-account implementation instructions.
- Community Swarm decision log and backlog no longer present demo-account implementation as a current requirement.
- No application code or configuration is changed under this decision.
