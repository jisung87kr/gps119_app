<?php

namespace App\Listeners;

use App\Enums\DispatchStatus;
use App\Events\RequestStatusUpdated;
use App\Services\Push\PushMessage;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 신고 상태 변경 → 신고자«본인»에게 푸시.
 *
 * 구조를 기다리는 사람에게 「접수됐고 누가 오고 있다」를 알리는 경로다.
 * 대시보드를 계속 보고 있어야만 알 수 있던 것을 대체한다 —
 * 사고 현장에서 화면을 들여다보고 있으라는 건 현실적이지 않다.
 */
class PushRequestStatusToRequester implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushService $push) {}

    public function handle(RequestStatusUpdated $event): void
    {
        $request = $event->request;
        $request->loadMissing('user');

        if ($request->user === null) {
            return;
        }

        $body = match ($event->dispatch?->status) {
            DispatchStatus::ACCEPTED => '구조대가 배정되었습니다. 곧 출발합니다.',
            DispatchStatus::COMPLETED => '구조가 완료 처리되었습니다.',
            default => '신고 상태가 변경되었습니다.',
        };

        // 🔴 담당 대원 «이름·연락처»를 담지 않는다(ADR-0004).
        //    신고자 본인 «채널»에는 실어도 되지만 푸시는 잠금화면에 뜬다 —
        //    사고 현장에서 폰을 남이 볼 수 있는 상황이 오히려 흔하다.
        $tally = $this->push->sendToUser($request->user, new PushMessage(
            title: 'GPS119 구조요청',
            body: $body,
            url: '/requests/'.$request->id,
            data: ['request_id' => $request->id, 'status' => $request->status->value],
            // 같은 신고의 상태 알림은 최신 것만 남는다.
            tag: 'request-status-'.$request->id,
        ));

        Log::info('신고 상태 푸시', [
            'request_id' => $request->id,
            'status' => $request->status->value,
            'push' => $tally,
        ]);
    }

    public function failed(RequestStatusUpdated $event, \Throwable $exception): void
    {
        Log::error('신고 상태 푸시 실패', [
            'request_id' => $event->request->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
