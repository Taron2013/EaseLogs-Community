<script>
(function () {
    function normalizeTagValue(value) {
        return value.trim().toLowerCase();
    }

    function matchOption(options, value) {
        var normalized = normalizeTagValue(value);

        if (normalized === '') {
            return '';
        }

        for (var i = 0; i < options.length; i += 1) {
            if (options[i].toLowerCase() === normalized) {
                return options[i];
            }
        }

        return null;
    }

    function enhanceFilterTagComboboxes(root) {
        root.querySelectorAll('.filter-tag-combobox:not([data-enhanced])').forEach(function (wrapper) {
            var fallback = wrapper.querySelector('.filter-tag-combobox-fallback');
            var enhanced = wrapper.querySelector('.filter-tag-combobox-enhanced');
            var input = wrapper.querySelector('.filter-tag-combobox-input');

            if (!fallback || !enhanced || !input) {
                return;
            }

            var options = Array.from(fallback.options)
                .map(function (option) { return option.value; })
                .filter(function (value) { return value !== ''; });
            var param = fallback.getAttribute('name');
            var form = wrapper.closest('form');

            input.name = param;
            fallback.removeAttribute('name');
            fallback.disabled = true;
            fallback.setAttribute('aria-hidden', 'true');
            fallback.tabIndex = -1;

            var label = wrapper.querySelector('label');
            if (label) {
                label.htmlFor = input.id;
            }

            enhanced.hidden = false;
            wrapper.classList.add('is-enhanced');
            wrapper.setAttribute('data-enhanced', 'true');

            function syncSubmittedValue() {
                var matched = matchOption(options, input.value);

                if (matched === null) {
                    input.value = '';
                } else {
                    input.value = matched;
                }
            }

            input.addEventListener('change', syncSubmittedValue);
            input.addEventListener('blur', syncSubmittedValue);

            if (form) {
                form.addEventListener('submit', syncSubmittedValue);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            enhanceFilterTagComboboxes(document);
        });
    } else {
        enhanceFilterTagComboboxes(document);
    }
})();
</script>
