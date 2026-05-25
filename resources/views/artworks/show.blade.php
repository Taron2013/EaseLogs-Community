@extends('layouts.app')

@section('title', ($artwork->title ?: 'Untitled') . ' — ' . config('app.name'))

@section('content')
    <h2 style="margin-top:0;">{{ $artwork->title ?: 'Untitled' }}</h2>

    <p>
        <a href="{{ route('artworks.edit', $artwork) }}" class="btn">Edit</a>
        <a href="{{ route('artworks.index') }}" class="btn">Back to list</a>
    </p>

    <dl class="detail-grid">
        <dt>Inventory code</dt>
        <dd>{{ $artwork->inventory_code }}</dd>

        <dt>SKU</dt>
        <dd>{{ $artwork->sku ?? '—' }}</dd>

        <dt>Description</dt>
        <dd>{{ $artwork->description ?: '—' }}</dd>

        <dt>Started date</dt>
        <dd>
            @if ($artwork->started_date)
                {{ $artwork->started_date->format('Y-m-d') }}
                @if ($artwork->started_date_is_estimated) (estimated) @endif
            @else
                —
            @endif
        </dd>

        <dt>Finished date</dt>
        <dd>
            @if ($artwork->finished_date)
                {{ $artwork->finished_date->format('Y-m-d') }}
                @if ($artwork->finished_date_is_estimated) (estimated) @endif
            @else
                —
            @endif
        </dd>

        <dt>Medium</dt>
        <dd>{{ $artwork->medium ?? '—' }}</dd>

        <dt>Surface</dt>
        <dd>{{ $artwork->surface ?? '—' }}</dd>

        <dt>Dimensions</dt>
        <dd>
            @if ($artwork->width || $artwork->height || $artwork->depth)
                {{ $artwork->width ?? '?' }} × {{ $artwork->height ?? '?' }} × {{ $artwork->depth ?? '?' }} {{ $artwork->dimension_unit }}
            @else
                —
            @endif
        </dd>

        <dt>Category</dt>
        <dd>{{ $artwork->category ?? '—' }}</dd>

        <dt>Style</dt>
        <dd>{{ $artwork->style ?? '—' }}</dd>

        <dt>Subject</dt>
        <dd>{{ $artwork->subject ?? '—' }}</dd>

        <dt>Status</dt>
        <dd>{{ str_replace('_', ' ', $artwork->status) }}</dd>

        <dt>Condition</dt>
        <dd>{{ str_replace('_', ' ', $artwork->condition) }}</dd>

        <dt>Location</dt>
        <dd>{{ $artwork->location ?? '—' }}</dd>

        <dt>Storage area</dt>
        <dd>{{ $artwork->storage_area ?? '—' }}</dd>

        <dt>Estimated value</dt>
        <dd>
            @if ($artwork->estimated_value !== null)
                {{ number_format((float) $artwork->estimated_value, 2) }} {{ $artwork->currency }}
            @else
                —
            @endif
        </dd>

        <dt>Sale price</dt>
        <dd>
            @if ($artwork->sale_price !== null)
                {{ number_format((float) $artwork->sale_price, 2) }} {{ $artwork->currency }}
            @else
                —
            @endif
        </dd>

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
