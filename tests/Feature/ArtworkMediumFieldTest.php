<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Services\ArtworkMediumSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkMediumFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_includes_medium_datalist_suggestions(): void
    {
        $this->signIn();

        $this->get('/artworks/create')
            ->assertOk()
            ->assertSee('list="artwork-medium-suggestions"', false)
            ->assertSee('value="Acrylic"', false)
            ->assertSee('value="Watercolor"', false);
    }

    public function test_store_persists_custom_medium_not_in_defaults(): void
    {
        $this->signIn();

        $this->post('/artworks', [
            'title' => 'Custom Medium Piece',
            'medium' => 'Egg Tempera on Panel',
        ])->assertRedirect('/artworks');

        $this->assertSame('Egg Tempera on Panel', Artwork::query()->value('medium'));
    }

    public function test_medium_filter_still_matches_exact_value(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['title' => 'Ink Work', 'medium' => 'Ink']);
        Artwork::factory()->for($user)->create(['title' => 'Oil Work', 'medium' => 'Oil on linen']);

        $this->get(route('artworks.index', ['medium' => 'Ink']))
            ->assertOk()
            ->assertSee('Ink Work', false)
            ->assertDontSee('Oil Work', false);
    }

    public function test_csv_import_accepts_custom_medium(): void
    {
        $this->signIn();

        $csv = "title,medium\nImported Piece,Custom Resin Mix\n";
        $file = UploadedFile::fake()->createWithContent('artworks.csv', $csv);

        $this->post(route('artworks.import.csv'), ['csv' => $file])
            ->assertRedirect(route('artworks.import-export'));

        $this->assertDatabaseHas('artworks', [
            'title' => 'Imported Piece',
            'medium' => 'Custom Resin Mix',
        ]);
    }

    public function test_project_json_defaults_are_used_when_present(): void
    {
        $defaults = app(ArtworkMediumSuggestionService::class)->configDefaults();

        $this->assertContains('Acrylic', $defaults);
        $this->assertContains('Tempera', $defaults);
    }
}
