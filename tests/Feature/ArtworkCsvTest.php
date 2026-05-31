<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use App\Services\ArtworkCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_artworks_index_does_not_show_csv_import_form(): void
    {
        User::factory()->create();

        $response = $this->get(route('artworks.index'));

        $response->assertOk();
        $response->assertDontSee('name="csv"', false);
        $response->assertDontSee(route('artworks.import.csv'), false);
        $response->assertDontSee('Export CSV', false);
    }

    public function test_import_export_page_shows_csv_controls(): void
    {
        User::factory()->create();

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
        User::factory()->create();
        Artwork::factory()->create(['title' => 'Export Me']);

        $response = $this->get(route('artworks.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('easelogs-artworks-', (string) $response->headers->get('content-disposition'));
    }

    public function test_csv_export_contains_only_approved_fields(): void
    {
        User::factory()->create();
        Artwork::factory()->create([
            'title' => 'Field Check',
            'start_date' => '2026-01-05',
            'completed_date' => '2026-02-01',
            'artwork_type' => 'Painting',
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
        User::factory()->create();

        $csv = implode("\n", [
            implode(',', ArtworkCsvService::COLUMNS),
            'Imported Work,2026-03-01,2026-03-15,Drawing,Graphite,11,14,,in,From CSV',
        ]);

        $response = $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('artworks', [
            'title' => 'Imported Work',
            'artwork_type' => 'Drawing',
            'medium' => 'Graphite',
            'notes' => 'From CSV',
        ]);

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('2026-03-01', $artwork->start_date?->format('Y-m-d'));
        $this->assertSame('2026-03-15', $artwork->completed_date?->format('Y-m-d'));
    }

    public function test_csv_import_rejects_invalid_columns(): void
    {
        User::factory()->create();

        $csv = "title,inventory_code\nWork,ART-001\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bad-columns.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('inventory_code', session('error'));
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_csv_import_rejects_photo_columns(): void
    {
        User::factory()->create();

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
        User::factory()->create();

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

    public function test_csv_import_without_user_redirects_to_import_export_with_message(): void
    {
        $csv = implode("\n", [
            'title',
            'No User Work',
        ]);

        $response = $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('db:seed', session('error'));
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_imported_records_appear_on_artwork_index(): void
    {
        User::factory()->create();

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
