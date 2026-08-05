<?php

namespace App\Listeners;

use App\Events\DispatchAssigned;
use App\Services\Push\PushMessage;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 지령 배정 → 배정된 구급대원«본인»에게 푸시.
 *
 * 이 앱에서 가장 긴급도가 높은 알림이다. 지금까지는 Reverb 개인 채널로만 갔는데,
 * 그건 앱/탭이 떠 있을 때만 닿는다 — 현장 대원이 화면을 끄고 이동 중이면
 * 배정된 사실 자체를 모른다. 「푸시 없는 모바일 관제는 안전 자산이 아니라
 * 안전 부채」([07] 리스크 표)가 정확히 이 지점이다.
 */
class PushDispatchAssigned implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushService $push) {}

    public function handle(DispatchAssigned $event): void
    {
        $dispatch = $event->dispatch;
        $dispatch->loadMissing('paramedic', 'request');

        if ($dispatch->paramedic === null) {
            return;
        }

        // 🔴 연락처를 담지 않는다(ADR-0004). 개인 dispatch «채널»은 연락처를 실어도 되지만,
        //    푸시는 다르다 — 잠금화면에 뜨고 전송 사업자 서버를 거친다.
        //    대원은 알림을 탭해 지령 화면에서 인가된 경로로 연락처를 받는다.
        $tally = $this->push->sendToUser($dispatch->paramedic, new PushMessage(
            title: '🚑 출동 지령',
            body: ($dispatch->request?->type?->label() ?? '구조요청').' 배정 — 수락해 주세요',
            url: '/events/'.$dispatch->project_id.'/dispatch',
            data: ['dispatch_id' => $dispatch->id, 'request_id' => $dispatch->request_id],
            tag: 'dispatch-'.$dispatch->id,
        ));

        Log::info('지령 배정 푸시', [
            'dispatch_id' => $dispatch->id,
            'paramedic_id' => $dispatch->paramedic_id,
            'push' => $tally,
        ]);
    }

    public function failed(DispatchAssigned $event, \Throwable $exception): void
    {
        // 삼키지 않는다. 이 알림이 실패하면 대원이 배정을 모른 채 있을 수 있다.
        Log::error('지령 배정 푸시 실패', [
            'dispatch_id' => $event->dispatch->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
