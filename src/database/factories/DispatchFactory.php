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
            // 기본은 주담당 — 지금까지 만들어진 지령은 전부 「이 환자를 책임지는 1명」이다.
            'is_primary' => true,
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

    /** 보조 인원(ADR-0007 D4). */
    public function support(): static
    {
        return $this->state(fn () => ['is_primary' => false]);
    }
}
