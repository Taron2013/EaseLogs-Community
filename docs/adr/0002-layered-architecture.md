# ADR-0002: Layered application architecture

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed layered application architecture requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Controllers coordinate HTTP concerns; services own business rules and workflows; repositories handle persistence, CRUD, and authorized queries; presentation/view models shape UI data. Avoid business rules in controllers and repositories. Adopt incrementally; inspect existing code before refactoring.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Compare representative verticals with layer boundaries; test service rules independently.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
