@props(['filters', 'search', 'sort', 'listingRoute' => 'artworks.index'])

@php
    $listQuery = array_merge($filters->queryParams(), $search->queryParams());
@endphp

<section class="artwork-sort-bar" aria-label="Sort artworks">
    <p class="artwork-filters-label">Sort</p>
    <div class="artwork-sort-quick">
        <a href="{{ route($listingRoute, $listQuery) }}"
           class="filter-pill{{ $sort->usesDefaultListing() ? ' is-active' : '' }}">Recently updated</a>
    </div>

    <form method="GET" action="{{ route($listingRoute) }}" class="artwork-sort-fields artwork-sort-fields-mobile">
        @foreach ($filters->queryParams() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        @foreach ($search->queryParams() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <div class="filter-field">
            <label for="mobile_sort_column">Sort by</label>
            <select name="sort" id="mobile_sort_column">
                <option value="" @selected($sort->usesDefaultListing())>Recently updated</option>
                <option value="title" @selected($sort->column() === 'title')>Title</option>
                <option value="medium" @selected($sort->column() === 'medium')>Medium</option>
                <option value="dimensions" @selected($sort->column() === 'dimensions')>Dimensions</option>
                <option value="start_date" @selected($sort->column() === 'start_date')>Start date</option>
                <option value="completed_date" @selected($sort->column() === 'completed_date')>Completed date</option>
                <option value="updated_at" @selected($sort->column() === 'updated_at')>Updated</option>
            </select>
        </div>

        <div class="filter-field">
            <label for="mobile_sort_direction">Direction</label>
            <select name="direction" id="mobile_sort_direction">
                <option value="desc" @selected($sort->usesDefaultListing() || $sort->direction() === 'desc')>Descending</option>
                <option value="asc" @selected(! $sort->usesDefaultListing() && $sort->direction() === 'asc')>Ascending</option>
            </select>
        </div>

        <button type="submit" class="btn filter-apply-btn">Apply sort</button>
    </form>
</section>
