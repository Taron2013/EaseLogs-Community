# ADR-0023: Preserve high-resolution original artwork images up to 200 megapixels

- **Status:** Proposed — incomplete; NOT approved for implementation
- **Date:** 2026-11-09
- **Decision owner:** Project owner
- **Edition:** Community; shared requirement with Pro ADR-0045
- **Related:** ADR-0007 image qualification; ADR-0008 originals and derivatives; Pro UAT-002

## Context
Current qualification constraints reportedly include 8192 pixels per edge and 16 million total pixels, which can reject legitimate artwork master photography. Pro UAT identified a valid 5818 x 4633 JPEG whose ZIP bytes matched its manifest SHA-256 but whose preview showed a misleading checksum mismatch after image qualification. Community's actual behavior must be verified separately.

## Proposed decision
1. Community shall accept and preserve valid original master and progress photos with dimensions up to and including **200,000,000 pixels**, including 12000 x 12000 (144 MP), 14000 x 14000 (196 MP), and 20000 x 10000 (200 MP).
2. The existing 8192-pixel edge ceiling shall not prevent those required cases. Define separate defensible extreme-dimension/aspect-ratio bounds after security review.
3. Original image bytes must be preserved without forced resizing, recompression, or destructive conversion. Validate integrity with SHA-256 over actual original bytes during export/import.
4. Separate secure ingestion/archival validation from derivative generation. Do not require full-resolution GD decoding merely to store an original. Use bounded safe format inspection and streaming IO; reject malformed and unsafe inputs.
5. Thumbnails, previews, scaling, and other derivatives use independent resource, pixel, memory, timeout, and concurrency limits. Processing failure must not corrupt or silently replace the original.
6. Enforce documented file-size, storage, supported-format, and deployment resource limits. This is not an unlimited-file or arbitrary-format policy.
7. Report checksum failures separately from qualification failures with accurate reason codes; never bypass SHA-256 or accept unsafe images.
8. This Community requirement is self-hosted and must not introduce Pro cloud-native dependencies or Pro-only functionality.

## Proposed acceptance tests
- Upload/export/import valid 12000 x 12000 and 20000 x 10000 originals with byte-identical SHA-256.
- Test the 200 MP boundary, unsupported and malformed formats, forged dimensions, truncated data, oversized files, extreme aspect ratios, ZIP traversal, and checksum mismatches.
- Verify original ingestion avoids decoding the entire 200 MP image in PHP memory.
- Verify derivative failure does not destroy originals and gives a visible status.
- Verify existing accepted ADR-0007 and ADR-0008 compatibility; any conflict needs an explicitly approved superseding decision.

## Open questions
- Select secure metadata/format validation method and allowed formats.
- Define maximum edge/aspect ratio, byte limits, and worker resource policy.
- Inventory Community upload/import/export paths and determine migration/testing needs.



## Owner-approved original image acceptance limits (2026-11-09)
The following **default acceptance limits are approved requirements for both Community and Pro**, but **implementation is not yet authorized**:

| Constraint | Approved default | Enforcement |
|---|---:|---|
| Total image pixels | 200,000,000 inclusive | width × height <= 200,000,000 |
| Maximum individual edge | 30,000 pixels inclusive | width <= 30,000 and height <= 30,000 |
| Maximum individual original file | 1 GiB (1,073,741,824 bytes) inclusive | actual file bytes <= 1,073,741,824 |

These constraints apply to original master images and original progress photos, whether uploaded individually or restored from a supported image import. All three must pass. The limits are independent: a file within the pixel cap may still exceed the byte cap, and vice versa. Existing 16 MP, 8192-edge, and 64 MB decoded-memory qualification restrictions must not block a valid original within these limits. Full-resolution decoding must not be required merely for archival acceptance. Processing-specific safety limits remain mandatory.

Administrators may explicitly configure lower or higher limits only through a separately reviewed, secure deployment configuration; defaults remain as above. Any change must preserve bounded validation, supported-format rules, storage controls, and truthful user-facing error messages. These approved image limits **do not change ZIP transport, archive extraction, or metadata-entry limits**. Any lower effective HTTP/PHP/nginx limit must be documented and surfaced safely to users.


## Owner-approved configuration and deployment contract (2026-11-09)
The owner approves exposing the three accepted original-image defaults through Laravel's configuration system for **both Community and Pro**. This is a requirement approval, not permission to implement or deploy.

