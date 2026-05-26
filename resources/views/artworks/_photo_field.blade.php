@php
    $latestPhoto = $artwork->latestPhoto;
    $prominentPreview = $prominentPreview ?? false;
    $maxKb = (int) config('easelogs.photo_max_kb', 10240);
    $maxMb = rtrim(rtrim(number_format($maxKb / 1024, 1), '0'), '.');
@endphp

<div class="form-section">
    <h2>Photo</h2>

    @if ($latestPhoto)
        <div class="field {{ $prominentPreview ? 'artwork-edit-photo-wrap' : '' }}">
            <p class="field-hint" style="margin-top:0;">
                {{ $prominentPreview ? 'Current artwork photo' : 'Current photo' }}
            </p>
            <img
                id="photo-edit-preview"
                src="{{ $latestPhoto->publicUrl() }}"
                alt="Photo of {{ $artwork->title ?: 'Untitled' }}"
                class="{{ $prominentPreview ? 'artwork-photo-edit-reference' : 'artwork-photo-preview' }}"
            >
        </div>
    @else
        <p class="field-hint" style="margin-top:0;">No photo yet. Upload one below.</p>
        <img id="photo-edit-preview" alt="" class="artwork-photo-edit-reference" hidden>
    @endif

    <div class="field">
        <label for="photo">{{ $latestPhoto ? 'Replace photo' : 'Artwork photo' }}</label>
        <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp">
        <p class="field-hint">JPEG, PNG, or WebP. Max {{ $maxMb }} MB. Uploading a new photo makes it the latest primary photo.</p>
    </div>
</div>

@if ($prominentPreview)
    <script>
        (function () {
            const input = document.getElementById('photo');
            const preview = document.getElementById('photo-edit-preview');
            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) {
                    return;
                }

                preview.hidden = false;
                preview.src = URL.createObjectURL(file);
            });
        })();
    </script>
@endif
