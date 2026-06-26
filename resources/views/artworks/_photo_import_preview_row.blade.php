@php
    use App\Support\ArtworkPhotoBulkImport\BulkImportManualResolution;

    $status = (string) ($row['status'] ?? '');
    $needsConfirmation = $status === 'needs_confirmation';
    $partialTitleMatch = $status === 'partial_title_match';
    $manuallyResolved = $status === 'manually_resolved';
    $isReady = $status === 'ready';
    $canResolve = BulkImportManualResolution::canResolveRow($row);
    $hasPreview = ! empty($row['absolute_path']);
    $filename = (string) ($row['filename'] ?? '');
    $isSkipped = in_array($status, [
        'ambiguous_match',
        'conflicting_match',
        'unmatched',
        'missing_artwork',
        'missing_photo',
        'duplicate_reference',
        'invalid_row',
        'invalid_file',
    ], true);
    $statusLabel = str_replace('_', ' ', $status);
    $statusClass = $isReady
        ? 'photo-import-status-pill--ready'
        : ($isSkipped ? 'photo-import-status-pill--skipped' : 'photo-import-status-pill--review');
    $cardClass = 'photo-import-card photo-import-row'
        . ($needsConfirmation || $partialTitleMatch || $manuallyResolved ? ' photo-import-card--highlight' : '');
@endphp

<article
    class="{{ $cardClass }}"
    data-row-key="{{ $row['row_key'] }}"
    data-filename="{{ $filename }}"
    data-title-candidate="{{ $row['title_candidate'] ?? '' }}"
    data-sku-candidate="{{ $row['sku_candidate'] ?? '' }}"
    data-status="{{ $status }}"
    data-skipped="{{ $isSkipped ? '1' : '0' }}"
>
    <div class="photo-import-card__import">
        @if ($needsConfirmation)
            <label class="field-inline-label" style="margin:0;">
                <input
                    type="checkbox"
                    class="photo-import-confirm photo-import-confirm-exact"
                    name="confirm_rows[]"
                    value="{{ $row['row_key'] }}"
                    form="photo-import-apply"
                    checked
                    aria-label="Import {{ $filename }}"
                >
                <span class="field-hint">Import</span>
            </label>
        @elseif ($partialTitleMatch)
            <label class="field-inline-label" style="margin:0;">
                <input
                    type="checkbox"
                    class="photo-import-confirm photo-import-confirm-partial"
                    name="confirm_rows[]"
                    value="{{ $row['row_key'] }}"
                    form="photo-import-apply"
                    aria-label="Confirm partial match import for {{ $filename }}"
                >
                <span class="field-hint">Import</span>
            </label>
        @elseif ($manuallyResolved)
            <label class="field-inline-label" style="margin:0;">
                <input
                    type="checkbox"
                    class="photo-import-confirm photo-import-confirm-manual"
                    name="confirm_rows[]"
                    value="{{ $row['row_key'] }}"
                    form="photo-import-apply"
                    checked
                    aria-label="Import manually resolved match for {{ $filename }}"
                >
                <span class="field-hint">Import</span>
            </label>
        @elseif ($isReady)
            <span class="field-hint" title="Imports automatically on apply">Auto</span>
            <span class="visually-hidden">Imports automatically on apply</span>
        @else
            <span class="field-hint" aria-hidden="true">—</span>
        @endif
    </div>

    <div class="photo-import-card__media">
        @if ($hasPreview)
            <img
                class="photo-import-card__thumb photo-import-row-thumb"
                src="{{ route('artworks.photo-bulk-import.preview.thumb', ['token' => $token, 'rowKey' => $row['row_key']]) }}"
                alt="Preview of {{ $filename }}"
                width="120"
                height="90"
                loading="lazy"
                decoding="async"
            >
        @else
            <div class="photo-import-card__thumb-placeholder" role="img" aria-label="No preview for {{ $filename }}">
                No preview
            </div>
        @endif
        <code class="photo-import-card__filename">{{ $filename }}</code>
    </div>

    <div class="photo-import-card__body">
        <span class="photo-import-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>

        @if ($partialTitleMatch && ! empty($row['title_candidate']))
            <div class="field-hint" style="margin-top:0.35rem;">Candidate: {{ $row['title_candidate'] }}</div>
        @endif

        <div style="margin-top:0.35rem;font-weight:600;">
            {{ $row['artwork_title'] ?? ($row['title_candidate'] ?? '—') }}
        </div>

        @if (! empty($row['sku_candidate']))
            <div class="field-hint" style="margin-top:0.2rem;">SKU from filename: <code>{{ $row['sku_candidate'] }}</code></div>
        @endif
        @if ($supportsSkuSearch && ! empty($row['matched_artwork_sku']))
            <div class="field-hint">Artwork SKU: <code>{{ $row['matched_artwork_sku'] }}</code></div>
        @endif
        @if ($supportsSkuSearch && ! empty($row['matched_artwork_inventory_code']) && ($row['match_method'] ?? '') === 'filename_sku')
            <div class="field-hint">Artwork inventory: <code>{{ $row['matched_artwork_inventory_code'] }}</code></div>
        @endif
        @if ($status === 'conflicting_match' && ! empty($row['conflicting_title_artwork_title']))
            <div class="field-hint" style="margin-top:0.35rem;">Title match: {{ $row['conflicting_title_artwork_title'] }}</div>
        @endif

        <dl class="photo-import-card__meta-grid">
            <dt>Source</dt>
            <dd>
                @if (($row['source'] ?? '') === 'mapping_csv')
                    CSV
                    @if (! empty($row['line']))
                        <span class="field-hint">L{{ $row['line'] }}</span>
                    @endif
                @elseif (($row['match_method'] ?? '') === 'partial_title_match')
                    Partial title
                @elseif (($row['match_method'] ?? '') === 'manual_resolution')
                    Manual match
                @elseif (($row['match_method'] ?? '') === 'filename_sku')
                    Filename SKU
                @elseif (($row['match_method'] ?? '') === 'filename_title')
                    Filename
                @else
                    {{ str_replace('_', ' ', $row['match_method'] ?? '—') }}
                @endif
            </dd>
            <dt>Current?</dt>
            <dd>{{ ($row['set_as_current'] ?? false) ? 'Yes' : 'No' }}</dd>
            <dt>Notes</dt>
            <dd>
                {{ $row['message'] ?? '' }}
                @if ($partialTitleMatch && isset($row['match_confidence']))
                    <span class="field-hint">Confidence: {{ number_format((float) $row['match_confidence'] * 100, 0) }}%</span>
                @endif
                @if (($row['has_existing_photos'] ?? false) && in_array($status, ['ready', 'needs_confirmation', 'partial_title_match', 'manually_resolved'], true))
                    Has existing photos.
                @endif
            </dd>
        </dl>
    </div>

    <div class="photo-import-card__actions">
        @if ($canResolve || $manuallyResolved)
            <button
                type="button"
                class="btn photo-import-open-resolve"
                data-row-key="{{ $row['row_key'] }}"
                aria-label="{{ $manuallyResolved ? 'Change match for' : 'Resolve match for' }} {{ $filename }}"
            >
                {{ $manuallyResolved ? 'Change match' : 'Resolve match' }}
            </button>
            @if ($manuallyResolved)
                <button
                    type="button"
                    class="btn photo-import-undo-resolve"
                    data-row-key="{{ $row['row_key'] }}"
                    aria-label="Undo manual match for {{ $filename }}"
                >
                    Undo match
                </button>
            @endif
        @else
            <span class="field-hint" aria-hidden="true">—</span>
        @endif
    </div>
</article>
