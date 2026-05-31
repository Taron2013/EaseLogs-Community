@php
    $latestPhoto = $artwork->latestPhoto;
    $prominentPreview = $prominentPreview ?? false;
    $completedWorkChecked = $completedWorkChecked ?? false;
    $maxKb = (int) config('easelogs.photo_max_kb', 10240);
    $maxMb = rtrim(rtrim(number_format($maxKb / 1024, 1), '0'), '.');
@endphp

<div class="form-section">
    <h2>Photo</h2>

    @if ($latestPhoto?->existsOnDisk())
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
        <p class="field-hint" style="margin-top:0;">
            @if ($latestPhoto)
                Photo file is missing on disk. Upload a new image below.
            @else
                No photo yet. Upload one below.
            @endif
        </p>
        <img id="photo-edit-preview" alt="" class="artwork-photo-edit-reference" hidden>
    @endif

    <div class="field">
        <label for="photo">{{ ($latestPhoto && $latestPhoto->existsOnDisk()) ? 'Replace photo' : 'Artwork photo' }}</label>
        <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp">
        <p class="field-hint">JPEG, PNG, or WebP. Max {{ $maxMb }} MB. Uploading a new photo makes it the latest primary photo.</p>
    </div>

    <div id="completed-photo-confirm-field" class="field" style="{{ $completedWorkChecked ? '' : 'display:none;' }}">
        <input type="hidden" name="confirm_completed_photo_upload" value="0">
        <div class="field-inline">
            <input
                type="checkbox"
                name="confirm_completed_photo_upload"
                id="confirm_completed_photo_upload"
                value="1"
                @checked(old('confirm_completed_photo_upload'))
            >
            <label for="confirm_completed_photo_upload">
                I understand this will replace or add a new image for a completed artwork.
            </label>
        </div>
        @error('confirm_completed_photo_upload')
            <p class="field-hint" style="color:#b71c1c;margin-top:0.35rem;">{{ $message }}</p>
        @enderror
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
