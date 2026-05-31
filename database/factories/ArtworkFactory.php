<?php

namespace Database\Factories;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artwork>
 */
class ArtworkFactory extends Factory
{
    protected $model = Artwork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'start_date' => fake()->optional()->date(),
            'completed_date' => fake()->optional()->date(),
            'artwork_type' => fake()->optional()->randomElement(['Painting', 'Drawing', 'Sculpture', 'Print']),
            'medium' => fake()->optional()->randomElement(['Oil on canvas', 'Acrylic', 'Watercolor']),
            'height' => fake()->optional()->randomFloat(2, 4, 48),
            'width' => fake()->optional()->randomFloat(2, 4, 48),
            'depth' => fake()->optional()->randomFloat(2, 0, 12),
            'dimension_unit' => 'in',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
