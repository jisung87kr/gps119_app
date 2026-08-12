<?php

namespace App\Events;

use App\Models\Dispatch;
use App\Models\Request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 신고 상태 갱신 → 신고자 본인 채널 (SPEC-05a/05b).
 *
 * 담당 구급대원 배정/완료 시 신고자에게 담당자 이름·연락처 전달(신고자 본인 채널이라 허용).
 */
class RequestStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Request $request,
        public ?Dispatch $dispatch = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("event.{$this->request->project_id}.requester.{$this->request->user_id}"),
            // 상황실도 받아야 한다. 신고가 취소되면 관제 지도에서 그 핀이 사라져야 하는데,
            // 이 이벤트가 신고자 채널로만 가던 때는 관제 화면이 끝까지 몰랐다.
            // (ADR-0004 상 control 은 연락처 허용 채널이라 페이로드를 나눌 필요가 없다.)
            new PrivateChannel("event.{$this->request->project_id}.control"),
        ];
    }

    public function broadcastWith(): array
    {
        $paramedic = $this->dispatch?->paramedic;

        return [
            'request_id' => $this->request->id,
            'status' => $this->request->status->value,
            'dispatch' => $this->dispatch ? [
                'paramedic_name' => $paramedic?->name,
                'paramedic_phone' => $paramedic?->phone,
            ] : null,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'request.status.updated';
    }
}
