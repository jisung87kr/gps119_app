<?php

namespace Database\Factories;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => EventRole::PARTICIPANT,
            'status' => ParticipantStatus::ACTIVE,
            'sharing_location' => false,
            'last_lat' => null,
            'last_lng' => null,
            'joined_at' => now(),
            'last_seen_at' => null,
        ];
    }

    public function role(EventRole $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function controller(): static
    {
        return $this->role(EventRole::CONTROLLER);
    }

    public function paramedic(): static
    {
        return $this->role(EventRole::PARAMEDIC);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ParticipantStatus::PENDING]);
    }

    public function left(): static
    {
        return $this->state(fn () => ['status' => ParticipantStatus::LEFT]);
    }
}
