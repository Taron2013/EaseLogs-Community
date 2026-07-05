<?php

namespace Tests\Feature;

use App\Models\Artwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_artwork_form_can_save_tags(): void
    {
        $user = $this->signIn();

        $this->post(route('artworks.store'), [
            'title' => 'Tagged Create',
            'tags' => 'Cubism, Animal',
        ])->assertRedirect(route('artworks.index'));

        $artwork = Artwork::query()->where('title', 'Tagged Create')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Cubism', 'Animal'], $artwork->tags->pluck('name')->all());
        $this->assertDatabaseHas('artwork_tags', ['user_id' => $user->id, 'normalized_name' => 'cubism']);
    }
}
