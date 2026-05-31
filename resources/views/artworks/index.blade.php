@extends('layouts.app')

@section('title', 'Artworks — ' . config('easelogs.display_name'))

@section('content')
    <p style="margin-top:0;">
        <a href="{{ route('artworks.create') }}" class="btn btn-primary">New artwork</a>
    </p>

    @if ($artworks->isEmpty())
        <p>No artworks yet. <a href="{{ route('artworks.create') }}">Create your first artwork</a>.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Title</th>
                    <th>Artwork type</th>
                    <th>Medium</th>
                    <th>Dimensions</th>
                    <th>Start date</th>
                    <th>Completed date</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($artworks as $artwork)
                    <tr>
                        <td>
                            @if ($artwork->latestPhoto?->existsOnDisk())
                                <a href="{{ route('artworks.show', $artwork) }}">
                                    <img src="{{ $artwork->latestPhoto->publicUrl() }}" alt="" class="artwork-thumb">
                                </a>
                            @else
                                <span class="artwork-thumb-placeholder">No photo</span>
                            @endif
                        </td>
                        <td><a href="{{ route('artworks.show', $artwork) }}">{{ $artwork->title ?: 'Untitled' }}</a></td>
                        <td>{{ $artwork->artwork_type ?? '—' }}</td>
                        <td>{{ $artwork->medium ?? '—' }}</td>
                        <td>{{ $artwork->formattedDimensions() ?? '—' }}</td>
                        <td>{{ $artwork->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $artwork->completed_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $artwork->updated_at?->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('artworks.edit', $artwork) }}">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">
            {{ $artworks->links() }}
        </div>
    @endif
@endsection