- Define the default pixel cap (200000000), edge cap (30000), and file-byte cap (1073741824) in the edition's Laravel `config/easelogs.php`, with optional `EASELOGS_IMAGE_MAX_PIXELS`, `EASELOGS_IMAGE_MAX_EDGE`, and `EASELOGS_IMAGE_MAX_FILE_BYTES` environment overrides. Use `config()` in runtime services; avoid reading `env()` directly outside configuration files.
- Document all three variables, units, defaults, and safety implications in each edition's tracked `.env.example`; never commit or overwrite a real `.env` containing installation secrets.
- Update relevant deployment/setup documentation and deployment-script templates so self-hosted production operators know how to adjust the limits, validate effective configuration, and refresh Laravel's config cache. Deployment must preserve existing overrides; do not silently replace an installation's `.env`.
- Reconcile the pre-existing `photo_max_kb` setting to avoid conflicting per-image size ceilings, and check PHP/nginx/web-server request-body settings and import ZIP limits separately. Describe the effective lower limit and ensure safe actionable errors.
- Validate configured values at startup/deployment, reject invalid/unsafe values, and keep administrative overrides subject to resource safety. Original archival limits must remain separate from derivative-worker memory/CPU/time limits.
- Include tests for defaults, overrides, invalid values, existing-installation compatibility, and no accidental edition-specific feature leakage.

## Accepted UI rejection-reporting requirement (owner approved 2026-11-09)
This subsection is **approved product behavior** for both editions. The overall 200 MP architectural ADR remains Proposed and does not authorize implementation.

- Display the affected file's safe display name, a concise rejection category, and an actionable next step. For ordinary configurable constraints (resolution, file bytes, archive bytes), show the observed value and effective limit where available.
- Use distinct states for **verified**, **rejected**, **unmatched**, and **integrity failure**; never label a qualification exception as a checksum mismatch. Display aggregate counts and per-file reasons during batch preview.
- Supported formats may be shown. Malformed/unsafe image or ZIP structures receive a generic safe message, without disclosing exploit detection logic, parser internals, stack traces, server paths, SQL, exception messages, secrets, or actual/expected hashes.
- Keep detailed diagnostics in access-controlled server logs with correlation identifiers and privacy-conscious retention; do not log entire image bytes or secrets. Ensure public errors do not vary in ways that expose sensitive security rules.
- Apply client-side preflight when possible, but enforce limits again server-side. Handle upstream 413 and PHP/nginx failures with a safe generic upload-too-large message if exact effective limit cannot be determined. Do not promise an exact limit the server cannot verify.
- Distinguish storage, format, integrity, qualification, ZIP transport/extraction, and artwork matching failures; reject unsafe content fail-closed. No bypass, automatic resize, or weakening of security checks is authorized.
- Test that ordinary over-limit errors are actionable, malicious/corrupt cases are non-revealing, and valid files are not misclassified.

**Proposal only: no implementation, deployments, merges, or changes to canonical main are authorized.**


## Owner-approved real-world format and master-image clarification (2026-11-09)

This subsection clarifies the shared Community/Pro archival acceptance requirements; it does **not** authorize implementation, deployment, or a change to this ADR's overall Proposed status.

- **Current priority formats:** JPEG/JPG and PNG. A representative real-world upscaled artwork master is approximately **12,000 × 11,000 = 132,000,000 pixels (132 MP)**. Valid JPEG and PNG originals of this size must be eligible for byte-preserving admission, export, and reimport within the independently enforced **200,000,000-pixel**, **30,000-pixel-per-edge**, and **1 GiB per-file** approved limits. Do not introduce a lower JPEG/PNG master cap that excludes this example.
- Preserve original file bytes, embedded metadata, and integrity hash. Derivative previews, thumbnails, watermarks, or scaling must not overwrite or silently convert the master. If derivative generation cannot safely process a master, retain the admitted original and show a safe placeholder/status.
- **TIFF/BigTIFF:** Anticipated future archival format, **research/proposal only**; not currently used for the owner's masters, not approved as an accepted format, and **not part of UAT-002 Slice B implementation**. Research should separately consider multi-page/IFD structures, bit depths, compression, metadata, streaming structural inspection, and isolated derivative processing. Any edition-specific implementation requires separate review and approval.
- **PSD:** Proprietary working-document format is outside the currently planned native archival format support; no PSD parsing or conversion is authorized.
- **WebP:** Secondary priority. A temporary format-specific restriction (including the coordinator-proposed 50 MP WebP cap) remains an **open security/design decision**, not an approved replacement for the general 200 MP JPEG/PNG requirement.
- **GIF:** No additional support is authorized by this clarification.
- Test JPEG and PNG at approximately 132 MP as well as the already approved upper boundaries. Keep import/export SHA-256 integrity verification and separate the structural-admission path from potentially expensive full-resolution decoding.

This shared clarification must be maintained independently in each edition's ADR. It does not introduce Pro cloud-native dependencies or Pro-only capabilities into Community.
