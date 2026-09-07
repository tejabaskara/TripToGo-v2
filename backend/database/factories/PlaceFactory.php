<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'=>fake()->word(),
            'description'=>fake()->paragraph(),
            'latitude'=>fake()->latitude(),
            'longitude'=>fake()->longitude(),
            'address'=>fake()->address(),
            'category'=>fake()->randomElement(['beach', 'mountain', 'museum']),
            'image'=>fake()->url(),
        ];
    }
}
