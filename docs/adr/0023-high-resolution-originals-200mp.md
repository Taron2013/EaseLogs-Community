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

**Proposal only: no implementation, deployments, merges, or changes to canonical main are authorized.**
