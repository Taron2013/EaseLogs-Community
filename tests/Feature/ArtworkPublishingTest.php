<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Services\ArtworkCsvService;
use App\Services\ArtworkPublishingProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_shows_publishing_section(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $this->get(route('artworks.edit', $artwork))
            ->assertOk()
            ->assertSee('id="publishing"', false)
            ->assertSee('Save publishing copy', false)
            ->assertSee('Private notes (studio only)', false);
    }

    public function test_publishing_profile_can_be_created_and_updated(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $this->patch(route('artworks.publishing.update', $artwork), [
            'short_description' => 'A vivid harbor scene.',
            'product_description' => 'Oil on canvas depicting sunset light.',
        ])->assertRedirect(route('artworks.edit', $artwork).'#publishing');

        $artwork->refresh();
        $this->assertSame('A vivid harbor scene.', $artwork->publishingProfile?->short_description);
        $this->assertSame('Oil on canvas depicting sunset light.', $artwork->publishingProfile?->product_description);

        $this->patch(route('artworks.publishing.update', $artwork), [
            'short_description' => 'Updated short copy.',
            'product_description' => 'Updated product copy.',
            'story_inspiration' => 'Inspired by coastal walks.',
            'materials_process' => 'Layered oils on linen.',
        ])->assertRedirect();

        $artwork->refresh();
        $this->assertSame('Updated short copy.', $artwork->publishingProfile?->short_description);
        $this->assertSame('Inspired by coastal walks.', $artwork->publishingProfile?->story_inspiration);
    }

    public function test_show_page_displays_publishing_with_copy_buttons(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        app(ArtworkPublishingProfileService::class)->syncForArtwork($artwork, [
            'product_description' => 'Gallery-ready landscape painting.',
        ]);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertSee('Publishing', false)
            ->assertSee('Gallery-ready landscape painting.', false)
            ->assertSee('publishing-copy-btn', false)
            ->assertSee('Private notes (studio only)', false);
    }

    public function test_private_notes_remain_separate_from_publishing(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'notes' => 'Private studio reminder only.',
        ]);

        app(ArtworkPublishingProfileService::class)->syncForArtwork($artwork, [
            'product_description' => 'Public listing copy.',
        ]);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertSee('Private studio reminder only.', false)
            ->assertSee('Public listing copy.', false);

        $this->patch(route('artworks.publishing.update', $artwork), [
            'product_description' => 'Changed public copy.',
        ]);

        $artwork->refresh();
        $this->assertSame('Private studio reminder only.', $artwork->notes);
        $this->assertSame('Changed public copy.', $artwork->publishingProfile?->product_description);
    }

    public function test_index_search_matches_publishing_profile_fields(): void
    {
        $user = $this->signIn();

        $matching = Artwork::factory()->for($user)->create(['title' => 'Harbor Piece']);
        Artwork::factory()->for($user)->create(['title' => 'Mountain Piece']);

        app(ArtworkPublishingProfileService::class)->syncForArtwork($matching, [
            'story_inspiration' => 'Unique lighthouse narrative phrase',
        ]);

        $this->get(route('artworks.index', ['q' => 'lighthouse narrative']))
            ->assertOk()
            ->assertSee('Harbor Piece', false)
            ->assertDontSee('Mountain Piece', false);
    }

    public function test_csv_export_does_not_include_publishing_columns(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Export Me']);

        app(ArtworkPublishingProfileService::class)->syncForArtwork($artwork, [
            'product_description' => 'Should not appear in metadata CSV.',
        ]);

        $content = $this->get(route('artworks.export.csv'))->streamedContent();
        $header = strtok($content, "\n");

        $this->assertSame(implode(',', ArtworkCsvService::COLUMNS), $header);
        $this->assertStringNotContainsString('product_description', $content);
        $this->assertStringNotContainsString('Should not appear in metadata CSV.', $content);
    }
}
