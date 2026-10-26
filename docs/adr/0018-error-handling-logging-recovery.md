# ADR-0018: Error handling, logging, and recovery

- **Status:** Accepted
- **Date:** 2026-10-25
- **Decision owner:** Project owner
- **Supersedes:** None

## Context and problem

EaseLogs Community and Pro require a consistent, reviewable decision baseline for Cloud Alpha and later releases. This record captures the project's discussed error handling, logging, and recovery requirements without authorizing code changes.

## Decision drivers

- Preserve authorized user data and edition boundaries.
- Keep Cloud Alpha scope realistic; distinguish later release blockers and deferred enhancements.
- Give the AI Swarm testable requirements without prescribing unnecessary implementation.

## Options considered

1. **Adopt the recorded policy:** Use the agreed behavior and explicit release constraints.
2. **Leave behavior implicit:** Risks inconsistent implementations and unreviewed design choices.

## Decision

Use clear actionable field validation messages for ordinary errors (e.g. invalid date), but generic messages for security-sensitive detection and unexpected exceptions; never expose stack traces, SQL, paths, secrets, or detection rules. Semicolons and other punctuation alone are not evidence of attack. For unexpected failures show an unpredictable per-attempt error reference ID, correlated to protected sanitized logs. Cloud Alpha requires protected logging, daily rotation, 14-day retention from error time, and authorized admin download of relevant logs; redact secrets and private data. Hourly minimal operational alert emails during persistent storage/log danger are deferred. Preserve form values on failed saves; artwork updates are atomic/all-or-nothing, retaining previously saved data. For image-only bulk ZIP imports, allow partial success with matched, valid-unmatched, invalid-rejected, and unexpected-error outcomes; show copyable filenames and reasons on screen, with no mandatory downloadable report. Structured Studio package imports remain atomic (ADR-0010). Valid unmatched images enter reconciliation and have a 14-day policy measured from original successful upload, not from later review or retries; automated deletion after verifying still unmatched is deferred to Cloud Release 2 or 3, and may not be enforced in Alpha. SHA-256 original-file hash duplicate detection applies to single and bulk uploads only within the current Studio or CE installation; display matched filenames, offer skip, permitted reuse, or explicit self-service separate-copy override. Never overwrite existing files, cross-deduplicate Studios, or treat hash as artwork identity. Pro separate copies count against Studio quota; log decisions. Interrupted ZIP retries should skip already accepted images without duplicating them, including user-authorized overrides, while allowing intentional distinct new operations; the Swarm must raise implementation tradeoffs if reliable retry recognition is unexpectedly complex. Cloud Alpha may require resubmitting ZIP; server-side resume without reupload is deferred. Protect new artwork creation against repeated submissions of the same attempt while allowing distinct records with identical details; implement in Alpha if low-risk, but mandatory blocker by Cloud Release 2 in both editions. Check existing behavior first. On-screen validation failures identify correctable fields; no automatic draft persistence or full browser-crash recovery is promised.

## Consequences

### Positive

- Clear, edition-aware behavior and release expectations for implementation reviews.

### Negative or accepted tradeoffs

- Implementation may be deferred as stated above; acceptance of this ADR does not authorize unsupervised code changes.
- If existing code conflicts with this policy, report the conflict and obtain scope approval before modifying it.

## Verification

Test privacy-safe messages and log references, log rotation and download, failed-save value preservation and atomicity, single/bulk duplicate choices and Studio isolation, retry idempotency, unmatched retention timestamps, and artwork creation repeated-request handling.

## References

- [ADR index](README.md)
- Project owner discussion and approved Cloud Alpha / Cloud Release 2 planning.
