@php
    $summary = $preview['summary'];
    $duplicateRows = [];
    $mainRows = [];

    foreach ($preview['rows'] as $row) {
        if (($row['status'] ?? '') === 'duplicate_existing_photo') {
            $duplicateRows[] = $row;
        } else {
            $mainRows[] = $row;
        }
    }

    $duplicateCount = count($duplicateRows);
    $readyCount = (int) ($summary['photos_ready_to_import'] ?? 0);
    $exactReview = (int) ($summary['needs_confirmation'] ?? 0);
    $partialReview = (int) ($summary['partial_title_match'] ?? 0);
    $manualResolved = (int) ($summary['manually_resolved'] ?? 0);
    $needsReview = (int) ($summary['needs_review'] ?? 0);
    $defaultSelected = $readyCount + $exactReview + $manualResolved;
    $supportsSkuSearch = $supportsSkuSearch ?? false;
    $hasConflictingRows = collect($preview['rows'])->contains(
        fn (array $row): bool => ($row['status'] ?? '') === 'conflicting_match',
    );
@endphp

<style>
    .photo-import-preview-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }
    .photo-import-duplicates {
        margin-bottom: 1rem;
        border: 1px solid #e8dfd0;
        border-radius: 8px;
        background: #fffaf2;
    }
    .photo-import-duplicates > summary {
        cursor: pointer;
        padding: 0.85rem 1rem;
        font-weight: 600;
        list-style: none;
    }
    .photo-import-duplicates > summary::-webkit-details-marker { display: none; }
    .photo-import-duplicates > summary::before {
        content: '▸ ';
        display: inline-block;
        width: 1rem;
    }
    .photo-import-duplicates[open] > summary::before { content: '▾ '; }
    .photo-import-duplicates__body {
        padding: 0 1rem 1rem;
        display: grid;
        gap: 0.65rem;
    }
    .photo-import-review-list {
        display: grid;
        gap: 0.75rem;
    }
    .photo-import-card {
        display: grid;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        background: #fff;
        border: 1px solid #ececeb;
        border-radius: 8px;
        align-items: start;
    }
    .photo-import-card--highlight { background: #faf8ef; }
    .photo-import-card--duplicate { background: #fafaf8; border-style: dashed; }
    .photo-import-card__import { min-width: 2.5rem; }
    .photo-import-card__media {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-width: 0;
    }
    .photo-import-card__thumb,
    .photo-import-card__thumb-placeholder {
        width: 100%;
        max-width: 7.5rem;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e4e4e2;
        background: #f3f3f1;
    }
    .photo-import-card__thumb-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #777;
        font-size: 0.75rem;
    }
    .photo-import-card__filename {
        font-size: 0.75rem;
        word-break: break-word;
        overflow-wrap: anywhere;
        line-height: 1.35;
        max-width: 7.5rem;
    }
    .photo-import-card__body { min-width: 0; }
    .photo-import-card__meta-grid {
        display: grid;
        gap: 0.35rem 0.75rem;
        margin-top: 0.35rem;
        font-size: 0.875rem;
    }
    .photo-import-card__meta-grid dt {
        font-weight: 500;
        color: #555;
        margin: 0;
    }
    .photo-import-card__meta-grid dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .photo-import-status-pill {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid #ddd;
        background: #f5f5f3;
        color: #333;
        text-transform: capitalize;
    }
    .photo-import-status-pill--ready { border-color: #a5d6a7; background: #e8f5e9; }
    .photo-import-status-pill--review { border-color: #ffe082; background: #fff8e1; }
    .photo-import-status-pill--skipped { border-color: #d0d0ce; background: #f3f3f1; color: #555; }
    .photo-import-status-pill--duplicate { border-color: #e0d2b8; background: #fff8e8; color: #5d4e37; }
    .photo-import-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: flex-start;
    }
    .photo-import-card__actions .btn { white-space: normal; text-align: center; }
    .photo-import-apply-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }
    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    @media (min-width: 768px) {
        .photo-import-card {
            grid-template-columns: auto 7.5rem minmax(0, 1fr) minmax(8rem, 12rem);
            grid-template-areas: 'import media body actions';
        }
        .photo-import-card__import { grid-area: import; }
        .photo-import-card__media { grid-area: media; }
        .photo-import-card__body { grid-area: body; }
        .photo-import-card__actions { grid-area: actions; justify-content: flex-end; }
        .photo-import-card__meta-grid {
            grid-template-columns: 6rem minmax(0, 1fr);
        }
        .photo-import-duplicate-row {
            grid-template-columns: 7.5rem minmax(0, 1fr);
            grid-template-areas: 'media body';
        }
    }
    @media (max-width: 767px) {
        .photo-import-card__actions .btn { width: 100%; }
        .photo-import-apply-actions .btn { width: 100%; text-align: center; }
        .photo-import-card__filename { max-width: none; }
    }
</style>

<section class="form-section" id="photo-import-summary">
    <h2 style="margin-top:0;font-size:1rem;">Summary</h2>
    <ul style="margin:0;display:grid;gap:0.35rem;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));list-style:none;padding:0;">
        <li><strong>Archive photos:</strong> <span id="count-archive">{{ $summary['archive_photos'] ?? 0 }}</span></li>
        <li><strong>Selected to import:</strong> <span id="count-selected">{{ $defaultSelected }}</span></li>
        <li><strong>Needs review:</strong> <span id="count-needs-review">{{ $needsReview }}</span></li>
        @if ($partialReview > 0)
            <li><strong>Partial title matches:</strong> <span id="count-partial">{{ $partialReview }}</span></li>
        @endif
        @if ($duplicateCount > 0)
            <li><strong>Exact duplicates:</strong> <span id="count-duplicates">{{ $duplicateCount }}</span></li>
        @endif
        <li><strong>Ambiguous:</strong> <span id="count-ambiguous">{{ $summary['ambiguous_matches'] ?? 0 }}</span></li>
        <li><strong>Unmatched:</strong> <span id="count-unmatched">{{ $summary['unmatched_photos'] ?? 0 }}</span></li>
    </ul>
    @if (($summary['has_existing_photos'] ?? 0) > 0)
        <p class="field-hint" style="margin:0.75rem 0 0;">
            {{ $summary['has_existing_photos'] }} matched artwork(s) already have photos (import will add more).
        </p>
    @endif
</section>

<section class="form-section" style="padding:0.85rem 1rem;">
    <div class="photo-import-preview-toolbar">
        <label class="field-inline-label" style="margin:0;" for="photo-import-filter">
            Show
            <select id="photo-import-filter" style="margin-left:0.35rem;">
                <option value="all">All actionable rows</option>
                <option value="needs_confirmation">Exact matches (needs review)</option>
                <option value="partial_title_match">Partial title matches</option>
                <option value="manually_resolved">Manually resolved</option>
                <option value="ready">Ready to import</option>
                <option value="ambiguous_match">Ambiguous</option>
                @if ($hasConflictingRows)
                    <option value="conflicting_match">Conflicting</option>
                @endif
                <option value="duplicate_existing_photo">Exact duplicates only</option>
                <option value="unmatched">Unmatched</option>
                <option value="skipped">Will be skipped</option>
            </select>
        </label>
        <button type="button" class="btn" id="photo-import-check-all">Check all exact title matches</button>
        <button type="button" class="btn" id="photo-import-check-visible">Check visible matches</button>
        <button type="button" class="btn" id="photo-import-clear-all">Clear all</button>
    </div>
</section>

@if ($duplicateCount > 0)
    <details class="photo-import-duplicates" id="photo-import-duplicates">
        <summary id="photo-import-duplicates-summary">
            Exact duplicates detected: {{ $duplicateCount }} (skipped by default)
        </summary>
        <div class="photo-import-duplicates__body" id="photo-import-duplicates-list">
            @foreach ($duplicateRows as $row)
                @include('artworks._photo_import_preview_duplicate_row', [
                    'row' => $row,
                    'token' => $token,
                    'supportsSkuSearch' => $supportsSkuSearch,
                ])
            @endforeach
        </div>
    </details>
@endif

<div class="photo-import-review-list" id="photo-import-main-list" aria-label="Photo import review rows">
    @foreach ($mainRows as $row)
        @include('artworks._photo_import_preview_row', [
            'row' => $row,
            'token' => $token,
            'supportsSkuSearch' => $supportsSkuSearch,
        ])
    @endforeach
</div>

<form id="photo-import-apply" method="POST" action="{{ route('artworks.photo-bulk-import.apply') }}" class="photo-import-apply-actions">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <button
        type="submit"
        class="btn btn-primary"
        id="photo-import-apply-btn"
        @disabled($defaultSelected === 0)
    >Apply import (<span id="count-selected-apply">{{ $defaultSelected }}</span>)</button>
    <a href="{{ route('artworks.photo-bulk-import.discard', $token) }}" class="btn">Cancel</a>
</form>

<script>
    (function () {
        const readyCount = {{ $readyCount }};
        const mainList = document.getElementById('photo-import-main-list');
        const duplicateDetails = document.getElementById('photo-import-duplicates');
        const rows = mainList ? Array.from(mainList.querySelectorAll('.photo-import-row')) : [];
        const confirmBoxes = Array.from(document.querySelectorAll('.photo-import-confirm'));
        const filter = document.getElementById('photo-import-filter');
        const selectedEl = document.getElementById('count-selected');
        const selectedApplyEl = document.getElementById('count-selected-apply');
        const applyBtn = document.getElementById('photo-import-apply-btn');

        function selectedCount() {
            const checked = confirmBoxes.filter((box) => box.checked).length;
            return readyCount + checked;
        }

        function updateSelectedCount() {
            const count = selectedCount();
            selectedEl.textContent = String(count);
            selectedApplyEl.textContent = String(count);
            applyBtn.disabled = count === 0;
        }

        function applyFilter() {
            const value = filter.value;

            if (value === 'duplicate_existing_photo') {
                if (mainList) {
                    mainList.style.display = 'none';
                }
                if (duplicateDetails) {
                    duplicateDetails.style.display = '';
                    duplicateDetails.open = true;
                }
                return;
            }

            if (mainList) {
                mainList.style.display = '';
            }
            if (duplicateDetails) {
                duplicateDetails.style.display = '';
                if (value !== 'all') {
                    duplicateDetails.open = false;
                }
            }

            rows.forEach((row) => {
                let show = true;
                if (value === 'all') {
                    show = true;
                } else if (value === 'skipped') {
                    show = row.dataset.skipped === '1';
                } else {
                    show = row.dataset.status === value;
                }
                row.style.display = show ? '' : 'none';
            });
        }

        confirmBoxes.forEach((box) => box.addEventListener('change', updateSelectedCount));

        document.getElementById('photo-import-check-all').addEventListener('click', () => {
            document.querySelectorAll('.photo-import-confirm-exact').forEach((box) => {
                box.checked = true;
            });
            updateSelectedCount();
        });

        document.getElementById('photo-import-clear-all').addEventListener('click', () => {
            confirmBoxes.forEach((box) => {
                box.checked = false;
            });
            updateSelectedCount();
        });

        document.getElementById('photo-import-check-visible').addEventListener('click', () => {
            applyFilter();
            rows.forEach((row) => {
                if (row.style.display === 'none') {
                    return;
                }
                const box = row.querySelector('.photo-import-confirm');
                if (box) {
                    box.checked = true;
                }
            });
            updateSelectedCount();
        });

        filter.addEventListener('change', applyFilter);
        updateSelectedCount();
    })();
</script>
