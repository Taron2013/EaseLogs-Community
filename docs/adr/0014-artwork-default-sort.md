# ADR-0014: Default artwork sorting

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed default artwork sorting requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Default artwork ordering is newest-first by effective date: completed_date when present, otherwise updated_at, otherwise created_at. Use deterministic tie-breaking. Preserve currently available explicit sort choices; additional filters may be deferred. Existing application defaults may differ and require future change.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Test fallback dates, ties, and edition parity.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
