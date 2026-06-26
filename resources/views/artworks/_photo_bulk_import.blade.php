<div class="form-section" style="margin-top:1.5rem;">
    <h2 style="font-size:1rem;margin:0 0 0.5rem;">Bulk photo import</h2>
    <p class="field-hint" style="margin:0 0 0.75rem;">
        Drop in a ZIP of photos. EaseLogs reads each filename, normalizes it, and tries to match your existing artworks automatically.
        A mapping CSV is optional — use it only to override or refine matches with <code>artwork_id</code> or a title candidate.
        <code>inventory_code</code> and <code>sku</code> are Pro-only.
    </p>
    @if ($easelogsDemo['blocks_imports'] ?? false)
        <p class="field-hint demo-restriction-notice">{{ $easelogsDemo['message_imports'] }}</p>
    @else
        @php($photoImportUpload = $photoImportUpload ?? \App\Support\ArtworkPhotoBulkImport\PhotoImportUploadEnvironment::viewData())
        <form method="POST" action="{{ route('artworks.photo-bulk-import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row-2">
                <div class="field" style="margin-bottom:0;">
                    <label for="photo_zip">Photo ZIP</label>
                    <input type="file" name="photo_zip" id="photo_zip" accept=".zip,application/zip" required>
                    @error('photo_zip')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="mapping_csv">Mapping CSV <span class="field-optional">(optional)</span></label>
                    <input type="file" name="mapping_csv" id="mapping_csv" accept=".csv,text/csv">
                    @error('mapping_csv')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <p class="field-hint" style="margin:0.75rem 0;">
                Optional CSV columns: <code>filename</code>, <code>artwork_id</code>,
                title candidate (<code>title</code>, <code>artwork_title</code>, or <code>name</code>),
                <code>set_as_current</code>, <code>caption</code>.
                Effective ZIP limit on this server:
                <strong>{{ number_format($photoImportUpload['effective_max_mb'] ?? $photoImportUpload['app_max_mb']) }} MB</strong>
                @if (($photoImportUpload['effective_max_mb'] ?? $photoImportUpload['app_max_mb']) < $photoImportUpload['app_max_mb'])
                    (EaseLogs app: {{ number_format($photoImportUpload['app_max_mb']) }} MB; PHP is lower).
                @else
                    (EaseLogs app: {{ number_format($photoImportUpload['app_max_mb']) }} MB).
                @endif
                See <code>docs/BULK_PHOTO_IMPORT.md</code>. Diagnostics: <code>/health/photo-import-upload</code>.
            </p>
            @foreach ($photoImportUpload['warnings'] as $warning)
                <div class="flash flash-warning" style="margin-bottom:0.75rem;">{{ $warning }}</div>
            @endforeach
            <button type="submit" class="btn btn-primary">Preview photo import</button>
        </form>
    @endif
</div>
