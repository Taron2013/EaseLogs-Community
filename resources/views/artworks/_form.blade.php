@php
    $isEdit = $artwork->exists;
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

<div class="form-section">
    <h2>Identification</h2>

    <div class="field">
        <label for="title">Title</label>
        <input type="text" name="title" id="title" autocomplete="off" placeholder="Untitled if left blank" value="{{ old('title', $artwork->title) }}">
        <p class="field-hint" id="title-help">Optional. Leave blank for Untitled.</p>
    </div>

    <div class="field">
        <label for="inventory_code">Inventory code</label>
        <input type="text" name="inventory_code" id="inventory_code" autocomplete="off" placeholder="Auto-generate when blank" value="{{ old('inventory_code', $artwork->inventory_code) }}" aria-describedby="inventory-code-help">
        <p class="field-hint" id="inventory-code-help">Leave blank to auto-generate (e.g. ART-2026-0001).</p>
    </div>

    <div class="field">
        <label for="sku">SKU</label>
        <input type="text" name="sku" id="sku" autocomplete="off" placeholder="Auto-generate when blank" value="{{ old('sku', $artwork->sku) }}" aria-describedby="sku-help">
        <p class="field-hint" id="sku-help">Leave blank to auto-generate (e.g. 2026#1).</p>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="4" placeholder="Optional notes, exhibition context, provenance">{{ old('description', $artwork->description) }}</textarea>
    </div>
</div>

<div class="form-section">
    <h2>Dates</h2>

    <div class="field">
        <label for="started_date">Started date</label>
        <input type="date" name="started_date" id="started_date" value="{{ old('started_date', $artwork->started_date?->format('Y-m-d')) }}">
    </div>

    <div class="field field-inline">
        <input type="checkbox" name="started_date_is_estimated" id="started_date_is_estimated" value="1"
            @checked(old('started_date_is_estimated', $artwork->started_date_is_estimated))>
        <label for="started_date_is_estimated">Started date is estimated</label>
    </div>

    <div class="field">
        @php
            $statusValue = old('status', $artwork->status ?? 'in_progress');
            $isComplete = $statusValue !== 'in_progress';
        @endphp

        <div id="finished-date-field" style="{{ $isComplete ? '' : 'display:none;' }}">
            <label for="finished_date">Finished date</label>
            <input type="date" name="finished_date" id="finished_date" value="{{ old('finished_date', $artwork->finished_date?->format('Y-m-d')) }}" aria-describedby="finished-date-help">
            <p class="field-hint" id="finished-date-help">Used for SKU year when auto-generating.</p>
        </div>
    </div>

    <div id="finished-date-is-estimated-field" class="field field-inline" style="{{ $isComplete ? '' : 'display:none;' }}">
        <input type="checkbox" name="finished_date_is_estimated" id="finished_date_is_estimated" value="1"
            @checked(old('finished_date_is_estimated', $artwork->finished_date_is_estimated))>
        <label for="finished_date_is_estimated">Finished date is estimated</label>
    </div>
</div>

<div class="form-section">
    <h2>Physical details</h2>

    <div class="field">
        <label for="medium">Medium</label>
        <input type="text" name="medium" id="medium" autocomplete="off" placeholder="e.g. Oil on canvas" value="{{ old('medium', $artwork->medium) }}">
    </div>

    <div class="field">
        <label for="surface">Surface</label>
        <input type="text" name="surface" id="surface" autocomplete="off" placeholder="e.g. Cotton canvas" value="{{ old('surface', $artwork->surface) }}">
    </div>

    <div class="field">
        <label for="width">Width</label>
        <input type="number" name="width" id="width" step="0.01" min="0" placeholder="Width" value="{{ old('width', $artwork->width) }}">
    </div>

    <div class="field">
        <label for="height">Height</label>
        <input type="number" name="height" id="height" step="0.01" min="0" placeholder="Height" value="{{ old('height', $artwork->height) }}">
    </div>

    <div class="field">
        <label for="depth">Depth</label>
        <input type="number" name="depth" id="depth" step="0.01" min="0" placeholder="Depth" value="{{ old('depth', $artwork->depth) }}">
    </div>

    <div class="field">
        <label for="dimension_unit">Dimension unit</label>
        <input type="text" name="dimension_unit" id="dimension_unit" autocomplete="off" placeholder="e.g. in, cm" value="{{ old('dimension_unit', $artwork->dimension_unit ?? 'in') }}">
    </div>
</div>

<div class="form-section">
    <h2>Classification</h2>

    <div class="field">
        <label for="category">Category</label>
        <input type="text" name="category" id="category" autocomplete="off" placeholder="e.g. Painting, Sculpture" value="{{ old('category', $artwork->category) }}">
    </div>

    <div class="field">
        <label for="style">Style</label>
        <input type="text" name="style" id="style" autocomplete="off" placeholder="e.g. Abstract, Realism" value="{{ old('style', $artwork->style) }}">
    </div>

    <div class="field">
        <label for="subject">Subject</label>
        <input type="text" name="subject" id="subject" autocomplete="off" placeholder="e.g. Landscape, Portrait" value="{{ old('subject', $artwork->subject) }}">
    </div>

    <div class="field">
        <label for="status">Status</label>
        <select name="status" id="status">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $artwork->status ?? 'in_inventory') === $status)>
                    {{ str_replace('_', ' ', ucfirst($status)) }}
                </option>
            @endforeach
        </select>
    </div>

    @if (config('artdoc.enable_professional_reproduction_photo_tracking', true))
        @php
            $statusValue = old('status', $artwork->status ?? 'in_progress');
            $isComplete = $statusValue !== 'in_progress';
        @endphp

        <div id="professional-art-reproduction-photo-field" class="field" style="{{ $isComplete ? '' : 'display:none;' }}">
            <input type="hidden" name="professional_art_reproduction_photo" value="0">
            <div class="field-inline">
                <input
                    type="checkbox"
                    name="professional_art_reproduction_photo"
                    id="professional_art_reproduction_photo"
                    value="1"
                    @checked(old('professional_art_reproduction_photo', $artwork->professional_art_reproduction_photo))
                >
                <label for="professional_art_reproduction_photo">Professional Art Reproduction Photo</label>
            </div>
            <p class="field-hint">Only available after the artwork is marked finished.</p>
        </div>

        <script>
            (function () {
                const status = document.getElementById('status');
                const photoField = document.getElementById('professional-art-reproduction-photo-field');
                const photoCheckbox = document.getElementById('professional_art_reproduction_photo');

                const finishedDateField = document.getElementById('finished-date-field');
                const finishedDateInput = document.getElementById('finished_date');
                const finishedDateIsEstimatedField = document.getElementById('finished-date-is-estimated-field');
                const finishedDateIsEstimatedCheckbox = document.getElementById('finished_date_is_estimated');

                if (!status || !photoField || !photoCheckbox || !finishedDateField || !finishedDateInput || !finishedDateIsEstimatedField || !finishedDateIsEstimatedCheckbox) {
                    return;
                }

                const updatePhotoState = () => {
                    const visible = status.value !== 'in_progress';
                    photoField.style.display = visible ? '' : 'none';
                    if (!visible) {
                        photoCheckbox.checked = false;
                    }
                };

                const today = () => new Date().toISOString().slice(0, 10);

                const updateFinishedDateState = () => {
                    const visible = status.value !== 'in_progress';
                    finishedDateField.style.display = visible ? '' : 'none';
                    finishedDateIsEstimatedField.style.display = visible ? '' : 'none';

                    if (!visible) {
                        finishedDateInput.value = '';
                        finishedDateIsEstimatedCheckbox.checked = false;
                    } else if (!finishedDateInput.value) {
                        finishedDateInput.value = today();
                    }
                };

                status.addEventListener('change', () => { updatePhotoState(); updateFinishedDateState(); });
                updatePhotoState();
                updateFinishedDateState();
            })();
        </script>
    @endif

    <div class="field">
        <label for="condition">Condition</label>
        <select name="condition" id="condition">
            @foreach ($conditions as $condition)
                <option value="{{ $condition }}" @selected(old('condition', $artwork->condition ?? 'good') === $condition)>
                    {{ str_replace('_', ' ', ucfirst($condition)) }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-section">
    <h2>Storage &amp; value</h2>

    <div class="field">
        <label for="location">Location</label>
        <input type="text" name="location" id="location" autocomplete="off" placeholder="e.g. Studio, Gallery" value="{{ old('location', $artwork->location) }}">
    </div>

    <div class="field">
        <label for="storage_area">Storage area</label>
        <input type="text" name="storage_area" id="storage_area" autocomplete="off" placeholder="e.g. Shelf A" value="{{ old('storage_area', $artwork->storage_area) }}">
    </div>

    <div class="field">
        <label for="estimated_value">Estimated value</label>
        <input type="number" name="estimated_value" id="estimated_value" step="0.01" min="0" placeholder="Optional value" value="{{ old('estimated_value', $artwork->estimated_value) }}">
    </div>

    <div class="field">
        <label for="sale_price">Sale price</label>
        <input type="number" name="sale_price" id="sale_price" step="0.01" min="0" placeholder="Optional price" value="{{ old('sale_price', $artwork->sale_price) }}">
    </div>

    <div class="field">
        <label for="currency">Currency</label>
        <input type="text" name="currency" id="currency" autocomplete="off" placeholder="e.g. USD" value="{{ old('currency', $artwork->currency ?? 'USD') }}">
    </div>

    <div class="field">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" rows="4" placeholder="Optional notes for inventory or condition">{{ old('notes', $artwork->notes) }}</textarea>
    </div>
</div>
