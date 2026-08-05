<?php

namespace App\Listeners;

use App\Enums\EventRole;
use App\Events\RequestCreated;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue; // 큐 활성화(워커 가동 중) — 디스코드/알림을 큐에서 처리
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 신규 신고 → «누구에게 알릴 것인가»를 정하고 통지한다.
 *
 * 이 리스너의 책임은 **수신자 판별 하나**다. 디스코드 공지는 재시도 의미가 달라서
 * AnnounceRequestToDiscord 로 분리했다 — 한 잡에 묶여 있으면 디스코드 실패가
 * 이미 성공한 통지를 재발송시킨다(같은 신고로 폰이 두 번 운다).
 *
 * 실제 발송(FCM·웹푸시)은 N1 의 PushService 가 붙는 자리다.
 */
class NotifyRescuers implements ShouldQueue
{
    use InteractsWithQueue; // 수신자 조회 + 발송이 요청 응답을 막지 않도록 큐에서 처리

    /**
     * Handle the event.
     */
    public function handle(RequestCreated $event): void
    {
        $request = $event->request;

        Log::info('New rescue request created', [
            'request_id' => $request->id,
            'user_id' => $request->user_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'description' => $request->description,
        ]);

        foreach ($this->recipientsFor($request) as $recipient) {
            $this->sendNotificationToRescuer($recipient, $request);
        }

        Log::info('NotifyRescuers listener completed', [
            'request_id' => $request->id,
        ]);
    }

    /**
     * 이 신고를 알려야 할 사람들. 중복 없이 한 번씩.
     *
     * 시스템 롤(spatie)만 보면 행사 상황실을 놓친다 — EventRole::CONTROLLER 는 행사별
     * 역할이라 시스템 롤이 그냥 'user' 인 경우가 흔하고, 그러면 자기 행사에 신고가 떠도
     * 아무 통지를 못 받아 관제 화면을 계속 띄워둬야만 알 수 있었다.
     * 행사 신고라면 그 행사의 활동중 상황실 + 지령 수령 가능 인력(구급대/자원봉사 구급)을
     * 함께 대상에 넣는다.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipientsFor($request)
    {
        $recipients = User::role(['rescuer', 'admin'])->get();

        if ($request->project_id) {
            $eventUserIds = EventParticipant::forProject($request->project_id)
                ->active()
                ->whereIn('role', [
                    EventRole::CONTROLLER->value,
                    EventRole::PARAMEDIC->value,
                    EventRole::VOLUNTEER_MEDIC->value,
                ])
                ->pluck('user_id');

            if ($eventUserIds->isNotEmpty()) {
                $recipients = $recipients->concat(User::whereIn('id', $eventUserIds)->get());
            }
        }

        // rescuer 이면서 admin 인 사람, 시스템 롤과 행사 역할을 겸한 사람이 두 번 받지 않도록.
        return $recipients->unique('id')->values();
    }

    /**
     * Send notification to a specific rescuer.
     */
    private function sendNotificationToRescuer(User $rescuer, $request): void
    {
        Log::info('Notifying rescuer about new request', [
            'rescuer_id' => $rescuer->id,
            'rescuer_name' => $rescuer->name,
            'request_id' => $request->id,
        ]);

        // TODO: Implement actual notification logic (email, SMS, push notification, etc.)
        // For now, we just log the notification
    }

    /**
     * Handle a job failure.
     */
    public function failed(RequestCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to notify rescuers about new request', [
            'request_id' => $event->request->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
