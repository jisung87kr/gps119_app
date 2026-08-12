<?php

namespace App\Listeners;

use App\Enums\EventRole;
use App\Events\RequestCreated;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\PushService;
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
 * 발송은 PushService 가 한다(웹푸시 + 앱푸시). 이 리스너는 «누구에게»만 정한다.
 */
class NotifyRescuers implements ShouldQueue
{
    use InteractsWithQueue; // 수신자 조회 + 발송이 요청 응답을 막지 않도록 큐에서 처리

    public function __construct(private readonly PushService $push) {}

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

        $recipients = $this->recipientsFor($request);

        // 🔴 페이로드에 연락처·상세 주소를 넣지 않는다(ADR-0004). 푸시는 잠금화면에 뜨고
        //    전송 사업자 서버를 거친다. «무슨 일이 났으니 열어라»까지만 말하고,
        //    상세는 관제 화면이 인가된 채널로 받아온다.
        $tally = $this->push->sendToUsers($recipients, new PushMessage(
            title: '🚨 신규 구조요청',
            body: ($request->type?->label() ?? '구조요청').' · '.($request->project?->name ?? '상시 운영'),
            // 딥링크는 행사를 «명시»한다. 상황실이 행사를 2개 이상 맡으면
            // 행사 없이 열었을 때 엉뚱한 현장이 뜬다.
            url: '/control?project='.$request->project_id.'&request='.$request->id,
            data: ['request_id' => $request->id, 'project_id' => $request->project_id],
            // 같은 신고로 알림이 쌓이지 않게 «대체»된다.
            tag: 'request-'.$request->id,
        ));

        Log::info('NotifyRescuers listener completed', [
            'request_id' => $request->id,
            'recipients' => $recipients->count(),
            'push' => $tally,
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
        // 시스템 롤로 받는 사람은 관리자뿐이다. 상시 구급 인력은 「상시 운영」 행사의
        // 구급대라, 아래 행사 스코프에서 잡힌다 — 이 대칭이 깨지면 길가 신고 알림이
        // 조용히 끊긴다(화면은 멀쩡해 보인다).
        $recipients = User::role('admin')->get();

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

        // 관리자이면서 그 행사의 역할도 가진 사람이 두 번 받지 않도록.
        return $recipients->unique('id')->values();
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
