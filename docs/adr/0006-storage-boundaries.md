# ADR-0006: Storage boundaries and transitions

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed storage boundaries and transitions requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Community storage is installation-managed; hosted Pro storage is platform-managed. Only one write target is active for a given storage operation. Changing a configured target does not silently relocate existing files; preserve access to existing file locations until deliberately migrated.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Verify old files remain readable after target changes and tenant isolation holds.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
