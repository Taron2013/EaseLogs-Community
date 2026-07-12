@php
    $param = $param ?? 'style_tags';
    $label = $label ?? 'Style';
    $emptyLabel = $emptyLabel ?? 'Any style';
    $options = $options ?? collect();
    $selected = $selected ?? '';
    $inputId = 'filter_'.$param.'_combobox';
    $listId = 'filter_'.$param.'_suggestions';
    $fallbackId = 'filter_'.$param;
@endphp

<div class="filter-field filter-tag-combobox" data-empty-label="{{ $emptyLabel }}">
    <label for="{{ $fallbackId }}">{{ $label }}</label>
    <select
        name="{{ $param }}"
        id="{{ $fallbackId }}"
        class="filter-tag-combobox-fallback"
    >
        <option value="">{{ $emptyLabel }}</option>
        @foreach ($options as $tagOption)
            <option value="{{ $tagOption }}" @selected($selected === $tagOption)>{{ $tagOption }}</option>
        @endforeach
    </select>
    <div class="filter-tag-combobox-enhanced" hidden>
        <input
            type="text"
            id="{{ $inputId }}"
            class="filter-tag-combobox-input"
            value="{{ $selected }}"
            placeholder="{{ $emptyLabel }}"
            autocomplete="off"
            list="{{ $listId }}"
            role="combobox"
            aria-autocomplete="list"
        >
        <datalist id="{{ $listId }}">
            @foreach ($options as $tagOption)
                <option value="{{ $tagOption }}"></option>
            @endforeach
        </datalist>
    </div>
</div>
