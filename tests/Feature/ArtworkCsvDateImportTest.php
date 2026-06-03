<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Services\ArtworkCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArtworkCsvDateImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedDateFormatsProvider(): array
    {
        return [
            'iso yyyy-mm-dd' => ['2026-05-31', '2026-05-31'],
            'us mm/dd/yyyy padded' => ['05/31/2026', '2026-05-31'],
            'us m/d/yyyy' => ['5/31/2026', '2026-05-31'],
            'us mm-dd-yyyy' => ['05-31-2026', '2026-05-31'],
            'us m-d-yyyy' => ['5-31-2026', '2026-05-31'],
            'iso yyyy/mm/dd' => ['2026/05/31', '2026-05-31'],
            'iso yyyy.mm.dd' => ['2026.05.31', '2026-05-31'],
            'written month day year comma' => ['May 31, 2026', '2026-05-31'],
            'written month day year' => ['May 31 2026', '2026-05-31'],
            'written day month year' => ['31 May 2026', '2026-05-31'],
            'date time space' => ['2026-05-31 14:30:00', '2026-05-31'],
            'date time t' => ['2026-05-31T14:30:00', '2026-05-31'],
            'date time zulu' => ['2026-05-31T14:30:00Z', '2026-05-31'],
            'date time offset' => ['2026-05-31T14:30:00-04:00', '2026-05-31'],
        ];
    }

    #[DataProvider('acceptedDateFormatsProvider')]
    public function test_csv_import_accepts_common_date_formats(string $input, string $expected): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(['title', 'start_date'], ['Format Check', $input]);

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('dates.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame($expected, $artwork->start_date?->format('Y-m-d'));
    }

    public function test_csv_import_blank_date_is_null(): void
    {
        $this->signIn();

        $csv = "title,start_date,completed_date\nBlank Dates,,\n";

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('blank-dates.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertNull($artwork->start_date);
        $this->assertNull($artwork->completed_date);
    }

    public function test_csv_import_rejects_invalid_dates(): void
    {
        $this->signIn();

        $csv = "title,start_date\nBad Date,not-a-date\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bad-dates.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Row 2: Invalid start_date', session('error'));
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_csv_import_rejects_impossible_calendar_dates(): void
    {
        $this->signIn();

        $csv = "title,start_date\nBad Date,2026-02-30\n";

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('impossible-date.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_csv_import_us_slash_date_uses_mm_dd_yyyy(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2026-03-04', $service->normalizeDateForImport('03/04/2026'));
    }

    public function test_csv_import_accepts_us_two_digit_year_slash_date_padded(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2024-06-05', $service->normalizeDateForImport('06/05/24'));
    }

    public function test_csv_import_accepts_us_two_digit_year_slash_date_unpadded(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2024-06-05', $service->normalizeDateForImport('6/5/24'));
    }

    public function test_csv_import_accepts_us_two_digit_year_dash_date_padded(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2024-06-05', $service->normalizeDateForImport('06-05-24'));
    }

    public function test_csv_import_accepts_us_two_digit_year_dash_date_unpadded(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2024-06-05', $service->normalizeDateForImport('6-5-24'));
    }

    public function test_csv_import_two_digit_year_70_99_maps_to_1970s_and_1990s(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('1999-12-31', $service->normalizeDateForImport('12/31/99'));
    }

    public function test_csv_import_two_digit_year_00_69_maps_to_2000s(): void
    {
        $service = new ArtworkCsvService;

        $this->assertSame('2000-01-01', $service->normalizeDateForImport('01/01/00'));
    }

    public function test_csv_import_rejects_invalid_two_digit_year_date(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(['title', 'start_date'], ['Bad Date', '02/30/24']);

        $response = $this->from(route('artworks.import-export'))->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('bad-two-digit.csv', $csv),
        ]);

        $response->assertRedirect(route('artworks.import-export'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Row 2: Invalid start_date', session('error'));
        $this->assertDatabaseCount('artworks', 0);

        $service = new ArtworkCsvService;
        $this->assertNull($service->normalizeDateForImport('02/30/24'));
    }

    public function test_csv_import_accepts_common_two_digit_year_rows_via_http(): void
    {
        $this->signIn();

        $csv = $this->buildImportCsv(
            ['title', 'start_date'],
            ['Summer Series', '06/10/24'],
        );

        $this->post(route('artworks.import.csv'), [
            'csv' => UploadedFile::fake()->createWithContent('two-digit-rows.csv', $csv),
        ])->assertRedirect(route('artworks.import-export'))->assertSessionHas('success');

        $this->assertSame('2024-06-10', Artwork::query()->first()?->start_date?->format('Y-m-d'));
    }

    public function test_normalize_date_for_import_returns_null_for_blank(): void
    {
        $service = new ArtworkCsvService;

        $this->assertNull($service->normalizeDateForImport(null));
        $this->assertNull($service->normalizeDateForImport(''));
        $this->assertNull($service->normalizeDateForImport('   '));
    }

    public function test_csv_export_still_emits_yyyy_mm_dd(): void
    {
        $this->signIn();
        Artwork::factory()->create([
            'title' => 'Export Date',
            'start_date' => '2026-05-31',
            'completed_date' => '2026-06-01',
        ]);

        $response = $this->get(route('artworks.export.csv'));
        $content = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($content))));

        $this->assertStringContainsString('2026-05-31', $lines[1]);
        $this->assertStringContainsString('2026-06-01', $lines[1]);
        $this->assertStringNotContainsString('05/31/2026', $content);
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
