# ADR-0017: Coding conventions and maintainability

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed coding conventions and maintainability requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Use layer-based folders (controllers, services, repositories, models, etc.), with optional business-related subfolders inside each layer; do not require one folder per individual action or feature-root folders. Group related import/export UI coordination when sensible. Keep controllers thin, services responsible for rules, repositories focused on persistence and authorized queries, and view models for presentation. Push efficient authorized search, filtering, sorting, pagination, and targeted related-image selection into database queries where appropriate; do not load entire collections unnecessarily. After Cloud Alpha, audit both editions for separation-of-concerns violations; remediate from Cloud Release 2, in independently testable increments and within the Swarm's 1,000 changed LOC per review cap. Avoid refactoring merely for stylistic uniformity.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Audit representative verticals; verify query scope, pagination, and tests; report violations and staged remediation.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
