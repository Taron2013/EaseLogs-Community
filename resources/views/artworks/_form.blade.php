@php
    $completedWorkChecked = old('completed_work') !== null
        ? (bool) old('completed_work')
        : ($artwork->exists && $artwork->isCompleted());
@endphp

@if ($errors->any())
    <div class="errors" role="alert" aria-live="polite">
        <strong>There were some problems with your submission.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="form-section panel-card">
    <div class="field">
        <label for="title">Title</label>
        <input type="text" name="title" id="title" autocomplete="off" placeholder="Untitled if left blank" value="{{ old('title', $artwork->title) }}">
        <p class="field-hint">Optional. Leave blank for Untitled.</p>
    </div>

    <div class="field">
        <label for="start_date">Start date</label>
        <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $artwork->start_date?->format('Y-m-d')) }}">
    </div>

    <div class="field field-inline">
        <input type="hidden" name="completed_work" value="0">
        <input
            type="checkbox"
            name="completed_work"
            id="completed_work"
            value="1"
            @checked($completedWorkChecked)
        >
        <label for="completed_work">Completed work</label>
    </div>

    <div id="completed-date-field" class="field" style="{{ $completedWorkChecked ? '' : 'display:none;' }}">
        <label for="completed_date">Completed date</label>
        <input type="date" name="completed_date" id="completed_date" value="{{ old('completed_date', $artwork->completed_date?->format('Y-m-d')) }}">
        <p class="field-hint">Only used when this artwork is marked as completed.</p>
    </div>

    <div class="field">
        <label for="artwork_type">Artwork type</label>
        <input type="text" name="artwork_type" id="artwork_type" autocomplete="off" placeholder="e.g. Painting, Drawing, Sculpture" value="{{ old('artwork_type', $artwork->artwork_type) }}">
    </div>

    <div class="field">
        <label for="medium">Medium</label>
        <input type="text" name="medium" id="medium" autocomplete="off" placeholder="e.g. Oil on canvas" value="{{ old('medium', $artwork->medium) }}">
    </div>

    <div class="field">
        <label for="height">Height</label>
        <input type="number" name="height" id="height" step="0.01" min="0" placeholder="Height" value="{{ old('height', $artwork->height) }}">
    </div>

    <div class="field">
        <label for="width">Width</label>
        <input type="number" name="width" id="width" step="0.01" min="0" placeholder="Width" value="{{ old('width', $artwork->width) }}">
    </div>

    <div class="field">
        <label for="depth">Depth</label>
        <input type="number" name="depth" id="depth" step="0.01" min="0" placeholder="Depth" value="{{ old('depth', $artwork->depth) }}">
    </div>

    <div class="field">
        <label for="dimension_unit">Dimension unit</label>
        <input type="text" name="dimension_unit" id="dimension_unit" autocomplete="off" placeholder="e.g. in, cm" value="{{ old('dimension_unit', $artwork->dimension_unit ?? 'in') }}">
    </div>

    @include('artworks._photo_field', [
        'prominentPreview' => $artwork->exists,
        'completedWorkChecked' => $completedWorkChecked,
    ])

    <div class="field">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" rows="4" placeholder="Optional notes about this artwork">{{ old('notes', $artwork->notes) }}</textarea>
    </div>
</div>

<script>
    (function () {
        const completedWork = document.getElementById('completed_work');
        const completedDateField = document.getElementById('completed-date-field');
        const completedDateInput = document.getElementById('completed_date');
        const photoConfirmField = document.getElementById('completed-photo-confirm-field');

        if (!completedWork || !completedDateField || !completedDateInput) {
            return;
        }

        const today = () => new Date().toISOString().slice(0, 10);

        const updateCompletedDateVisibility = () => {
            const visible = completedWork.checked;
            completedDateField.style.display = visible ? '' : 'none';

            if (!visible) {
                completedDateInput.value = '';
            } else if (!completedDateInput.value) {
                completedDateInput.value = today();
            }

            if (photoConfirmField) {
                photoConfirmField.style.display = visible ? '' : 'none';
            }
        };

        completedWork.addEventListener('change', updateCompletedDateVisibility);
        updateCompletedDateVisibility();
    })();
</script>
