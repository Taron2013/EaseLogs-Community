# EaseLogs Community Agent Instructions

These instructions guide AI work in this application repository. They are not task authorization or technical access controls.

## Before starting

- Confirm the repository, current branch, and task scope with the project owner. Do not assume a task authorizes work in another repository or branch.
- Read `README.md`, `docs/adr/README.md`, every applicable accepted ADR from this branch, the relevant product documentation, and the task's acceptance criteria.
- Inspect the relevant code and tests before proposing or making changes. Treat the checked-out branch as the source of truth; do not assume another branch has the same implementation or decisions.
- For the demo account, follow [ADR-0001](https://github.com/Taron2013/EaseLogs-Community/blob/main/docs/adr/0001-demo-account-demo-mode.md).

## One-agent task boundaries

- Work only on the bounded task explicitly authorized by the project owner. Do not spawn or delegate to other agents unless the owner explicitly asks for that.
- Edit only files needed for the authorized task. Do not expand scope into unrelated cleanup, backlog work, or application redesign.
- Work on `main` only when the owner explicitly authorizes the task on `main`; otherwise use the branch the owner specifies.
- Stop and ask the owner when requirements or evidence are unclear, instructions conflict, or work would change product behavior, architecture, UX, security or privacy policy, credentials policy, dependencies, or acceptance criteria beyond what was approved. A proposed ADR is not approval to implement it.
- Keep code review within the shared limit of 1,000 changed code lines. If the task exceeds that limit, report the size and ask the owner to split or explicitly revise the limit.

## Git and release boundaries

- Do not deploy, publish, merge, force-push, or move code between swarm and application repositories without the owner's explicit authorization for that action and exact revision.
- Do not silently overwrite existing work or discard uncommitted changes. Inspect repository status before making changes and stop if unexpected changes could be affected.
- Report the repository, branch, starting and resulting revision, changed files, checks run and results, and any unresolved questions or limitations.

## Decisions and secrets

- Treat accepted ADRs as project decisions within their stated scope. If code and an accepted ADR disagree, report the discrepancy; do not silently rewrite the ADR or decide which one to ignore.
- Only the project owner or an owner-designated coordinator may maintain this `AGENTS.md`. An assigned agent must treat it as read-only and propose changes separately.
- Never print or add credentials, tokens, keys, or secrets to code or reports. The configured public demo password is the sole owner-approved exception, strictly as described in ADR-0001; this exception does not authorize any other password or secret.
