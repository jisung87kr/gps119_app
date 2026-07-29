<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationPing>
 */
class LocationPingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'latitude' => fake()->latitude(37.4, 37.7),
            'longitude' => fake()->longitude(126.8, 127.2),
            'accuracy' => fake()->numberBetween(3, 50),
            'heading' => fake()->numberBetween(0, 359),
            'speed' => fake()->numberBetween(0, 20),
            'recorded_at' => now(),
        ];
    }
}
