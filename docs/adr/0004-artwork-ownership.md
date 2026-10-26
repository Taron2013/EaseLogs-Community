# ADR-0004: Artwork ownership and attribution

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed artwork ownership and attribution requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Distinguish application account or Studio tenancy, record ownership, record creator, copyright holder, and physical artwork owner. Do not infer legal ownership from tenant or creator. Authorization follows the relevant CE user or Pro Studio boundary.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Verify data and authorization tests distinguish these roles.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
