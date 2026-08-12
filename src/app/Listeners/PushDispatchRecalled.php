<?php

namespace App\Listeners;

use App\Events\DispatchRecalled;
use App\Services\Push\PushMessage;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 지령 회수 → 배정됐던 구급대원«본인»에게 푸시.
 *
 * 배정 푸시(PushDispatchAssigned)와 짝이다. 배정만 알리고 회수를 안 알리면, 대원은
 * 화면을 끈 채 취소된 현장으로 계속 이동한다 — 잘못된 출동은 그 사람이 «진짜»
 * 필요한 곳에 못 가는 것과 같은 비용이다.
 *
 * tag 를 배정 푸시와 같은 'dispatch-{id}' 로 둔다. 알림이 쌓이는 게 아니라
 * «배정»이 «회수»로 교체되게 하려는 것이다(PushMessage::$tag → 웹/안드로이드/APNs 공통).
 */
class PushDispatchRecalled implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushService $push) {}

    public function handle(DispatchRecalled $event): void
    {
        $dispatch = $event->dispatch;
        $dispatch->loadMissing('paramedic');

        if ($dispatch->paramedic === null) {
            return;
        }

        $reason = trim((string) $dispatch->note);

        $tally = $this->push->sendToUser($dispatch->paramedic, new PushMessage(
            title: '지령 회수',
            body: $reason !== ''
                ? '상황실이 지령을 회수했습니다 — '.$reason
                : '상황실이 지령을 회수했습니다. 출동을 중단해 주세요.',
            url: '/events/'.$dispatch->project_id.'/dispatch',
            data: ['dispatch_id' => $dispatch->id, 'request_id' => $dispatch->request_id],
            tag: 'dispatch-'.$dispatch->id,
        ));

        Log::info('지령 회수 푸시', [
            'dispatch_id' => $dispatch->id,
            'paramedic_id' => $dispatch->paramedic_id,
            'push' => $tally,
        ]);
    }

    public function failed(DispatchRecalled $event, \Throwable $exception): void
    {
        // 삼키지 않는다. 실패하면 대원이 취소된 현장으로 계속 간다.
        Log::error('지령 회수 푸시 실패', [
            'dispatch_id' => $event->dispatch->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
