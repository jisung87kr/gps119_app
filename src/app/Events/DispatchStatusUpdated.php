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
 * 지령 상태 전이 → 행사 관제 채널 (SPEC-05a/05b).
 *
 * ADR-0004: control 채널이지만 페이로드에 연락처 없음(상태/대원id/시각만).
 */
class DispatchStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Dispatch $dispatch) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->dispatch->project_id}.control")];
    }

    public function broadcastWith(): array
    {
        return [
            'dispatch_id' => $this->dispatch->id,
            'request_id' => $this->dispatch->request_id,
            'status' => $this->dispatch->status->value,
            // 다중 배차(ADR-0007 D4): 관제 화면은 주담당 상태와 보조 인원수를 따로 센다.
            // 이 플래그가 없으면 보조의 전이가 주담당 상태를 덮어써서, 상황실이 「누가
            // 책임지는가」를 잘못 읽는다.
            'is_primary' => (bool) $this->dispatch->is_primary,
            'paramedic_id' => $this->dispatch->paramedic_id,
            'occurred_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.updated';
    }
}
