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

- [ADR-0001: Restrict the demo account to demo-enabled deployments](0001-demo-account-demo-mode.md) — the configured demo identity is provisioned and usable only when demo mode is enabled; disabling demo mode blocks one-click and ordinary password login for that identity.
