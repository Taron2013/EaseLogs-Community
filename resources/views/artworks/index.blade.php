@extends('layouts.app')

@section('title', 'Artworks — ' . config('easelogs.display_name'))

@section('content')
    <p style="margin-top:0;">
        <a href="{{ route('artworks.create') }}" class="btn btn-primary">New artwork</a>
    </p>

    @php
        $indexQuery = array_merge($filters->queryParams(), $sort->queryParams());
    @endphp

    <section class="artwork-filters" aria-label="Artwork filters">
        <p class="artwork-filters-label">Filter</p>
        <div class="artwork-filters-quick">
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('all'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('all') ? ' is-active' : '' }}">All</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('in_progress'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('in_progress') ? ' is-active' : '' }}">In progress</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('completed'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('completed') ? ' is-active' : '' }}">Completed</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('untitled'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('untitled') ? ' is-active' : '' }}">Untitled</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('missing_photo'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('missing_photo') ? ' is-active' : '' }}">Missing photo</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('missing_dimensions'), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('missing_dimensions') ? ' is-active' : '' }}">Missing dimensions</a>
        </div>

        <form method="GET" action="{{ route('artworks.index') }}" class="artwork-filters-fields">
            @foreach ($sort->queryParams() as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            @if ($filters->quickFilter() !== 'all')
                <input type="hidden" name="filter" value="{{ $filters->quickFilter() }}">
            @endif

            <label for="filter_artwork_type">Artwork type</label>
            <select name="artwork_type" id="filter_artwork_type">
                <option value="">Any</option>
                @foreach ($artworkTypes as $type)
                    <option value="{{ $type }}" @selected($filters->artworkType() === $type)>{{ $type }}</option>
                @endforeach
            </select>

            <label for="filter_medium">Medium</label>
            <select name="medium" id="filter_medium">
                <option value="">Any</option>
                @foreach ($mediums as $mediumOption)
                    <option value="{{ $mediumOption }}" @selected($filters->medium() === $mediumOption)>{{ $mediumOption }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn">Apply</button>
        </form>

        @if ($filters->hasActiveFilters())
            <p class="artwork-filters-clear">
                <a href="{{ route('artworks.index', $sort->queryParams()) }}">Clear filters</a>
            </p>
        @endif
    </section>

    @if ($artworks->isEmpty())
        @if ($filters->hasActiveFilters())
            <p>No artworks match these filters. <a href="{{ route('artworks.index', $sort->queryParams()) }}">Clear filters</a>.</p>
        @else
            <p>No artworks yet. <a href="{{ route('artworks.create') }}">Create your first artwork</a>.</p>
        @endif
    @else
        <div class="artwork-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('title', $filters->queryParams())) }}" class="sort-link">
                            Title <span class="sort-indicator">{{ $sort->indicator('title') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('artwork_type', $filters->queryParams())) }}" class="sort-link">
                            Artwork type <span class="sort-indicator">{{ $sort->indicator('artwork_type') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('medium', $filters->queryParams())) }}" class="sort-link">
                            Medium <span class="sort-indicator">{{ $sort->indicator('medium') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('dimensions', $filters->queryParams())) }}" class="sort-link">
                            Dimensions <span class="sort-indicator">{{ $sort->indicator('dimensions') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('start_date', $filters->queryParams())) }}" class="sort-link">
                            Start date <span class="sort-indicator">{{ $sort->indicator('start_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('completed_date', $filters->queryParams())) }}" class="sort-link">
                            Completed date <span class="sort-indicator">{{ $sort->indicator('completed_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('updated_at', $filters->queryParams())) }}" class="sort-link">
                            Updated <span class="sort-indicator">{{ $sort->indicator('updated_at') }}</span>
                        </a>
                    </th>
                    <th>Actions</th>
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
                        <td class="artwork-actions">
                            <div class="artwork-actions-stack">
                                <a href="{{ route('artworks.show', $artwork) }}" class="artwork-action-link">View</a>
                                <a href="{{ route('artworks.edit', $artwork) }}" class="artwork-action-link">Edit</a>
                                <form method="POST"
                                      action="{{ route('artworks.destroy', $artwork) }}"
                                      class="artwork-action-delete-form"
                                      onsubmit="return confirm('Delete this artwork? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="artwork-action-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="pagination">
            {{ $artworks->links() }}
        </div>
    @endif
@endsection
