@php
    use App\Support\ArtworkTagType;
    use App\Services\ArtworkTagService;

    $tagOptions = $tagOptions ?? collect();
    $selected = old('tags', $artwork->exists ? $artwork->tagNamesForType(ArtworkTagType::GENERAL) : []);
    $selected = is_array($selected) ? $selected : [];

    $pool = $tagOptions
        ->merge(ArtworkTagService::EXAMPLE_GENERAL_TAGS)
        ->unique()
        ->sort()
        ->values();
@endphp

<div class="tag-picker" aria-label="Artwork tags">
    <div class="field tag-picker-section" data-field-name="tags">
        <label for="tag-input-tags">Tags</label>
        <div class="tag-picker-selected" id="tag-picker-selected-tags" role="list" aria-label="Selected tags">
            @foreach ($selected as $tagName)
                <span class="tag-chip tag-chip-general" role="listitem">
                    <span class="tag-chip-label">{{ $tagName }}</span>
                    <button type="button" class="tag-chip-remove" aria-label="Remove {{ $tagName }}">&times;</button>
                    <input type="hidden" name="tags[]" value="{{ $tagName }}">
                </span>
            @endforeach
        </div>
        <div class="tag-picker-add">
            <input
                type="text"
                id="tag-input-tags"
                class="tag-picker-input"
                list="tag-suggestions-tags"
                maxlength="255"
                placeholder="Add tags…"
                autocomplete="off"
            >
            <datalist id="tag-suggestions-tags">
                @foreach ($pool as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
            <button type="button" class="btn tag-picker-add-btn">Add</button>
        </div>
        <p class="field-hint">Optional. Select existing tags, type new ones, or enter comma-separated names.</p>
    </div>
</div>

<script>
    (function () {
        const section = document.querySelector('.tag-picker-section[data-field-name="tags"]');

        if (! section) {
            return;
        }

        const fieldName = section.dataset.fieldName;
        const selectedContainer = section.querySelector('.tag-picker-selected');
        const input = section.querySelector('.tag-picker-input');
        const addBtn = section.querySelector('.tag-picker-add-btn');

        if (!selectedContainer || !input || !addBtn || !fieldName) {
            return;
        }

        function selectedNames() {
            return Array.from(selectedContainer.querySelectorAll('input[type="hidden"]'))
                .map(function (el) { return el.value.trim().toLowerCase(); });
        }

        function addTag(name) {
            name = name.trim();
            if (name === '') {
                return;
            }

            if (selectedNames().includes(name.toLowerCase())) {
                return;
            }

            const chip = document.createElement('span');
            chip.className = 'tag-chip tag-chip-general';
            chip.setAttribute('role', 'listitem');

            const label = document.createElement('span');
            label.className = 'tag-chip-label';
            label.textContent = name;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'tag-chip-remove';
            removeBtn.setAttribute('aria-label', 'Remove ' + name);
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', function () {
                chip.remove();
            });

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = fieldName + '[]';
            hidden.value = name;

            chip.appendChild(label);
            chip.appendChild(removeBtn);
            chip.appendChild(hidden);
            selectedContainer.appendChild(chip);
        }

        function addFromInputValue() {
            const raw = input.value;

            if (raw.includes(',')) {
                raw.split(',').forEach(function (part) {
                    addTag(part);
                });
            } else {
                addTag(raw);
            }

            input.value = '';
        }

        addBtn.addEventListener('click', addFromInputValue);

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addFromInputValue();
            }
        });

        selectedContainer.querySelectorAll('.tag-chip-remove').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('.tag-chip')?.remove();
            });
        });
    })();
</script>
