@php
    $profile = $artwork->publishingProfile;
    $fields = [
        'short_description' => 'Short description',
        'product_description' => 'Product description',
        'story_inspiration' => 'Story / inspiration',
        'materials_process' => 'Materials & process',
    ];
@endphp

<section class="form-section" aria-labelledby="publishing-show-heading" style="margin-top:1.5rem;">
    <h2 id="publishing-show-heading" style="margin-top:0;font-size:1.1rem;">Publishing</h2>
    <p class="field-hint" style="margin:0 0 1rem;">
        Public-facing copy for websites, marketplaces, and social posts.
        <a href="{{ route('artworks.edit', $artwork) }}#publishing">Edit publishing copy</a>
    </p>

    @foreach ($fields as $key => $label)
        @php
            $value = trim((string) ($profile?->{$key} ?? ''));
        @endphp
        <div class="field" style="margin-bottom:1rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
                <h3 style="margin:0;font-size:0.95rem;font-weight:600;">{{ $label }}</h3>
                @if ($value !== '')
                    <button type="button" class="btn publishing-copy-btn" data-copy-source="publishing-{{ $key }}">Copy</button>
                @endif
            </div>
            @if ($value !== '')
                <pre id="publishing-{{ $key }}" class="publishing-copy-source" style="margin:0.5rem 0 0;white-space:pre-wrap;font-family:inherit;background:#f8f8f6;border:1px solid #e4e4e2;border-radius:6px;padding:0.75rem;">{{ $value }}</pre>
            @else
                <p class="field-hint" style="margin:0.35rem 0 0;">—</p>
            @endif
        </div>
    @endforeach
</section>

<script>
    (function () {
        document.querySelectorAll('.publishing-copy-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const sourceId = button.getAttribute('data-copy-source');
                const source = sourceId ? document.getElementById(sourceId) : null;

                if (!source) {
                    return;
                }

                const text = source.textContent || '';

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        const original = button.textContent;
                        button.textContent = 'Copied';
                        window.setTimeout(function () {
                            button.textContent = original;
                        }, 1500);
                    });

                    return;
                }

                const range = document.createRange();
                range.selectNodeContents(source);
                const selection = window.getSelection();
                selection?.removeAllRanges();
                selection?.addRange(range);
            });
        });
    })();
</script>
