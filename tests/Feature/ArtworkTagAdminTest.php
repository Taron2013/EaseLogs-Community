<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Models\User;
use App\Services\ArtworkTagService;
use App\Support\ArtworkTagType;
use App\Support\EaseLogsEdition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ArtworkTagAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['easelogs.edition' => 'Community Edition']);
    }

    public function test_admin_page_lists_tags_in_flat_table(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $tag = $this->createTag($user, 'Sold', ArtworkTagType::GENERAL);
        $artwork->tags()->attach($tag->id);
        $this->createTag($user, 'Unused', ArtworkTagType::GENERAL);

        $this->get(route('settings.artwork-tags.index'))
            ->assertOk()
            ->assertSee('Settings / Artwork Tags', false)
            ->assertSee('Sold', false)
            ->assertSee('Unused', false)
            ->assertSee('1 artwork', false)
            ->assertSee('0 artworks', false)
            ->assertDontSee('Style tags', false)
            ->assertDontSee('Subject tags', false)
            ->assertDontSee('name="type"', false);
    }

    public function test_create_tag_stores_general_type(): void
    {
        $user = $this->signIn();

        $this->post(route('settings.artwork-tags.store'), [
            'name' => 'Contest Entry',
        ])->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('artwork_tags', [
            'user_id' => $user->id,
            'name' => 'Contest Entry',
            'normalized_name' => 'contest entry',
            'type' => ArtworkTagType::GENERAL,
        ]);
    }

    public function test_crafted_type_field_on_create_is_rejected(): void
    {
        $this->signIn();

        $this->from(route('settings.artwork-tags.index'))
            ->post(route('settings.artwork-tags.store'), [
                'name' => 'Sneaky Style',
                'type' => ArtworkTagType::STYLE,
            ])
            ->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('artwork_tags', ['normalized_name' => 'sneaky style']);
    }

    public function test_rejects_duplicate_general_tag(): void
    {
        $user = $this->signIn();
        $this->createTag($user, 'Cubism', ArtworkTagType::GENERAL);

        $this->from(route('settings.artwork-tags.index'))
            ->post(route('settings.artwork-tags.store'), [
                'name' => ' cubism ',
            ])
            ->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ArtworkTag::query()->where('user_id', $user->id)->count());
    }

    public function test_edit_tag_name(): void
    {
        $user = $this->signIn();
        $tag = $this->createTag($user, 'Old Name', ArtworkTagType::GENERAL);

        $this->patch(route('settings.artwork-tags.update', $tag), [
            'name' => 'New Name',
        ])->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('artwork_tags', [
            'id' => $tag->id,
            'name' => 'New Name',
            'normalized_name' => 'new name',
            'type' => ArtworkTagType::GENERAL,
        ]);
    }

    public function test_cannot_reclassify_tag_type_from_settings(): void
    {
        $user = $this->signIn();
        $tag = $this->createTag($user, 'Bird', ArtworkTagType::GENERAL);

        $this->from(route('settings.artwork-tags.index'))
            ->patch(route('settings.artwork-tags.update', $tag), [
                'name' => 'Bird',
                'type' => ArtworkTagType::STYLE,
            ])
            ->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseHas('artwork_tags', [
            'id' => $tag->id,
            'type' => ArtworkTagType::GENERAL,
        ]);
    }

    public function test_prevents_unsafe_delete_of_tag_assigned_to_artwork(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        $tag = $this->createTag($user, 'In Use', ArtworkTagType::GENERAL);
        $artwork->tags()->attach($tag->id);

        $this->from(route('settings.artwork-tags.index'))
            ->delete(route('settings.artwork-tags.destroy', $tag))
            ->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('artwork_tags', ['id' => $tag->id]);
    }

    public function test_deletes_unused_tag(): void
    {
        $user = $this->signIn();
        $tag = $this->createTag($user, 'Unused', ArtworkTagType::GENERAL);

        $this->delete(route('settings.artwork-tags.destroy', $tag))
            ->assertRedirect(route('settings.artwork-tags.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('artwork_tags', ['id' => $tag->id]);
    }

    public function test_new_tag_appears_in_artwork_create_form_and_filter(): void
    {
        $this->signIn();

        $this->post(route('settings.artwork-tags.store'), [
            'name' => 'Photorealism',
        ])->assertRedirect(route('settings.artwork-tags.index'));

        $this->get(route('artworks.create'))
            ->assertOk()
            ->assertSee('Photorealism', false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('Photorealism', false)
            ->assertSee('filter_tag', false);
    }

    public function test_cannot_manage_another_users_tag(): void
    {
        $this->signIn();
        $otherUser = User::factory()->create();
        $tag = $this->createTag($otherUser, 'Foreign', ArtworkTagType::GENERAL);

        $this->patch(route('settings.artwork-tags.update', $tag), [
            'name' => 'Hijacked',
        ])->assertNotFound();

        $this->delete(route('settings.artwork-tags.destroy', $tag))
            ->assertNotFound();
    }

    public function test_community_edition_has_no_merge_route(): void
    {
        $this->signIn();

        $this->assertFalse(EaseLogsEdition::supportsArtworkTagMerge());

        $this->get(route('settings.artwork-tags.index'))
            ->assertOk()
            ->assertDontSee('Merge tags', false);

        $this->assertFalse(Route::has('settings.artwork-tags.merge'));

        $this->post('/settings/artwork-tags/merge', [
            'source_tag_id' => 1,
            'destination_tag_id' => 2,
            'confirm_merge' => 1,
        ])->assertStatus(405);
    }

    public function test_tag_settings_linked_from_profile_and_navigation(): void
    {
        $this->signIn();

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Artwork tags', false)
            ->assertSee(route('settings.artwork-tags.index'), false);

        $this->get(route('artworks.index'))
            ->assertOk()
            ->assertSee('Settings', false)
            ->assertSee(route('settings.artwork-tags.index'), false);
    }

    private function createTag(User $user, string $name, string $type): ArtworkTag
    {
        return app(ArtworkTagService::class)->createTagForUser($user, $name, $type);
    }
}
