<?php

namespace Tests\Feature;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\FcmSender;
use App\Services\Push\PushMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FCM 발송기 — 액세스 토큰 캐시와 실패 «분류»가 핵심이다.
 *
 * 🔴 앱 푸시에는 우회로가 없다(M-24: 앱 웹뷰에 서비스워커가 없어 웹 푸시로 대체 불가).
 *    여기서 실패를 잘못 분류하면 그대로 「앱 사용자만 알림을 못 받는」 상태가 된다.
 */
class FcmSenderTest extends TestCase
{
    use RefreshDatabase;

    private string $credPath;

    protected function setUp(): void
    {
        parent::setUp();

        // isConfigured() 가 is_readable() 을 보므로 «읽을 수 있는 파일»이 실제로 필요하다.
        // 내용은 안 본다 — 토큰 발급은 주입된 fetcher 가 대신한다.
        $this->credPath = tempnam(sys_get_temp_dir(), 'fcm').'.json';
        file_put_contents($this->credPath, '{}');
    }

    protected function tearDown(): void
    {
        @unlink($this->credPath);
        parent::tearDown();
    }

    private function device(): DeviceToken
    {
        return DeviceToken::register(
            User::factory()->create(),
            PushPlatform::ANDROID,
            'fcm-token-abc',
        );
    }

    private function message(): PushMessage
    {
        return new PushMessage('제목', '본문', '/control?request=7', ['request_id' => 7]);
    }

    /** @param  array<string,mixed>  $token */
    private function sender(array $token = ['access_token' => 'tok-1', 'expires_in' => 3600], ?int & $calls = null): FcmSender
    {
        $calls = 0;

        return new FcmSender('proj-1', $this->credPath, function () use ($token, &$calls) {
            $calls++;

            return $token;
        });
    }

    public function test_자격증명이_없으면_조용히_건너뛴다(): void
    {
        Http::fake();
        $sender = new FcmSender(null, null);

        $this->assertFalse($sender->isConfigured());
        $this->assertSame(PushDelivery::SKIPPED, $sender->send($this->device(), $this->message()));
        Http::assertNothingSent();
    }

    public function test_성공하면_delivered(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/proj-1/messages/1'], 200)]);

        $this->assertSame(PushDelivery::DELIVERED, $this->sender()->send($this->device(), $this->message()));
    }

