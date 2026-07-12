@props(['artwork', 'compact' => false])

@php
    $tagNames = $artwork->tagNames();
@endphp

@if ($tagNames !== [])
    @if ($compact)
        <p class="artwork-tags-compact">
            @foreach ($tagNames as $tagName)
                <span class="tag-chip tag-chip-readonly tag-chip-general">{{ $tagName }}</span>
            @endforeach
        </p>
    @else
        <section class="artwork-tags-display" aria-label="Tags">
            <div class="artwork-tags-group">
                <h3 class="artwork-tags-group-label">Tags</h3>
                <p class="artwork-tags-group-list">
                    @foreach ($tagNames as $tagName)
                        <span class="tag-chip tag-chip-readonly tag-chip-general">{{ $tagName }}</span>
                    @endforeach
                </p>
            </div>
        </section>
    @endif
@endif
