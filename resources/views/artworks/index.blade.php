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
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('title')) }}" class="sort-link">
                            Title <span class="sort-indicator">{{ $sort->indicator('title') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('artwork_type')) }}" class="sort-link">
                            Artwork type <span class="sort-indicator">{{ $sort->indicator('artwork_type') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('medium')) }}" class="sort-link">
                            Medium <span class="sort-indicator">{{ $sort->indicator('medium') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('dimensions')) }}" class="sort-link">
                            Dimensions <span class="sort-indicator">{{ $sort->indicator('dimensions') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('start_date')) }}" class="sort-link">
                            Start date <span class="sort-indicator">{{ $sort->indicator('start_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('completed_date')) }}" class="sort-link">
                            Completed date <span class="sort-indicator">{{ $sort->indicator('completed_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('updated_at')) }}" class="sort-link">
                            Updated <span class="sort-indicator">{{ $sort->indicator('updated_at') }}</span>
                        </a>
                    </th>
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
                        <td><a href="{{ route('artworks.show', $artwork) }}">{{ $artwork->displayTitle() }}</a></td>
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
