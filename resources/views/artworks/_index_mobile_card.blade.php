@props(['artwork'])

<article class="artwork-mobile-card">
    <div class="artwork-mobile-card-header">
        @unless ($easelogsDemo['blocks_deletes'] ?? false)
            <input type="checkbox"
                   class="artwork-row-select"
                   name="ids[]"
                   value="{{ $artwork->id }}"
                   form="bulk-delete-form"
                   aria-label="Select {{ $artwork->displayTitle() }}">
        @endunless
        <div class="artwork-mobile-card-photo">
            @include('artworks._index_artwork_photo', ['artwork' => $artwork])
        </div>
        <h3 class="artwork-mobile-card-title">
            <a href="{{ route('artworks.show', $artwork) }}">{{ $artwork->displayTitle() }}</a>
        </h3>
    </div>

    <dl class="artwork-mobile-card-meta">
        <div>
            <dt>Artwork type</dt>
            <dd>{{ $artwork->artwork_type ?? '—' }}</dd>
        </div>
        <div>
            <dt>Medium</dt>
            <dd>{{ $artwork->medium ?? '—' }}</dd>
        </div>
        <div>
            <dt>Dimensions</dt>
            <dd>{{ $artwork->formattedDimensions() ?? '—' }}</dd>
        </div>
        <div>
            <dt>Start date</dt>
            <dd>{{ $artwork->start_date?->format('Y-m-d') ?? '—' }}</dd>
        </div>
        <div>
            <dt>Completed date</dt>
            <dd>{{ $artwork->completed_date?->format('Y-m-d') ?? '—' }}</dd>
        </div>
        <div>
            <dt>Updated</dt>
            <dd>{{ $artwork->updated_at?->format('Y-m-d') }}</dd>
        </div>
    </dl>

    <div class="artwork-mobile-card-actions">
        @include('artworks._index_artwork_actions', ['artwork' => $artwork])
    </div>
</article>
