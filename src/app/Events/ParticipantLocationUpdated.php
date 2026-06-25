<?php

namespace App\Events;

use App\Enums\EventRole;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 참가자 위치 갱신 브로드캐스트 (BE-2.2 / SPEC-05a·05b).
 *
 * 두 채널 동시 송출:
 *   - event.{id}.locations (presence) — 관제 지도 마커 이동
 *   - event.{id}.control   (private)  — 상황실
 *
 * ADR-0004: 페이로드는 최소(좌표/역할/시각)만. **연락처 없음** → 두 채널 동일 payload 안전.
 */
class ParticipantLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $projectId,
        public int $userId,
        public EventRole $role,
        public float $latitude,
        public float $longitude,
        public ?int $accuracy,
        public string $recordedAt,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("event.{$this->projectId}.locations"),
            new PrivateChannel("event.{$this->projectId}.control"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role->value,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'recorded_at' => $this->recordedAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.location';
    }
}
