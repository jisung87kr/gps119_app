<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' 행사',
            'description' => fake()->sentence(),
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
            'settings' => null,
            'created_by' => User::factory(),
            // slug/status 는 Project::booted() creating 훅에서 자동 발급
        ];
    }
}
