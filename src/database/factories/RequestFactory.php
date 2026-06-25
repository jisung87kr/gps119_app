<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Request>
 */
class RequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'latitude' => fake()->latitude(37.4, 37.7),
            'longitude' => fake()->longitude(126.8, 127.2),
            'address' => fake()->streetAddress(),
            'description' => fake()->sentence(),
            'status' => RequestStatus::PENDING,
            'priority' => RequestPriority::MEDIUM,
            'contact_phone' => '01012345678',
            'requested_at' => now(),
        ];
    }
}
