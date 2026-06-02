@props(['artwork'])

@if ($artwork->latestPhoto?->existsOnDisk())
    <a href="{{ route('artworks.show', $artwork) }}">
        <img src="{{ $artwork->latestPhoto->publicUrl() }}" alt="" class="artwork-thumb">
    </a>
@else
    <span class="artwork-thumb-placeholder">No photo</span>
@endif
