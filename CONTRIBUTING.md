# Contributing to EaseLogs Community Edition

Thank you for helping improve EaseLogs Community Edition. This project is distributed under the [EaseLogs Community License](LICENSE). By contributing, you agree that your submissions may be used under that license and the [contribution terms in LICENSE Section 5](LICENSE).

Read [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md) and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) before submitting changes.

---

## What you affirm by contributing

When you open a pull request or otherwise submit a contribution, you confirm that:

1. **You have the right to submit the work** — you authored it, or you have permission from the copyright holder.
2. **You are not submitting incompatible copyleft code** — no GPL-only, AGPL-only, LGPL-only, or other code whose license would force the combined work to be redistributed only under copyleft terms incompatible with the EaseLogs Community License and our dependency stack (MIT/BSD/Apache-dominant PHP ecosystem).
3. **You are not submitting unattributed third-party snippets** — including code copied from Stack Overflow, public GitHub repositories, blogs, tutorials, or AI-generated output **unless** you have the right to use it and any required attribution or license conditions are met and documented in the PR.
4. **Your contribution may be distributed under the EaseLogs Community License** — including in future releases, and as described in LICENSE Section 5 (contributor grant to Douglas Cross).
5. **You disclose third-party material** — if your change includes third-party code, assets, fonts, images, or substantial derived snippets, describe them in the pull request (origin, license, and how it is compatible).

---

## Dependencies

Before adding or upgrading a Composer or npm dependency:

- Check the package **license** for compatibility with the EaseLogs Community License and existing MIT/BSD/Apache dependencies.
- Flag **GPL/AGPL/LGPL-only** packages — they are generally **not acceptable** without explicit maintainer approval and legal review.
- Note **MPL-2.0** or other licenses that may affect build or distribution (see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)).
- Update [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) or run `php scripts/generate-third-party-notices.php` when `composer.lock` changes; update npm notices when `package-lock.json` changes.

---

## Contribution workflow

1. **Fork** the repository (or branch from `main` if you are a maintainer).
2. **Create a branch** with a clear name (for example `fix/artwork-index-sort` or `docs/install-guide-clarity`).
3. **Make focused changes** — one logical change per pull request when possible.
4. **Run tests** from the project root:
   ```bash
   ./vendor/bin/phpunit
   ```
   Or:
   ```bash
   composer test
   ```
5. **Build frontend assets** if you changed `resources/css`, `resources/js`, or `vite.config.js`:
   ```bash
   npm install
   npm run build
   ```
6. **Open a pull request** that includes:
   - **What** changed and **why**
   - **How to test** the change
   - **License / provenance notes** when you touched third-party code, copied patterns, added dependencies, or included generated content
7. Wait for review. Maintainers may request changes or additional license information.

---

## Code style and scope

- Match existing PHP and Blade conventions in the files you edit.
- Keep Community Edition scope: do not add paid-tier product marketing, Pro-only deployment docs, or features outside the Community Edition scope without maintainer agreement.
- Do not commit secrets (`.env`, keys, certificates, `database.sqlite`, user uploads).

---

## Questions

For licensing questions about **commercial use** of EaseLogs, see [COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md). For contribution licensing, refer to [LICENSE](LICENSE) Section 5.
