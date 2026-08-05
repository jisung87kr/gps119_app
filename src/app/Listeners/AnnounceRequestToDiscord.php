<?php

namespace App\Listeners;

use App\Events\RequestCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 신규 신고 → 운영용 디스코드 웹훅.
 *
 * 🔑 이 부작용이 `NotifyRescuers` 에서 «떨어져 나온» 이유는 재시도 의미가 다르기 때문이다.
 * 한 리스너가 [수신자 통지 + 디스코드]를 같이 하면, 디스코드가 실패했을 때 잡 전체가
 * 재시도되면서 **이미 성공한 통지가 다시 나간다.** 푸시(N1)가 붙는 순간 그건
 * 「같은 신고로 폰이 두 번 우는」 문제가 된다. 부작용 하나에 리스너 하나가 원칙이다.
 *
 * 실패해도 구조 활동에는 지장이 없는 «운영 편의» 경로라 재시도는 1회로 둔다.
 */
class AnnounceRequestToDiscord implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 2;

    public function handle(RequestCreated $event): void
    {
        // config 경유 — 예전엔 리스너 안에서 env() 를 직접 읽었는데,
        // `php artisan config:cache` 를 하면 env() 가 null 을 돌려줘 조용히 꺼진다.
        $url = config('services.discord.webhook_url');

        if (empty($url)) {
            return;
        }

        $request = $event->request;
        $requestUrl = rtrim(config('app.url'), '/').'/requests/'.$request->id;

        $message = "[{$request->description}] 공유됨\n".
            "요청자: {$request->user?->name}\n".
            "연락처: {$request->user?->formatted_phone}\n".
            "위치정보: {$request->latitude}/{$request->longitude}\n".
            "주소: {$request->address}\n".
            $requestUrl;

        // 예전엔 file_get_contents 였다 — 타임아웃이 없어서 디스코드가 응답을 안 주면
        // 큐 워커 한 칸이 그대로 묶인다. 그 워커는 지령 브로드캐스트도 처리한다.
        $response = Http::timeout(5)->post($url, [
            'content' => $message,
            'username' => 'gps119 Bot',
        ]);

        if ($response->failed()) {
            Log::warning('디스코드 웹훅 전송 실패', [
                'request_id' => $request->id,
                'status' => $response->status(),
            ]);
        }
    }

    public function failed(RequestCreated $event, \Throwable $exception): void
    {
        // 삼키지 않는다. 다만 구조 활동 자체를 막지는 않으므로 경고 수준으로 남긴다.
        Log::warning('디스코드 공지 실패', [
            'request_id' => $event->request->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
