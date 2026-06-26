<?php

namespace Database\Factories;

use App\Enums\DispatchStatus;
use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dispatch>
 */
class DispatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'project_id' => Project::factory(),
            'assigned_by' => User::factory(),
            'paramedic_id' => User::factory(),
            'status' => DispatchStatus::ASSIGNED,
            'note' => null,
            'reject_reason' => null,
            'assigned_at' => now(),
        ];
    }

    public function status(DispatchStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
