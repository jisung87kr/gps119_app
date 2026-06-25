<?php

namespace App\Events;

use App\Models\Request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Request $request;

    /**
     * Create a new event instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 브로드캐스트 대상 채널 (OI-1 확정 반영 / SPEC-05b 정정6).
     *
     * - project_id 있는 행사 신고 → 해당 행사 관제 채널(event.{id}.control)
     * - project_id 없는 일반 신고 → 전역 채널(requests.global, 시스템 admin·rescuer 구독)
     *
     * 기존 new Channel('requests') + PrivateChannel('rescuers') 는 ADR-0004대로 제거.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->request->project_id) {
            return [new PrivateChannel("event.{$this->request->project_id}.control")];
        }

        return [new PrivateChannel('requests.global')];
    }

    /**
     * 브로드캐스트 페이로드 (SPEC-05b 최소 페이로드).
     *
     * control / global 은 신뢰 채널(admin·rescuer·controller 만 인가)이므로
     * 연락처(requester.phone) 포함 가능 — ADR-0004 위배 아님.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->request->id,
            'project_id' => $this->request->project_id,
            'type' => $this->request->type ?? null,
            'priority' => $this->request->priority->value,
            'latitude' => $this->request->latitude,
            'longitude' => $this->request->longitude,
            'address' => $this->request->address,
            'requester' => [
                'id' => $this->request->user->id,
                'name' => $this->request->user->name,
                'phone' => $this->request->user->phone,
            ],
            'created_at' => $this->request->requested_at?->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'request.created';
    }
}
