@extends('layouts.app')

@section('title', $artwork->displayTitle() . ' — ' . config('easelogs.display_name'))

@section('content')
    <h2 style="margin-top:0;">{{ $artwork->displayTitle() }}</h2>

    @if ($artwork->latestPhoto?->existsOnDisk())
        <figure style="margin:0 0 1.25rem;">
            <img src="{{ $artwork->latestPhoto->publicUrl() }}" alt="Photo of {{ $artwork->displayTitle() }}" class="artwork-photo">
        </figure>
    @endif

    <p>
        <a href="{{ route('artworks.edit', $artwork) }}" class="btn">Edit</a>
        <a href="{{ route('artworks.index') }}" class="btn">Back to list</a>
    </p>

    <dl class="detail-grid">
        <dt>Start date</dt>
        <dd>{{ $artwork->start_date?->format('Y-m-d') ?? '—' }}</dd>

        <dt>Completed date</dt>
        <dd>{{ $artwork->completed_date?->format('Y-m-d') ?? '—' }}</dd>

        <dt>Artwork type</dt>
        <dd>{{ $artwork->artwork_type ?? '—' }}</dd>

        <dt>Medium</dt>
        <dd>{{ $artwork->medium ?? '—' }}</dd>

        <dt>Dimensions</dt>
        <dd>{{ $artwork->formattedDimensions() ?? '—' }}</dd>

        <dt>Notes</dt>
        <dd>{{ $artwork->notes ?: '—' }}</dd>

        <dt>Created</dt>
        <dd>{{ $artwork->created_at?->format('Y-m-d H:i') }}</dd>

        <dt>Updated</dt>
        <dd>{{ $artwork->updated_at?->format('Y-m-d H:i') }}</dd>
    </dl>

    <form method="POST" action="{{ route('artworks.destroy', $artwork) }}" style="margin-top:1.5rem;"
        onsubmit="return confirm('Delete this artwork? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Delete artwork</button>
    </form>
@endsection
