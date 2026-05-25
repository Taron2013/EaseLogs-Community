<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalArtReproductionPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_title_is_optional_on_create(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => '',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 0,
            'medium' => 'Acrylic',
            'surface' => 'Canvas',
            'width' => 12,
            'height' => 16,
            'dimension_unit' => 'in',
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => '',
            'status' => 'in_progress',
            'professional_art_reproduction_photo' => false,
        ]);
    }

    public function test_create_page_hides_professional_art_reproduction_photo_when_unfinished(): void
    {
        User::factory()->create();

        $response = $this->get('/artworks/create');

        $response->assertStatus(200);
        $response->assertSee('professional-photo-field');
        $response->assertSee('display:none;');
    }

    public function test_edit_page_hides_professional_art_reproduction_photo_when_status_in_progress(): void
    {
        $user = User::factory()->create();

        $artwork = Artwork::create([
            'user_id' => $user->id,
            'inventory_code' => 'TEST-001',
            'sku' => 'TEST-001',
            'title' => 'Test',
            'status' => 'in_progress',
        ]);

        $response = $this->get(route('artworks.edit', $artwork));

        $response->assertStatus(200);
        $response->assertSee('id="professional-art-reproduction-photo-field"', false);
        $response->assertSee('style="display:none;"', false);
    }

    public function test_complete_artwork_can_save_professional_art_reproduction_photo_true(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => 'Complete Work',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 1,
            'professional_art_reproduction_photo' => 1,
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => 'Complete Work',
            'status' => 'in_inventory',
            'professional_art_reproduction_photo' => true,
        ]);
    }

    public function test_incomplete_artwork_forces_professional_art_reproduction_photo_false(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => 'Incomplete Work',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 0,
            'professional_art_reproduction_photo' => 1,
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => 'Incomplete Work',
            'status' => 'in_progress',
            'professional_art_reproduction_photo' => false,
        ]);
    }

    public function test_feature_disabled_ignores_professional_reproduction_photo_values(): void
    {
        config(['artdoc.enable_professional_reproduction_photo_tracking' => false]);

        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => 'Disabled Feature',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 1,
            'professional_art_reproduction_photo' => 1,
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => 'Disabled Feature',
            'status' => 'in_inventory',
            'professional_art_reproduction_photo' => false,
        ]);
    }

    public function test_create_with_finished_painting_defaults_finished_date_to_today(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => 'Finished Today',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 1,
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => 'Finished Today',
            'status' => 'in_inventory',
        ]);

        $artwork = Artwork::where('title', 'Finished Today')->first();
        $this->assertNotNull($artwork);
        $this->assertEquals(now()->format('Y-m-d'), $artwork->finished_date->format('Y-m-d'));
    }

    public function test_unchecked_finished_painting_ignores_finished_date_on_create(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/artworks', [
            'title' => 'Should Be Unfinished',
            'inventory_code' => '',
            'sku' => '',
            'started_date' => now()->format('Y-m-d'),
            'finished_painting' => 0,
            'finished_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect('/artworks');
        $this->assertDatabaseHas('artworks', [
            'title' => 'Should Be Unfinished',
            'status' => 'in_progress',
            'finished_date' => null,
        ]);
    }

    public function test_editing_artwork_back_to_in_progress_clears_completion_fields(): void
    {
        $user = User::factory()->create();

        $artwork = Artwork::create([
            'user_id' => $user->id,
            'inventory_code' => 'EDIT-001',
            'sku' => 'EDIT-001',
            'title' => 'Completed Work',
            'status' => 'in_inventory',
            'started_date' => now()->subDays(3)->format('Y-m-d'),
            'finished_date' => now()->format('Y-m-d'),
            'finished_date_is_estimated' => true,
            'professional_art_reproduction_photo' => true,
        ]);

        $response = $this->put(route('artworks.update', $artwork), [
            'title' => 'Completed Work',
            'inventory_code' => 'EDIT-001',
            'sku' => 'EDIT-001',
            'status' => 'in_progress',
            'started_date' => now()->subDays(3)->format('Y-m-d'),
            'finished_date' => now()->format('Y-m-d'),
            'finished_date_is_estimated' => 1,
            'professional_art_reproduction_photo' => 1,
        ]);

        $response->assertRedirect(route('artworks.show', $artwork));

        $artwork->refresh();
        $this->assertSame('in_progress', $artwork->status);
        $this->assertNull($artwork->finished_date);
        $this->assertFalse($artwork->finished_date_is_estimated);
        $this->assertFalse($artwork->professional_art_reproduction_photo);

        $response = $this->get(route('artworks.edit', $artwork));
        $response->assertStatus(200);
        $response->assertSee('id="finished-date-field"', false);
        $response->assertSee('id="finished-date-is-estimated-field"', false);
        $response->assertSee('id="professional-art-reproduction-photo-field"', false);
        $response->assertSee('style="display:none;"', false);
    }
}
