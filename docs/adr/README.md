# Project Decision Records

This directory is the canonical record for significant product and architecture decisions that apply to this application edition.

## How to use this directory

- Write one decision per file using `NNNN-short-title.md`; copy [template.md](template.md).
- Give every record a status, date, and decision owner. Use `Proposed`, `Accepted`, `Rejected`, `Deprecated`, or `Superseded by ADR-NNNN`.
- Include the context, decision drivers, considered options, selected decision and rationale, consequences, and how implementation can be checked.
- Keep accepted and superseded records. When a decision changes, write a new ADR that points to the one it supersedes; do not silently rewrite the historical rationale.
- A proposed ADR is not approval to implement. An accepted ADR governs its stated scope, but the task still needs its required human-approved scope and acceptance criteria.
- For material conflicts between an ADR, an instruction, and observed code, stop and report the exact conflict to the project owner. Do not silently choose which authority to follow.
- Before design or implementation, an AI worker must read this index and the relevant accepted ADRs. The project specification and task brief remain required context too.

## Decision record boundaries

- Product and application architecture decisions belong here, in the corresponding application repository.
- Swarm workflow and project execution approvals belong in that project's swarm repository under `project/DECISIONS.md`.
- Tool-neutral swarm operating policy belongs in the shared AI-Swarm repository.

When proposing a new decision from a swarm branch, label it Proposed. The project owner must explicitly accept the decision. Any transfer of the ADR and related implementation into a non-swarm branch follows the project's separate promotion gate.

## Accepted ADRs

- [ADR-0003: Dedicated demo accounts are not a current Community requirement](0003-community-demo-scope.md) — Community is primarily self-hosted; optional future consideration requires a new decision. The superseded ADR-0001 is preserved in Git history.

## Reconciled application decisions (2026-10-25)

These records were present in the edition's Swarm baseline but missing from this application repository's main ADR index. They have been copied to this documentation branch **without changing their original approval status**. The source Swarm baseline remains an historical imported snapshot; this application directory is the intended canonical ADR home. Do not interpret a Proposed ADR as permission to implement.

### Accepted

- [ADR-0002: layered architecture](0002-layered-architecture.md) — **Accepted**.
- [ADR-0004: artwork ownership](0004-artwork-ownership.md) — **Accepted**.
- [ADR-0005: installation and studio identity](0005-installation-and-studio-identity.md) — **Accepted**.
- [ADR-0006: storage boundaries](0006-storage-boundaries.md) — **Accepted**.
- [ADR-0007: image qualification](0007-image-qualification.md) — **Accepted**.
- [ADR-0008: originals and derivatives](0008-originals-and-derivatives.md) — **Accepted**.
- [ADR-0009: portable studio export](0009-portable-studio-export.md) — **Accepted**.
- [ADR-0010: atomic structured import](0010-atomic-structured-import.md) — **Accepted**.
- [ADR-0014: artwork default sort](0014-artwork-default-sort.md) — **Accepted**.
- [ADR-0015: artwork search filter pagination](0015-artwork-search-filter-pagination.md) — **Accepted**.
- [ADR-0016: ui action consistency](0016-ui-action-consistency.md) — **Accepted**.
- [ADR-0017: coding conventions](0017-coding-conventions.md) — **Accepted**.
- [ADR-0018: error handling logging recovery](0018-error-handling-logging-recovery.md) — **Accepted**.

- [ADR-0022: Community and Pro edition independence](0022-edition-independence.md) — **Accepted**; renumbered from Swarm ADR-0003 because Community ADR-0003 already governs demo-account scope.

### Proposed / unresolved

- [ADR-0011: artwork identity relationships](0011-artwork-identity-relationships.md) — **Proposed**.
- [ADR-0012: supplies catalog](0012-supplies-catalog.md) — **Proposed**.
- [ADR-0013: materials ledger](0013-materials-ledger.md) — **Proposed**.

**Not copied from Pro:** ADR-0019/0020 Cloud Alpha architecture/security plans and ADR-0021 Artwork Offerings; their Community scope is not approved. Community demo-account ADR-0003 remains authoritative and was not replaced by the Swarm's differently numbered ADR-0021.

