<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Services\ArtworkCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkTypeRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_forms_work_without_artwork_type(): void
    {
        $this->signIn();
        $artwork = Artwork::factory()->create(['medium' => 'Oil']);

        $this->get('/artworks/create')
            ->assertOk()
            ->assertDontSee('name="artwork_type"', false)
            ->assertSee('name="medium"', false);

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertDontSee('name="artwork_type"', false)
            ->assertSee('name="medium"', false);
    }

    public function test_store_and_update_persist_medium_without_artwork_type(): void
    {
        $this->signIn();

        $this->post('/artworks', [
            'title' => 'Medium Only Piece',
            'medium' => 'Watercolor',
        ])->assertRedirect('/artworks');

        $artwork = Artwork::query()->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Watercolor', $artwork->medium);

        $this->put(route('artworks.update', $artwork), [
            'title' => 'Medium Only Piece',
            'medium' => 'Ink',
        ])->assertRedirect(route('artworks.show', $artwork));

        $this->assertSame('Ink', $artwork->fresh()->medium);
    }

    public function test_index_and_show_pages_do_not_reference_artwork_type(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'No Type Work',
            'medium' => 'Acrylic',
        ]);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertDontSee('filter_artwork_type', false)
            ->assertDontSee('Artwork type', false)
            ->assertSee('No Type Work', false);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertDontSee('Artwork type', false)
            ->assertSee('Acrylic', false);
    }

    public function test_csv_export_excludes_artwork_type_column(): void
    {
        $this->signIn();
        Artwork::factory()->create(['title' => 'Export Without Type', 'medium' => 'Oil']);

        $content = $this->get(route('artworks.export.csv'))->streamedContent();

        $header = trim(explode("\n", trim($content))[0]);
        $this->assertSame(implode(',', ArtworkCsvService::COLUMNS), $header);
        $this->assertStringNotContainsString('artwork_type', $header);
    }

    public function test_csv_import_ignores_legacy_artwork_type_column(): void
    {
        $this->signIn();

        $csv = "title,medium,artwork_type\nLegacy Column Work,Graphite,Painting\n";
        $file = UploadedFile::fake()->createWithContent('legacy-type.csv', $csv);

        $this->post(route('artworks.import.csv'), ['csv' => $file])
            ->assertRedirect(route('artworks.import-export'));

        $artwork = Artwork::query()->where('title', 'Legacy Column Work')->first();
        $this->assertNotNull($artwork);
        $this->assertSame('Graphite', $artwork->medium);
    }
}
