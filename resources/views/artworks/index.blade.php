@extends('layouts.app')

@section('title', 'Artworks — ' . config('easelogs.display_name'))

@section('content')
    <p style="margin-top:0;">
        <a href="{{ route('artworks.create') }}" class="btn btn-primary">New artwork</a>
    </p>

    @php
        $listQuery = array_merge($filters->queryParams(), $search->queryParams());
        $indexQuery = array_merge($listQuery, $sort->queryParams());
        $hasActiveViewModifiers = $filters->hasActiveFilters()
            || $search->hasTerm()
            || ! $sort->usesDefaultListing();
    @endphp

    <section class="artwork-filters" aria-label="Artwork filters">
        <p class="artwork-filters-label">Filter</p>
        <div class="artwork-filters-quick">
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('all'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('all') ? ' is-active' : '' }}">All</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('in_progress'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('in_progress') ? ' is-active' : '' }}">In progress</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('completed'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('completed') ? ' is-active' : '' }}">Completed</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('untitled'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('untitled') ? ' is-active' : '' }}">Untitled</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('missing_photo'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('missing_photo') ? ' is-active' : '' }}">Missing photo</a>
            <a href="{{ route('artworks.index', array_merge($filters->queryParamsForQuickFilter('missing_dimensions'), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive('missing_dimensions') ? ' is-active' : '' }}">Missing dimensions</a>
        </div>

        <form method="GET" action="{{ route('artworks.index') }}" class="artwork-filters-fields">
            @foreach ($sort->queryParams() as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            @if ($filters->quickFilter() !== 'all')
                <input type="hidden" name="filter" value="{{ $filters->quickFilter() }}">
            @endif

            <div class="filter-field">
                <label for="filter_q">Search</label>
                <input type="search" name="q" id="filter_q" value="{{ $search->term() ?? '' }}" placeholder="Title or notes" autocomplete="off">
            </div>

            <div class="filter-field">
                <label for="filter_artwork_type">Artwork type</label>
                <select name="artwork_type" id="filter_artwork_type">
                    <option value="">Any</option>
                    @foreach ($artworkTypes as $type)
                        <option value="{{ $type }}" @selected($filters->artworkType() === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="filter_medium">Medium</label>
                <select name="medium" id="filter_medium">
                    <option value="">Any</option>
                    @foreach ($mediums as $mediumOption)
                        <option value="{{ $mediumOption }}" @selected($filters->medium() === $mediumOption)>{{ $mediumOption }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn filter-apply-btn">Apply</button>
        </form>

        @if ($hasActiveViewModifiers)
            <p class="artwork-filters-clear">
                @if ($filters->hasActiveFilters())
                    <a href="{{ route('artworks.index', array_merge($search->queryParams(), $sort->queryParams())) }}">Clear filters</a>
                    <span class="artwork-filters-clear-sep"> · </span>
                @endif
                <a href="{{ route('artworks.index') }}">Reset view</a>
            </p>
        @endif
    </section>

    @include('artworks._index_sort_bar', ['filters' => $filters, 'search' => $search, 'sort' => $sort])

    @if ($errors->any())
        <div class="flash flash-error" role="alert">
            <ul class="errors" style="margin:0;padding-left:1.25rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($artworks->isEmpty())
        @if ($filters->hasActiveFilters() || $search->hasTerm())
            <p>No artworks match this view. <a href="{{ route('artworks.index', array_merge($search->queryParams(), $sort->queryParams())) }}">Clear filters</a>@if ($search->hasTerm() || ! $sort->usesDefaultListing()) or <a href="{{ route('artworks.index') }}">reset view</a>@endif.</p>
        @else
            <p>No artworks yet. <a href="{{ route('artworks.create') }}">Create your first artwork</a>.</p>
        @endif
    @else
        <form id="bulk-delete-form"
              method="POST"
              action="{{ route('artworks.bulk-delete') }}"
              class="bulk-delete-form">
            @csrf
            @method('DELETE')
            @foreach ($indexQuery as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
        </form>

        <div id="bulk-actions" class="bulk-actions" hidden>
            <p class="bulk-actions-count"><span id="bulk-selected-count">0</span> selected</p>
            <button type="submit" form="bulk-delete-form" class="btn btn-danger" id="bulk-delete-submit">Delete Selected</button>
            <button type="button" class="btn" id="bulk-clear-selection">Clear Selection</button>
        </div>

        <div class="artwork-mobile-list">
            <div class="artwork-mobile-list-toolbar">
                <label class="artwork-mobile-select-all">
                    <input type="checkbox" id="artwork-select-all-mobile" aria-label="Select all artworks on this page">
                    <span>Select all on page</span>
                </label>
            </div>
            @foreach ($artworks as $artwork)
                @include('artworks._index_mobile_card', ['artwork' => $artwork])
            @endforeach
        </div>

        <div class="artwork-table-wrap artwork-index-table">
        <table>
            <thead>
                <tr>
                    <th class="artwork-select-col">
                        <span class="artwork-select-label">Select</span>
                        <input type="checkbox" id="artwork-select-all" aria-label="Select all artworks on this page">
                    </th>
                    <th>Photo</th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('title', $listQuery)) }}" class="sort-link">
                            Title <span class="sort-indicator">{{ $sort->indicator('title') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('artwork_type', $listQuery)) }}" class="sort-link">
                            Artwork type <span class="sort-indicator">{{ $sort->indicator('artwork_type') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('medium', $listQuery)) }}" class="sort-link">
                            Medium <span class="sort-indicator">{{ $sort->indicator('medium') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('dimensions', $listQuery)) }}" class="sort-link">
                            Dimensions <span class="sort-indicator">{{ $sort->indicator('dimensions') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('start_date', $listQuery)) }}" class="sort-link">
                            Start date <span class="sort-indicator">{{ $sort->indicator('start_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('completed_date', $listQuery)) }}" class="sort-link">
                            Completed date <span class="sort-indicator">{{ $sort->indicator('completed_date') }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('artworks.index', $sort->queryParamsFor('updated_at', $listQuery)) }}" class="sort-link">
                            Updated <span class="sort-indicator">{{ $sort->indicator('updated_at') }}</span>
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($artworks as $artwork)
                    <tr>
                        <td class="artwork-select-col">
                            <input type="checkbox"
                                   class="artwork-row-select"
                                   name="ids[]"
                                   value="{{ $artwork->id }}"
                                   form="bulk-delete-form"
                                   aria-label="Select {{ $artwork->displayTitle() }}">
                        </td>
                        <td>
                            @include('artworks._index_artwork_photo', ['artwork' => $artwork])
                        </td>
                        <td><a href="{{ route('artworks.show', $artwork) }}">{{ $artwork->displayTitle() }}</a></td>
                        <td>{{ $artwork->artwork_type ?? '—' }}</td>
                        <td>{{ $artwork->medium ?? '—' }}</td>
                        <td>{{ $artwork->formattedDimensions() ?? '—' }}</td>
                        <td>{{ $artwork->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $artwork->completed_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $artwork->updated_at?->format('Y-m-d') }}</td>
                        <td class="artwork-actions">
                            @include('artworks._index_artwork_actions', ['artwork' => $artwork])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="pagination">
            {{ $artworks->links('artworks.pagination') }}
        </div>

        <script>
            (function () {
                const selectAllDesktop = document.getElementById('artwork-select-all');
                const selectAllMobile = document.getElementById('artwork-select-all-mobile');
                const bulkBar = document.getElementById('bulk-actions');
                const bulkCount = document.getElementById('bulk-selected-count');
                const bulkForm = document.getElementById('bulk-delete-form');
                const clearBtn = document.getElementById('bulk-clear-selection');

                function visibleRowChecks() {
                    return Array.from(document.querySelectorAll('.artwork-row-select')).filter(function (checkbox) {
                        return checkbox.offsetParent !== null;
                    });
                }

                function selectedCount() {
                    return visibleRowChecks().filter(function (checkbox) {
                        return checkbox.checked;
                    }).length;
                }

                function syncSelectAllControls() {
                    const checks = visibleRowChecks();
                    const count = selectedCount();
                    const allSelected = checks.length > 0 && count === checks.length;

                    [selectAllDesktop, selectAllMobile].forEach(function (control) {
                        if (!control) {
                            return;
                        }

                        control.checked = allSelected;
                        control.indeterminate = count > 0 && !allSelected;
                    });
                }

                function updateBulkBar() {
                    const count = selectedCount();
                    bulkBar.hidden = count === 0;
                    bulkCount.textContent = String(count);
                    syncSelectAllControls();
                }

                function setSelectAll(checked) {
                    visibleRowChecks().forEach(function (checkbox) {
                        checkbox.checked = checked;
                    });
                    updateBulkBar();
                }

                selectAllDesktop?.addEventListener('change', function () {
                    setSelectAll(selectAllDesktop.checked);
                });

                selectAllMobile?.addEventListener('change', function () {
                    setSelectAll(selectAllMobile.checked);
                });

                document.querySelectorAll('.artwork-row-select').forEach(function (checkbox) {
                    checkbox.addEventListener('change', updateBulkBar);
                });

                clearBtn?.addEventListener('click', function () {
                    setSelectAll(false);
                });

                bulkForm?.addEventListener('submit', function (event) {
                    const count = selectedCount();
                    if (count === 0) {
                        event.preventDefault();
                        return;
                    }

                    const noun = count === 1 ? 'artwork' : 'artworks';
                    const message = 'Delete ' + count + ' selected ' + noun + '?\nThis action cannot be undone.';

                    if (!confirm(message)) {
                        event.preventDefault();
                    }
                });
            })();
        </script>
    @endif
@endsection