    public function test_unregistere_d_는_폐기_대상이다(): void
    {
        // 기기가 앱을 지웠다. 재시도해도 영영 안 된다 — 재시도가 아니라 폐기여야 한다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]],
        ], 404)]);

        $this->assertSame(PushDelivery::INVALID, $this->sender()->send($this->device(), $this->message()));
    }

    public function test_일시적_서버오류는_재시도_대상이다(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);

        $this->assertSame(PushDelivery::FAILED, $this->sender()->send($this->device(), $this->message()));
    }

    public function test_🔑_액세스_토큰은_재사용된다(): void
    {
        // 캐시가 없으면 «푸시 한 건마다» 구글에 토큰을 새로 요청한다.
        // 신고 하나가 수신자 수만큼 발송되므로 대량 행사에서 그대로 지연으로 돌아온다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);
        $sender = $this->sender(calls: $calls);

        $sender->send($this->device(), $this->message());
        $sender->send($this->device(), $this->message());
        $sender->send($this->device(), $this->message());

        $this->assertSame(1, $calls, '토큰이 매번 새로 발급되고 있다');
    }

    public function test_🔑_401이면_캐시를_버려_다음_시도가_새_토큰을_받는다(): void
    {
        // 캐시를 안 버리면 남은 TTL 동안 상한 토큰을 계속 써서 재시도가 «전부 같은 이유로»
        // 실패한다. 재시도가 의미를 가지려면 다음 시도에서 새 토큰을 받아야 한다.
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401)]);
        $sender = $this->sender(calls: $calls);

        $this->assertSame(PushDelivery::FAILED, $sender->send($this->device(), $this->message()));
        $sender->send($this->device(), $this->message());

        $this->assertSame(2, $calls, '401 이후에도 상한 토큰을 재사용하고 있다');
    }

    public function test_만료가_짧으면_캐시도_짧게_잡는다(): void
    {
        // TTL 을 고정값으로 박으면 구글이 만료를 바꿨을 때 «조용히» 어긋난다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender(['access_token' => 'tok-short', 'expires_in' => 120])
            ->send($this->device(), $this->message());

        $this->assertSame('tok-short', Cache::get('push.fcm.token:'.sha1($this->credPath)));
    }

    public function test_🔑_빈_토큰이면_요청_자체를_보내지_않는다(): void
    {
        // 빈 Bearer 로 요청을 쏘면 401 이 오고, 그건 「구글이 잠깐 이상한가」와
        // 구분되지 않는다. 보내기 «전»에 끊어야 원인이 로그에 정확히 남는다.
        Http::fake();

        $result = $this->sender(['access_token' => '', 'expires_in' => 3600])
            ->send($this->device(), $this->message());

        Http::assertNothingSent();

        // 예외로 «던지지» 않는 것도 계약이다 — PushService 는 기기 목록을 돌며
        // send() 를 부르므로, 여기서 던지면 자격증명 하나 때문에 그 배치의
        // 나머지 수신자가 통째로 누락된다. 값으로 돌려주고 report() 로 드러낸다.
        $this->assertSame(PushDelivery::FAILED, $result);
    }

    public function test_🔴_페이로드에_전화번호가_없다(): void
    {
        // ADR-0004. 푸시는 잠금화면에 뜨고 벤더 서버를 거친다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), new PushMessage(
            '새 구조요청', '사고 — 서울시청 앞', '/control?request=7', ['request_id' => 7],
        ));

        Http::assertSent(function ($request) {
            $this->assertDoesNotMatchRegularExpression('/01\d[-\s]?\d{3,4}[-\s]?\d{4}/', json_encode($request->data()));

            return true;
        });
    }

    public function test_🔑_tag_가_있으면_기기에서_대체된다(): void
    {
        // tag 는 toWebPayload() 에만 실려 있었다 — 웹은 대체되고 «앱만» 쌓였다.
        // 리스너들은 최신 것만 남는다고 전제하고 쓴다(NotifyRescuers: request-{id}).
        // 쌓이면 안드로이드가 그룹으로 묶고, 그룹 요약 줄에는 메시지 extras 가 없어
        // 그걸 누르면 딥링크가 사라진다 — 대원이 배정 화면 대신 대시보드에 떨어진다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), new PushMessage(
            '새 구조요청', '사고', '/control?request=7', ['request_id' => 7], 'request-7',
        ));

        Http::assertSent(function ($request) {
            $message = $request->data()['message'];

            $this->assertSame('request-7', $message['android']['notification']['tag']);
            // iOS 는 tag 대신 헤더로 합친다.
            $this->assertSame('request-7', $message['apns']['headers']['apns-collapse-id']);

            return true;
        });
    }

    public function test_🔑_뱃지_숫자가_ap_ns_페이로드에_실린다(): void
    {
        // iOS 앱 아이콘 뱃지는 aps.badge 로만 정해진다. Capacitor 의
        // presentationOptions:['badge',…] 는 «갱신을 허용한다»는 뜻일 뿐이라,
        // 이걸 안 실으면 뱃지가 «아예» 안 뜬다(실기기 QA 2026-08-09).
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), (new PushMessage(
            '새 구조요청', '사고', '/control?request=7', [], 'request-7',
        ))->withBadge(3));

        Http::assertSent(function ($request) {
            $message = $request->data()['message'];

            $this->assertSame(3, $message['apns']['payload']['aps']['badge']);
            // tag 블록과 «같은» apns 키를 쓴다 — 한쪽이 다른 쪽을 덮어쓰면 안 된다.
            $this->assertSame('request-7', $message['apns']['headers']['apns-collapse-id']);

            return true;
        });
    }

    public function test_🔑_뱃지_0_은_«지우기»라서_실어야_한다(): void
    {
        // `if ($badge)` 로 쓰면 0 이 falsy 라 지우기가 통째로 사라진다.
        // 볼 것이 없어진 사람의 뱃지가 영영 안 지워진다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), $this->message()->withBadge(0));

        Http::assertSent(function ($request) {
            $this->assertSame(0, $request->data()['message']['apns']['payload']['aps']['badge']);

            return true;
        });
    }

    public function test_tag_가_없으면_해당_블록을_보내지_않는다(): void
    {
        // 빈 tag 를 보내면 FCM 이 400 을 준다 — 없으면 «키 자체가 없어야» 한다.
        //
        // ⚠️ apns 는 더 이상 «통째로» 없지 않다. sound/interruption-level 이 tag 와
        //    무관하게 항상 실리기 때문이다(아래 테스트). 여기서 고정하려는 것은
        //    «collapse-id 를 빈 값으로 보내지 않는다»이므로 그 키만 본다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), $this->message());

        Http::assertSent(function ($request) {
            $message = $request->data()['message'];

            $this->assertArrayNotHasKey('android', $message);
            $this->assertArrayNotHasKey('headers', $message['apns']);

            return true;
        });
    }

    public function test_🔑_i_os_알림은_소리와_time_sensitive_를_달고_나간다(): void
    {
        // 실기기 QA(2026-08-31): 알림은 «오는데» 잠금화면에서 화면이 켜지지 않고,
        // 손으로 깨워야 보였다. 소리도 안 났다.
        //
        // 안드로이드는 채널(IMPORTANCE_HIGH)이 heads-up 을 보장하지만 iOS 에서
        // 그 역할을 하는 건 이 페이로드다. 둘 다 비어 있어 iOS 만 반쪽이었다.
        // 구조 지령이 «조용히» 도착하면 대원이 배정을 놓친다 — 뱃지와 달리
        // 이건 편의가 아니라 도달 자체의 문제다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), $this->message());

        Http::assertSent(function ($request) {
            $aps = $request->data()['message']['apns']['payload']['aps'];

            $this->assertSame('default', $aps['sound']);
            $this->assertSame('time-sensitive', $aps['interruption-level']);

            return true;
        });
    }

    public function test_tag_와_소리가_같은_apns_키를_두고_다투지_않는다(): void
    {
        // tag 블록이 `$payload['apns'] = [...]` 로 «통째 대입»이던 시절엔 순서만
        // 바뀌어도 sound 가 조용히 사라졌다. 셋(collapse-id·sound·badge)이 같은
        // apns 키를 공유하므로 «함께 살아남는지»를 고정한다.
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);

        $this->sender()->send($this->device(), (new PushMessage(
            '새 구조요청', '사고', '/control?request=7', [], 'request-7',
        ))->withBadge(2));

        Http::assertSent(function ($request) {
            $apns = $request->data()['message']['apns'];

            $this->assertSame('request-7', $apns['headers']['apns-collapse-id']);
            $this->assertSame('default', $apns['payload']['aps']['sound']);
            $this->assertSame('time-sensitive', $apns['payload']['aps']['interruption-level']);
            $this->assertSame(2, $apns['payload']['aps']['badge']);

            return true;
        });
    }
}
