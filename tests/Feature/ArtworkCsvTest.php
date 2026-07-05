<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Services\ArtworkCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_artworks_index_does_not_show_csv_import_form(): void
    {
        $this->signIn();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertDontSee('name="csv"', false);
        $response->assertDontSee(route('artworks.import.csv'), false);
        $response->assertDontSee('Export CSV', false);
    }

    public function test_import_export_page_shows_csv_controls(): void
    {
        $this->signIn();

        $response = $this->get(route('artworks.import-export'));

        $response->assertOk();
        $response->assertSee('Import / Export', false);
        $response->assertSee('Export CSV', false);
        $response->assertSee('Import CSV', false);
        $response->assertSee('Metadata only. Photos are not included', false);
        $response->assertSee(route('artworks.export.csv'), false);
        $response->assertSee(route('artworks.import.csv'), false);
    }

    public function test_csv_export_downloads_successfully(): void
    {
        $this->signIn();
        Artwork::factory()->create(['title' => 'Export Me']);

        $response = $this->get(route('artworks.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('easelogs-artworks-', (string) $response->headers->get('content-disposition'));
    }

    public function test_csv_export_contains_only_approved_fields(): void
    {
        $this->signIn();
        Artwork::factory()->create([
            'title' => 'Field Check',
            'start_date' => '2026-01-05',
            'completed_date' => '2026-02-01',
            'medium' => 'Oil',
            'height' => 10,
            'width' => 8,
            'depth' => 1,
            'dimension_unit' => 'in',
            'notes' => 'Exported note',
        ]);

        $response = $this->get(route('artworks.export.csv'));
        $content = $response->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($content))));
        $this->assertSame(implode(',', ArtworkCsvService::COLUMNS), $lines[0]);
        $this->assertStringContainsString('Field Check', $lines[1]);
        $this->assertStringNotContainsString('user_id', $content);
        $this->assertStringNotContainsString('photo', strtolower($content));
        $this->assertStringNotContainsString('inventory_code', $content);
        $this->assertStringNotContainsString('sale_price', $content);
        $this->assertStringNotContainsString('created_at', $content);
    }

    public function test_csv_import_creates_records(): void
    {
        $this->signIn();

        $csv = implode("\n", [
            implode(',', ArtworkCsvService::COLUMNS),
            'Imported Work,2026-03-01,2026-03-15,Graphite,11,14,,in,From CSV',
        ]);

        $response = $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('artworks', [
            'title' => 'Imported Work',
            'medium' => 'Graphite',
            'notes' => 'From CSV',
        ]);

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('2026-03-01', $artwork->start_date?->format('Y-m-d'));
        $this->assertSame('2026-03-15', $artwork->completed_date?->format('Y-m-d'));
        $this->assertDatabaseCount('artwork_photos', 0);
    }

    public function test_csv_import_with_only_title_column(): void
    {
        $this->signIn();

        $csv = "title\nTitle Only Work\n";

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('title-only.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Title Only Work', $artwork->title);
        $this->assertNull($artwork->start_date);
        $this->assertNull($artwork->medium);
        $this->assertSame('in', $artwork->dimension_unit);
        $this->assertDatabaseCount('artwork_photos', 0);
    }

    public function test_csv_import_with_title_and_start_date_only(): void
    {
        $this->signIn();

        $csv = "title,start_date\nDated Work,2026-04-10\n";

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('partial.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Dated Work', $artwork->title);
        $this->assertSame('2026-04-10', $artwork->start_date?->format('Y-m-d'));
        $this->assertNull($artwork->completed_date);
    }

    public function test_csv_import_ignores_unknown_columns(): void
    {
        $this->signIn();

        $csv = "title,start_date,random_column,legacy_id\nIgnored Extras,2026-05-01,foo,999\n";

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('extras.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $this->assertDatabaseHas('artworks', [
            'title' => 'Ignored Extras',
        ]);

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('2026-05-01', $artwork->start_date?->format('Y-m-d'));
        $this->assertDatabaseCount('artwork_photos', 0);
    }

    public function test_csv_import_subset_with_unknown_columns_maps_only_approved_fields(): void
    {
        $this->signIn();

        $csv = "title,medium,legacy_id\nSubset Work,Ink,should-not-save\n";

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('subset-extras.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Subset Work', $artwork->title);
        $this->assertSame('Ink', $artwork->medium);
        $this->assertNull($artwork->notes);
        $this->assertNull($artwork->height);
    }

    public function test_csv_import_rejects_disallowed_columns(): void
    {
        $this->signIn();

        $csv = "title,inventory_code\nWork,ART-001\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bad-columns.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('inventory_code', session('error'));
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_csv_import_without_approved_columns_fails(): void
    {
        $this->signIn();

        $csv = "random_column,legacy_id\nfoo,bar\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('no-approved.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_csv_import_rejects_photo_columns(): void
    {
        $this->signIn();

        $csv = "title,photo_path\nWork,/fake/photo.jpg\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('photo-column.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('artworks', 0);
        $this->assertDatabaseCount('artwork_photos', 0);
    }

    public function test_csv_import_rejects_malformed_dates(): void
    {
        $this->signIn();

        $csv = implode("\n", [
            'title,start_date',
            'Bad Date,not-a-date',
        ]);

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bad-dates.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('start_date', session('error'));
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_imported_records_appear_on_artwork_index(): void
    {
        $this->signIn();

        $csv = implode("\n", [
            'title,medium',
            'Index Visible,Watercolor',
        ]);

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('index.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'));

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertSee('Index Visible');
        $response->assertSee('Watercolor');
    }
}
