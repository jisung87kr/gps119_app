<?php

namespace App\Events;

use App\Models\Dispatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 지령 배정 → 해당 구급대원 개인 채널 (SPEC-05a/05b).
 *
 * ADR-0004: 개인 dispatch 채널이므로 신고자 연락처 포함 허용(본인에게만 전달).
 */
class DispatchAssigned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Dispatch $dispatch) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->dispatch->project_id}.dispatch.{$this->dispatch->paramedic_id}")];
    }

    public function broadcastWith(): array
    {
        $req = $this->dispatch->request;

        return [
            'dispatch_id' => $this->dispatch->id,
            'request' => [
                'id' => $req->id,
                'type' => $req->type?->value,
                'latitude' => $req->latitude,
                'longitude' => $req->longitude,
                'address' => $req->address,
                'requester_name' => $req->user?->name,
                'requester_phone' => $req->user?->phone,
            ],
            'note' => $this->dispatch->note,
            'assigned_at' => $this->dispatch->assigned_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.assigned';
    }
}
