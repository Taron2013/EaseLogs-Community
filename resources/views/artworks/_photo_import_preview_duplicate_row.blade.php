@php
    $filename = (string) ($row['filename'] ?? '');
    $hasPreview = ! empty($row['absolute_path']);
@endphp

<article
    class="photo-import-card photo-import-card--duplicate photo-import-duplicate-row"
    data-row-key="{{ $row['row_key'] }}"
    data-filename="{{ $filename }}"
    data-status="duplicate_existing_photo"
    data-skipped="1"
>
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
        <span class="photo-import-status-pill photo-import-status-pill--duplicate">duplicate existing photo</span>
        <div style="margin-top:0.35rem;font-weight:600;">
            {{ $row['artwork_title'] ?? '—' }}
        </div>
        @if ($supportsSkuSearch && ! empty($row['matched_artwork_sku']))
            <div class="field-hint">Artwork SKU: <code>{{ $row['matched_artwork_sku'] }}</code></div>
        @endif
        @if ($supportsSkuSearch && ! empty($row['matched_artwork_inventory_code']))
            <div class="field-hint">Artwork inventory: <code>{{ $row['matched_artwork_inventory_code'] }}</code></div>
        @endif
        <p class="field-hint" style="margin:0.5rem 0 0;">
            {{ $row['message'] ?? 'Exact duplicate of existing photo.' }} Will be skipped on apply.
        </p>
    </div>
</article>
