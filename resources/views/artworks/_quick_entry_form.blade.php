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

<div class="form-section">
    <h2>Quick entry</h2>

    <div class="field">
        <label for="title">Title (optional)</label>
        <input type="text" name="title" id="title" autocomplete="off" placeholder="Leave blank for Untitled" value="{{ old('title', $artwork->title) }}">
        <p class="field-hint">Optional. Add a title later from the edit page.</p>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="started_date">Started date</label>
            <input type="date" name="started_date" id="started_date" value="{{ old('started_date', $artwork->started_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
        </div>

        <div class="field">
            <input type="hidden" name="finished_painting" value="0">
            <div class="field-inline">
                <input type="checkbox" name="finished_painting" id="finished_painting" value="1" @checked(old('finished_painting'))>
                <label for="finished_painting">Finished painting</label>
            </div>
            <p class="field-hint">Check only if the work is complete and ready for inventory.</p>
        </div>
    </div>

    <div id="finished-date-field" class="field" style="{{ old('finished_painting') ? '' : 'display:none;' }}">
        <label for="finished_date">Finished date</label>
        <input type="date" name="finished_date" id="finished_date" value="{{ old('finished_date', $artwork->finished_date?->format('Y-m-d')) }}" aria-describedby="finished-date-help">
        <p class="field-hint" id="finished-date-help">Date the artwork was finished. Defaults to today when marked finished.</p>
    </div>

    @if (config('artdoc.enable_professional_reproduction_photo_tracking', true))
        <div id="professional-photo-field" class="field" style="{{ old('finished_painting') ? '' : 'display:none;' }}">
            <input type="hidden" name="professional_art_reproduction_photo" value="0">
            <div class="field-inline">
                <input type="checkbox" name="professional_art_reproduction_photo" id="professional_art_reproduction_photo" value="1" @checked(old('professional_art_reproduction_photo'))>
                <label for="professional_art_reproduction_photo">Professional Art Reproduction Photo</label>
            </div>
            <p class="field-hint">Only available after the artwork is marked finished.</p>
        </div>

        <script>
            (function () {
                const finished = document.getElementById('finished_painting');
                const photoField = document.getElementById('professional-photo-field');
                const photoCheckbox = document.getElementById('professional_art_reproduction_photo');
                const finishedDateField = document.getElementById('finished-date-field');
                const finishedDateInput = document.getElementById('finished_date');

                if (!finished || !photoField || !photoCheckbox || !finishedDateField || !finishedDateInput) {
                    return;
                }

                const today = () => new Date().toISOString().slice(0, 10);

                const updateState = () => {
                    const visible = finished.checked;
                    photoField.style.display = visible ? '' : 'none';
                    finishedDateField.style.display = visible ? '' : 'none';

                    if (!visible) {
                        photoCheckbox.checked = false;
                        finishedDateInput.value = '';
                    } else {
                        if (!finishedDateInput.value) {
                            finishedDateInput.value = today();
                        }
                    }
                };

                finished.addEventListener('change', updateState);
                updateState();
            })();
        </script>
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="medium">Medium</label>
            <input type="text" name="medium" id="medium" autocomplete="off" placeholder="e.g. Oil on canvas" value="{{ old('medium', $artwork->medium) }}">
        </div>

        <div class="field">
            <label for="surface">Canvas / support</label>
            <input type="text" name="surface" id="surface" autocomplete="off" placeholder="e.g. Linen, Panel" value="{{ old('surface', $artwork->surface) }}">
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="width">Width</label>
            <input type="number" name="width" id="width" step="0.01" min="0" placeholder="Width" value="{{ old('width', $artwork->width) }}">
        </div>

        <div class="field">
            <label for="height">Height</label>
            <input type="number" name="height" id="height" step="0.01" min="0" placeholder="Height" value="{{ old('height', $artwork->height) }}">
        </div>

        <div class="field">
            <label for="dimension_unit">Unit</label>
            <input type="text" name="dimension_unit" id="dimension_unit" autocomplete="off" placeholder="in" value="{{ old('dimension_unit', $artwork->dimension_unit ?? 'in') }}">
        </div>
    </div>

    <div class="field">
        <label for="inventory_code">Inventory code</label>
        <input type="text" name="inventory_code" id="inventory_code" autocomplete="off" placeholder="Auto-generate when blank" value="{{ old('inventory_code', $artwork->inventory_code) }}">
        <p class="field-hint">Optional. Leave blank and the system will generate one.</p>
    </div>

    <div class="field">
        <label for="sku">SKU</label>
        <input type="text" name="sku" id="sku" autocomplete="off" placeholder="Auto-generate when blank" value="{{ old('sku', $artwork->sku) }}">
        <p class="field-hint">Optional. Leave blank to let the app assign a SKU.</p>
    </div>
</div>
