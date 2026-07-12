@php
    $listingRoute = $listingRoute ?? 'artworks.index';
    $searchPlaceholder = $searchPlaceholder ?? 'Title, notes';
    $tagOptions = $tagOptions ?? collect();
    $selectedTag = $filters->tag() ?? '';
    $hasActiveViewModifiers = $filters->hasActiveFilters()
        || $search->hasTerm()
        || ! $sort->usesDefaultListing();
    $hasTagFilter = $tagOptions->isNotEmpty();
@endphp

<section class="artwork-filters" aria-label="Artwork filters">
    <p class="artwork-filters-label">Filter</p>
    <div class="artwork-filters-quick">
        @foreach ([
            'all' => 'All',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'untitled' => 'Untitled',
            'missing_photo' => 'Missing photo',
            'missing_dimensions' => 'Missing dimensions',
            'has_dimensions' => 'Has dimensions',
        ] as $quick => $label)
            <a href="{{ route($listingRoute, array_merge($filters->queryParamsForQuickFilter($quick), $search->queryParams(), $sort->queryParams())) }}"
               class="filter-pill{{ $filters->isQuickFilterActive($quick) ? ' is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route($listingRoute) }}" class="artwork-filters-fields">
        @include('artworks._query_hidden_fields', ['params' => $sort->queryParams()])
        @if ($filters->quickFilter() !== 'all')
            <input type="hidden" name="filter" value="{{ $filters->quickFilter() }}">
        @endif

        <div class="filter-field filter-field-search">
            <label for="filter_q">Search</label>
            <input type="search" name="q" id="filter_q" value="{{ $search->term() ?? '' }}" placeholder="{{ $searchPlaceholder }}" autocomplete="off">
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

        @if ($hasTagFilter)
            @include('artworks._index_typed_tag_combobox', [
                'param' => 'tag',
                'label' => 'Tag',
                'emptyLabel' => 'Any tag',
                'options' => $tagOptions,
                'selected' => $selectedTag,
            ])
        @endif

        <div class="filter-field">
            <label for="filter_dimension_unit">Dimension unit</label>
            <select name="dimension_unit" id="filter_dimension_unit">
                <option value="">Any</option>
                @foreach ($dimensionUnits ?? [] as $unitOption)
                    <option value="{{ $unitOption }}" @selected($filters->dimensionUnit() === $unitOption)>{{ $unitOption }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="filter_width_min">Width min</label>
            <input type="number" name="width_min" id="filter_width_min" step="0.01" min="0" value="{{ $filters->widthMin() }}">
        </div>

        <div class="filter-field">
            <label for="filter_width_max">Width max</label>
            <input type="number" name="width_max" id="filter_width_max" step="0.01" min="0" value="{{ $filters->widthMax() }}">
        </div>

        <div class="filter-field">
            <label for="filter_height_min">Height min</label>
            <input type="number" name="height_min" id="filter_height_min" step="0.01" min="0" value="{{ $filters->heightMin() }}">
        </div>

        <div class="filter-field">
            <label for="filter_height_max">Height max</label>
            <input type="number" name="height_max" id="filter_height_max" step="0.01" min="0" value="{{ $filters->heightMax() }}">
        </div>

        <button type="submit" class="btn filter-apply-btn">Apply</button>
    </form>

    @if ($hasActiveViewModifiers)
        <p class="artwork-filters-clear">
            @if ($filters->hasActiveFilters())
                <a href="{{ route($listingRoute, array_merge($search->queryParams(), $sort->queryParams())) }}">Clear filters</a>
                <span class="artwork-filters-clear-sep"> · </span>
            @endif
            <a href="{{ route($listingRoute) }}">Reset view</a>
        </p>
    @endif
</section>

@if ($hasTagFilter)
    @include('artworks._index_typed_tag_combobox_script')
@endif
