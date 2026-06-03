<div class="form-section">
    <h2 style="font-size:1rem;margin:0 0 0.5rem;">Export CSV</h2>
    <p class="field-hint" style="margin:0 0 0.75rem;">
        Download artwork metadata as a CSV file. Photos are not included.
    </p>
    <a href="{{ route('artworks.export.csv') }}" class="btn btn-primary">Export CSV</a>
</div>

<div class="form-section" style="margin-top:1.5rem;">
    <h2 style="font-size:1rem;margin:0 0 0.5rem;">Import CSV</h2>
    <p class="field-hint" style="margin:0 0 0.75rem;">
        Metadata only. Photos are not included in CSV import or export. Include any subset of approved columns
        (title, start_date, completed_date, artwork_type, medium, height, width, depth, dimension_unit, notes).
        Extra columns are ignored. Dates accept common formats (YYYY-MM-DD, MM/DD/YYYY, written dates, ISO date-times); export uses YYYY-MM-DD.
        Unsupported fields (inventory, photos, and similar) are rejected.
    </p>
    <form method="POST" action="{{ route('artworks.import.csv') }}" enctype="multipart/form-data" class="field-inline" style="align-items:flex-end;flex-wrap:wrap;gap:0.75rem;">
        @csrf
        <div class="field" style="margin-bottom:0;">
            <label for="csv">CSV file</label>
            <input type="file" name="csv" id="csv" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn">Import CSV</button>
    </form>
    @error('csv')
        <p class="field-hint" style="color:#b71c1c;margin-top:0.5rem;">{{ $message }}</p>
    @enderror
</div>
