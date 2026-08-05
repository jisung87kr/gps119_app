<?php

namespace Tests\Feature;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PushService — 전송 «정책».
 *
 * 전송 규격(HTTP·암호화)은 가짜 sender 로 대체한다. 여기서 고정하려는 것은
 * 규격과 무관한 결정들이다: 누구에게 보내는가, 무효 토큰을 어떻게 하는가,
 * 그리고 «보낼 곳이 없을 때 터지지 않는가».
 */
class PushServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSender(PushDelivery $result, bool $configured = true): PushSender
    {
        return new class($result, $configured) implements PushSender
        {
            public array $sentTo = [];

            public function __construct(
                private PushDelivery $result,
                private bool $configured,
            ) {}

            public function supports(PushPlatform $platform): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function send(DeviceToken $device, PushMessage $message): PushDelivery
            {
                $this->sentTo[] = $device->id;

                return $this->result;
            }
        };
    }

    private function message(): PushMessage
    {
        return new PushMessage('신규 신고', '구조요청이 접수되었습니다.', '/control');
    }

    public function test_sends_to_every_active_device_of_a_user(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);
        DeviceToken::factory()->revoked()->create(['user_id' => $user->id]);

        $sender = $this->fakeSender(PushDelivery::DELIVERED);
        $tally = (new PushService([$sender]))->sendToUser($user, $this->message());

        $this->assertCount(2, $sender->sentTo, '폐기된 통로로도 보냈다');
        $this->assertSame(2, $tally['delivered']);
    }

    public function test_does_not_send_to_other_users(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id]);
        DeviceToken::factory()->create(['user_id' => User::factory()->create()->id]);

        $sender = $this->fakeSender(PushDelivery::DELIVERED);
        (new PushService([$sender]))->sendToUser($user, $this->message());

        $this->assertCount(1, $sender->sentTo, '남의 기기로 알림이 갔다');
    }

    public function test_a_user_appearing_twice_is_only_pushed_once(): void
    {
        // 수신자 목록은 리스너가 만든다 — 시스템 롤과 행사 역할을 겸한 사람이
        // 두 번 들어올 수 있다. 그때 폰이 두 번 울리면 안 된다.
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $sender = $this->fakeSender(PushDelivery::DELIVERED);
        (new PushService([$sender]))->sendToUsers([$user, $user], $this->message());

        $this->assertCount(1, $sender->sentTo);
    }

    public function test_an_invalid_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $device = DeviceToken::factory()->create(['user_id' => $user->id]);

        (new PushService([$this->fakeSender(PushDelivery::INVALID)]))
            ->sendToUser($user, $this->message());

        $this->assertNotNull($device->fresh()->revoked_at, '죽은 통로가 남으면 매 알림마다 실패가 쌓인다');
    }

    public function test_a_transient_failure_does_not_revoke(): void
    {
        // 일시 장애로 살아있는 기기를 지우면, 그 사람은 다시 구독하기 전까지
        // 지령을 못 받는다. FAILED 와 INVALID 를 가르는 이유가 이것이다.
        $user = User::factory()->create();
        $device = DeviceToken::factory()->create(['user_id' => $user->id]);

        (new PushService([$this->fakeSender(PushDelivery::FAILED)]))
            ->sendToUser($user, $this->message());

        $this->assertNull($device->fresh()->revoked_at);
    }

    public function test_is_a_no_op_when_no_sender_is_configured(): void
    {
        // FCM 은 명의(N0-4) 확정 전까지 꺼져 있다. 그 상태에서 예외가 나면
        // 신고 접수 자체가 막힌다 — 「알림이 안 갔다」보다 훨씬 나쁘다.
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $tally = (new PushService([$this->fakeSender(PushDelivery::DELIVERED, configured: false)]))
            ->sendToUser($user, $this->message());

        $this->assertSame(1, $tally['skipped']);
        $this->assertSame(0, $tally['delivered']);
    }

    public function test_is_a_no_op_when_the_user_has_no_devices(): void
    {
        $tally = (new PushService([$this->fakeSender(PushDelivery::DELIVERED)]))
            ->sendToUser(User::factory()->create(), $this->message());

        $this->assertSame(0, array_sum($tally));
    }

    public function test_sending_to_an_empty_recipient_list_is_safe(): void
    {
        $tally = (new PushService([$this->fakeSender(PushDelivery::DELIVERED)]))
            ->sendToUsers([], $this->message());

        $this->assertSame(0, array_sum($tally));
    }
}
