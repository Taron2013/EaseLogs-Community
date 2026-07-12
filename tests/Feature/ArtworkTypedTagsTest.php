<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Models\User;
use App\Services\ArtworkTagService;
use App\Support\ArtworkTagType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkTypedTagsTest extends TestCase
{
    use RefreshDatabase;

    private ArtworkTagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tagService = app(ArtworkTagService::class);
    }

    public function test_create_form_shows_single_tags_control_without_typed_sections(): void
    {
        $this->signIn();

        $this->get(route('artworks.create'))
            ->assertOk()
            ->assertSee('tag-input-tags', false)
            ->assertDontSee('Style tags', false)
            ->assertDontSee('Subject tags', false)
            ->assertDontSee('General tags', false);
    }

    public function test_create_artwork_with_tag_chips_creates_general_tags(): void
    {
        $user = $this->signIn();

        $this->post(route('artworks.store'), [
            'title' => 'Tagged Work',
            'tags' => ['Cubism', 'Sold'],
        ])->assertRedirect(route('artworks.index'));

        $artwork = Artwork::query()->where('title', 'Tagged Work')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Cubism', 'Sold'], $artwork->tagNamesForType(ArtworkTagType::GENERAL));
        $this->assertDatabaseHas('artwork_tags', [
            'user_id' => $user->id,
            'type' => ArtworkTagType::GENERAL,
            'normalized_name' => 'cubism',
        ]);
    }

    public function test_comma_separated_tags_in_single_control_create_general_tags(): void
    {
        $user = $this->signIn();

        $this->tagService->findOrCreateForUser($user, 'Dog', ArtworkTagType::GENERAL);

        $this->post(route('artworks.store'), [
            'title' => 'Comma Tags',
            'tags' => ['Dog', 'dog', 'Bird'],
        ])->assertRedirect(route('artworks.index'));

        $artwork = Artwork::query()->where('title', 'Comma Tags')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Dog', 'Bird'], $artwork->tagNamesForType(ArtworkTagType::GENERAL));
        $this->assertSame(2, ArtworkTag::query()->where('user_id', $user->id)->count());
    }

    public function test_crafted_style_and_subject_requests_are_rejected(): void
    {
        $this->signIn();

        $this->post(route('artworks.store'), [
            'title' => 'Crafted Typed',
            'style_tags' => ['Cubism'],
        ])->assertSessionHasErrors('style_tags');

        $this->post(route('artworks.store'), [
            'title' => 'Crafted Typed',
            'subject_tags' => ['Landscape'],
        ])->assertSessionHasErrors('subject_tags');

        $this->assertDatabaseMissing('artwork_tags', ['normalized_name' => 'cubism']);
    }

    public function test_edit_replaces_general_tags_and_preserves_legacy_typed_tags(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Preserve Typed']);

        $styleTag = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Cubism',
            'normalized_name' => 'cubism',
            'type' => ArtworkTagType::STYLE,
        ]);
        $generalTag = $this->tagService->createTagForUser($user, 'Sold', ArtworkTagType::GENERAL);
        $artwork->tags()->attach([$styleTag->id, $generalTag->id]);

        $this->put(route('artworks.update', $artwork), [
            'title' => 'Preserve Typed',
            'tags' => ['Gift'],
        ])->assertRedirect(route('artworks.show', $artwork));

        $fresh = $artwork->fresh();
        $this->assertEqualsCanonicalizing(['Gift'], $fresh->tagNamesForType(ArtworkTagType::GENERAL));
        $this->assertEqualsCanonicalizing(['Cubism'], $fresh->tagNamesForType(ArtworkTagType::STYLE));
    }

    public function test_show_and_index_display_single_tags_group(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Display Tags']);

        $style = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Cubism',
            'normalized_name' => 'cubism',
            'type' => ArtworkTagType::STYLE,
        ]);
        $general = $this->tagService->createTagForUser($user, 'Sold', ArtworkTagType::GENERAL);
        $artwork->tags()->attach([$style->id, $general->id]);

        $this->get(route('artworks.show', $artwork))
            ->assertOk()
            ->assertSee('Tags', false)
            ->assertSee('Cubism', false)
            ->assertSee('Sold', false)
            ->assertDontSee('Style', false)
            ->assertDontSee('Subject', false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('Cubism', false)
            ->assertSee('Sold', false);
    }

    public function test_single_tag_filter_matches_any_tag_type(): void
    {
        $user = $this->signIn();
        $tagged = Artwork::factory()->for($user)->create(['title' => 'Style Tagged']);
        Artwork::factory()->for($user)->create(['title' => 'Plain Piece']);

        $styleTag = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Cubism',
            'normalized_name' => 'cubism',
            'type' => ArtworkTagType::STYLE,
        ]);
        $tagged->tags()->attach($styleTag->id);

        $this->get(route('artworks.index', ['tag' => 'Cubism']))
            ->assertOk()
            ->assertSee('Style Tagged', false)
            ->assertDontSee('Plain Piece', false);
    }

    public function test_tag_filter_preserves_sort_and_pagination_query_string(): void
    {
        $user = $this->signIn();

        $tag = $this->tagService->createTagForUser($user, 'SharedTag', ArtworkTagType::GENERAL);

        Artwork::factory()->count(21)->for($user)->sequence(
            fn ($sequence) => ['title' => 'Tagged '.$sequence->index, 'completed_date' => null],
        )->create()->each(function (Artwork $artwork) use ($tag): void {
            $artwork->tags()->attach($tag->id);
        });

        $page1 = $this->get(route('artworks.index', [
            'tag' => 'SharedTag',
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $page1->assertOk();
        $page1->assertSee('tag=SharedTag', false);
        $page1->assertSee('sort=title', false);
        $page1->assertSee('page=2', false);
    }

    public function test_manual_cleanup_reuses_existing_normalized_general_tag(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Cleanup Piece']);

        $dogs = $this->tagService->createTagForUser($user, 'Dogs', ArtworkTagType::GENERAL);
        $dog = $this->tagService->createTagForUser($user, 'Dog', ArtworkTagType::GENERAL);
        $artwork->tags()->attach($dogs->id);

        $this->put(route('artworks.update', $artwork), [
            'title' => 'Cleanup Piece',
            'tags' => ['Dog'],
        ])->assertRedirect(route('artworks.show', $artwork));

        $fresh = $artwork->fresh();
        $this->assertEqualsCanonicalizing(['Dog'], $fresh->tagNamesForType(ArtworkTagType::GENERAL));
        $this->assertTrue($fresh->tags->contains('id', $dog->id));
        $this->assertFalse($fresh->tags->contains('id', $dogs->id));
    }

    public function test_search_matches_tag_names(): void
    {
        $user = $this->signIn();
        $matching = Artwork::factory()->for($user)->create(['title' => 'Hidden Title']);
        Artwork::factory()->for($user)->create(['title' => 'Other Work']);

        $this->tagService->syncCommunityTagsForArtwork($matching, $user, ['UniqueSearchTag']);

        $this->get(route('artworks.index', ['q' => 'UniqueSearchTag']))
            ->assertOk()
            ->assertSee('Hidden Title', false)
            ->assertDontSee('Other Work', false);
    }

    public function test_community_edition_has_no_bulk_update_route(): void
    {
        $this->signIn();

        $this->get('/artworks/bulk-update')->assertNotFound();
    }
}
