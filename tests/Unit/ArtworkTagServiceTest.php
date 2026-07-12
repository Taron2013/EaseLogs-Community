<?php

namespace Tests\Unit;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Models\User;
use App\Services\ArtworkTagService;
use App\Support\ArtworkTagType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkTagServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArtworkTagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArtworkTagService::class);
    }

    public function test_create_tag_for_user_stores_general_type_in_community_edition(): void
    {
        $user = User::factory()->create();

        $tag = $this->service->createTagForUser($user, 'Sold', ArtworkTagType::STYLE);

        $this->assertSame(ArtworkTagType::GENERAL, $tag->type);
    }

    public function test_prevents_duplicate_general_tags_within_same_user(): void
    {
        $user = User::factory()->create();

        $first = $this->service->findOrCreateForUser($user, 'Cubism', ArtworkTagType::GENERAL);
        $second = $this->service->findOrCreateForUser($user, ' cubism ', ArtworkTagType::GENERAL);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ArtworkTag::query()->where('user_id', $user->id)->count());
    }

    public function test_community_tags_from_request_input_parses_tag_array(): void
    {
        $parsed = $this->service->communityTagsFromRequestInput([
            'tags' => ['Dog', 'dog', ' Bird ', ''],
            'style_tags' => ['Ignored'],
        ]);

        $this->assertEquals(['Dog', 'Bird'], $parsed);
    }

    public function test_sync_community_tags_preserves_existing_style_and_subject_assignments(): void
    {
        $user = User::factory()->create();
        $artwork = Artwork::factory()->for($user)->create();

        $style = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Cubism',
            'normalized_name' => 'cubism',
            'type' => ArtworkTagType::STYLE,
        ]);
        $subject = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Landscape',
            'normalized_name' => 'landscape',
            'type' => ArtworkTagType::SUBJECT,
        ]);
        $oldGeneral = $this->service->createTagForUser($user, 'Sold', ArtworkTagType::GENERAL);
        $artwork->tags()->attach([$style->id, $subject->id, $oldGeneral->id]);

        $this->service->syncCommunityTagsForArtwork($artwork, $user, ['Gift']);

        $fresh = $artwork->fresh();
        $this->assertEqualsCanonicalizing(['Cubism'], $fresh->tagNamesForType(ArtworkTagType::STYLE));
        $this->assertEqualsCanonicalizing(['Landscape'], $fresh->tagNamesForType(ArtworkTagType::SUBJECT));
        $this->assertEqualsCanonicalizing(['Gift'], $fresh->tagNamesForType(ArtworkTagType::GENERAL));
    }

    public function test_update_tag_for_user_preserves_legacy_type_in_community_edition(): void
    {
        $user = User::factory()->create();
        $tag = ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy Style',
            'normalized_name' => 'legacy style',
            'type' => ArtworkTagType::STYLE,
        ]);

        $updated = $this->service->updateTagForUser($tag, 'Renamed Style', ArtworkTagType::GENERAL);

        $this->assertSame(ArtworkTagType::STYLE, $updated->type);
        $this->assertSame('Renamed Style', $updated->name);
    }
}
