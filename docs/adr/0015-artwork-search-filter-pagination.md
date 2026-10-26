# ADR-0015: Artwork search, filters, and pagination

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed artwork search, filters, and pagination requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Search the full authorized artwork collection, not just the currently displayed page. Across filter categories apply AND; within one category, selected values apply OR. Filter and sort before pagination; show 20 artworks per page. Clear Filters resets all filters and default sort. For no results display exactly: 'No artwork matches your current search or filters. Clear your filters to return to your artwork collection, or adjust your selections to try again.' Preserving list state on return from detail is a post-cloud nice-to-have.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Test authorization, filter combinations, sorting before pagination, clear, and no-results copy.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
