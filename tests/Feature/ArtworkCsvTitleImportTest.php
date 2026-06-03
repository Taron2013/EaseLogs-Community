<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkCsvTitleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_with_title_column_imports_title(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title', 'completed_date'],
            ['Dragon Painting', '06/05/24'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('dragon.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Dragon Painting', $artwork->title);
        $this->assertSame('2024-06-05', $artwork->completed_date?->format('Y-m-d'));
    }

    public function test_csv_import_with_title_header_casing_imports_title(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['Title', 'completed_date'],
            ['Cased Title', '2024-06-05'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('title-case.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertSame('Cased Title', Artwork::query()->first()?->title);
    }

    public function test_csv_import_with_uppercase_title_header_imports_title(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['TITLE', 'completed_date'],
            ['Upper Title', '2024-06-05'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('title-upper.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertSame('Upper Title', Artwork::query()->first()?->title);
    }

    public function test_csv_import_with_padded_title_header_imports_title(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            [' title ', 'completed_date'],
            ['Padded Header Title', '2024-06-05'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('title-padded.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertSame('Padded Header Title', Artwork::query()->first()?->title);
    }

    public function test_csv_import_with_title_start_and_completed_dates_imports_all_three(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title', 'start_date', 'completed_date'],
            ['Three Fields', '2024-01-01', '2024-06-05'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('three-fields.csv', $csv),
        ])->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Three Fields', $artwork->title);
        $this->assertSame('2024-01-01', $artwork->start_date?->format('Y-m-d'));
        $this->assertSame('2024-06-05', $artwork->completed_date?->format('Y-m-d'));
    }

    public function test_csv_import_with_unknown_columns_still_imports_title(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title', 'completed_date', 'random_column', 'legacy_id'],
            ['With Extras', '06/05/24', 'ignore-me', '999'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('title-extras.csv', $csv),
        ])->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('With Extras', $artwork->title);
        $this->assertSame('2024-06-05', $artwork->completed_date?->format('Y-m-d'));
    }

    public function test_csv_import_with_utf8_bom_on_title_header_imports_title(): void
    {
        $this->signIn();

        $csv = "\xEF\xBB\xBF".$this->buildImportCsv(
            ['title', 'completed_date'],
            ['BOM Title', '06/05/24'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bom-title.csv', $csv),
        ])->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('BOM Title', $artwork->title);
        $this->assertSame('2024-06-05', $artwork->completed_date?->format('Y-m-d'));
    }

    public function test_csv_import_trims_title_value_but_keeps_nonblank_text(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title'],
            ['  Spaced Title  '],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('trim-title.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertSame('Spaced Title', Artwork::query()->first()?->title);
    }

    public function test_csv_import_blank_title_remains_empty(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title', 'medium'],
            ['', 'Watercolor'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('blank-title.csv', $csv),
        ])->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('', $artwork->title);
        $this->assertSame('Watercolor', $artwork->medium);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $row
     */
    private function buildImportCsv(array $header, array $row): string
    {
        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, $header);
        fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
