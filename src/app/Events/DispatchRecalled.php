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
 * 지령 회수 → 해당 구급대원 개인 채널 (ADR-0007).
 *
 * DispatchStatusUpdated 로 갈음하지 않는 이유: 그건 상황실 화면을 갱신하는 이벤트다.
 * 회수에서 정말 중요한 사람은 «이미 달리고 있는 대원»이고, 그 사람에게 닿는 채널은
 * 개인 dispatch 채널뿐이다. 별도 이벤트라 별도 리스너(푸시)를 달 수 있다.
 *
 * 페이로드에 연락처를 싣지 않는다 — 회수는 「가지 마세요」이지 「연락하세요」가 아니다.
 */
class DispatchRecalled implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Dispatch $dispatch) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->dispatch->project_id}.dispatch.{$this->dispatch->paramedic_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'dispatch_id' => $this->dispatch->id,
            'request_id' => $this->dispatch->request_id,
            'status' => $this->dispatch->status->value,
            'reason' => $this->dispatch->note,
            'cancelled_at' => $this->dispatch->cancelled_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.recalled';
    }
}
