@php
    $mediumSuggestions = $mediumSuggestions ?? app(\App\Services\ArtworkMediumSuggestionService::class)->formSuggestions(auth()->user());
@endphp

<div class="field">
    <label for="medium">Medium</label>
    <input
        type="text"
        name="medium"
        id="medium"
        autocomplete="off"
        list="artwork-medium-suggestions"
        maxlength="255"
        placeholder="e.g. Oil on canvas"
        value="{{ old('medium', $artwork->medium) }}"
    >
    <datalist id="artwork-medium-suggestions">
        @foreach ($mediumSuggestions as $mediumSuggestion)
            <option value="{{ $mediumSuggestion }}"></option>
        @endforeach
    </datalist>
</div>
