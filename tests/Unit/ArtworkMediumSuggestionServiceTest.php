<?php

namespace Tests\Unit;

use App\Models\Artwork;
use App\Services\ArtworkMediumSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkMediumSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_defaults_load_from_json_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'medium-defaults-');
        file_put_contents($path, json_encode(['Encaustic', 'Resin']));

        $service = new ArtworkMediumSuggestionService($path);

        $this->assertSame(['Encaustic', 'Resin'], $service->configDefaults());
    }

    public function test_missing_json_falls_back_to_builtin_defaults(): void
    {
        $service = new ArtworkMediumSuggestionService('/tmp/easelogs-missing-medium-defaults.json');

        $this->assertSame(ArtworkMediumSuggestionService::BUILTIN_DEFAULTS, $service->configDefaults());
    }

    public function test_invalid_json_falls_back_to_builtin_defaults(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'medium-defaults-');
        file_put_contents($path, 'not-json');

        $service = new ArtworkMediumSuggestionService($path);

        $this->assertSame(ArtworkMediumSuggestionService::BUILTIN_DEFAULTS, $service->configDefaults());
    }

    public function test_filter_options_include_defaults_and_existing_artwork_mediums(): void
    {
        $user = $this->signIn();

        Artwork::factory()->for($user)->create(['medium' => 'Oil on linen']);

        $service = new ArtworkMediumSuggestionService('/tmp/easelogs-missing-medium-defaults.json');
        $options = $service->filterOptions($user);

        $this->assertContains('Oil on linen', $options);
        $this->assertContains('Acrylic', $options);
    }
}
