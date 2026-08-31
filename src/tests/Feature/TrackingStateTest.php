<?php

namespace Tests\Feature;

use App\Enums\LocationPermission;
use App\Enums\TrackingState;
use App\Models\EventParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 「공유 켬 + OS 권한 없음」을 관제가 구분한다 (M-5, ADR-0008).
 *
 * 🔑 여기서 고정하려는 것은 라벨이 아니라 **세 축의 조합**이다 —
 *    의도(sharing_location) · 능력(location_permission) · 증거(last_seen_at).
 *    M-5 이전에는 능력 축이 없어서 「껐다」·「권한이 없다」·「네트워크가 끊겼다」가
 *    전부 같은 «오프라인»으로 보였다. 백그라운드 추적(N3)에서는 이 구분이 사고를 가른다.
 *
 * ⚠️ 이 판정은 사람 눈으로 확인할 수 없다 — 화면에는 배지 하나로만 나오고,
 *    틀린 배지도 «그럴듯하게» 보인다. 그래서 테스트가 유일한 방어다.
 */
class TrackingStateTest extends TestCase
{
    use RefreshDatabase;

    private function participant(array $attrs = []): EventParticipant
    {
        return new EventParticipant(array_merge([
            'sharing_location' => true,
            'location_permission' => LocationPermission::ALWAYS,
            'last_seen_at' => now(),
        ], $attrs));
    }

    public function test_공유를_끄면_나머지를_보지_않는다(): void
    {
        // 권한이 없어도 «정상»이다. 끈 사람을 붉게 띄우면 진짜 이상이 묻힌다.
        $p = $this->participant([
            'sharing_location' => false,
            'location_permission' => LocationPermission::DENIED,
        ]);

        $this->assertSame(TrackingState::OFF, $p->trackingState());
    }

    public function test_🔴_보고가_없으면_권한없음이_아니라_알수없음이다(): void
    {
        // null 은 「거부」가 아니라 「보고한 적이 없다」(웹 사용자·구버전 앱)다.
        // 둘을 같게 취급하면 웹으로 잘 쓰고 있는 사람이 전부 «위치 권한 없음»으로 뜬다.
        $p = $this->participant(['location_permission' => null]);

        $this->assertSame(TrackingState::UNKNOWN, $p->trackingState());
    }

    public function test_🔴_공유는_켰는데_권한이_거부면_blocked(): void
    {
        // M-5 가 드러내려는 그 상태. 참가자는 「켜뒀으니 보이겠지」라고 믿는데
        // 실제로는 한 번도 보인 적이 없다.
        $p = $this->participant(['location_permission' => LocationPermission::DENIED]);

        $state = $p->trackingState();

        $this->assertSame(TrackingState::BLOCKED, $state);
        $this->assertTrue($state->needsAttention());
    }

    public function test_기기_위치서비스가_꺼진_것도_blocked_다(): void
    {
        // 앱 권한은 있는데 기기 위치가 꺼진 경우. 사용자에게는 다른 안내가 나가지만
        // 관제가 보는 결론은 같다 — 위치가 안 온다.
        $p = $this->participant(['location_permission' => LocationPermission::SERVICES_OFF]);

        $this->assertSame(TrackingState::BLOCKED, $p->trackingState());
    }

    public function test_공유를_켰는데_아직_안_물어본_상태도_blocked_다(): void
    {
        // 공유를 켠 시점에는 이미 물었어야 한다. 그 조합은 「아직」이 아니라
        // 「물었는데 배선이 깨졌다」이므로 조용히 정상으로 두면 안 된다.
        $p = $this->participant(['location_permission' => LocationPermission::NOT_DETERMINED]);

        $this->assertSame(TrackingState::BLOCKED, $p->trackingState());
    }

    public function test_🔑_앱_사용중_권한은_신선도와_무관하게_foreground_only(): void
    {
        // 화면을 닫아 끊긴 것은 «고장이 아니라 그 권한의 정상 동작»이다.
        // STALE 로 올리면 진짜 이상(항상 허용인데 안 옴)과 구분이 안 된다.
        $fresh = $this->participant(['location_permission' => LocationPermission::WHEN_IN_USE]);
        $stale = $this->participant([
            'location_permission' => LocationPermission::WHEN_IN_USE,
            'last_seen_at' => now()->subMinutes(30),
        ]);

        $this->assertSame(TrackingState::FOREGROUND_ONLY, $fresh->trackingState());
        $this->assertSame(TrackingState::FOREGROUND_ONLY, $stale->trackingState());
    }

    public function test_항상허용이고_최근_위치가_있으면_tracking(): void
    {
        $this->assertSame(TrackingState::TRACKING, $this->participant()->trackingState());
    }

    public function test_항상허용인데_위치가_끊기면_stale(): void
    {
        // 권한은 충분한데 안 온다 — 네트워크·배터리·앱 종료를 의심하는 상태다.
        // BLOCKED 와 «다른» 원인이므로 상황실의 조치도 다르다.
        $p = $this->participant(['last_seen_at' => now()->subMinutes(5)]);

        $state = $p->trackingState();

        $this->assertSame(TrackingState::STALE, $state);
        $this->assertFalse($state->needsAttention(), '잠깐의 끊김을 경보로 올리면 BLOCKED 가 묻힌다');
    }

    public function test_한_번도_위치를_보낸_적_없으면_stale_이다(): void
    {
        $p = $this->participant(['last_seen_at' => null]);

        $this->assertSame(TrackingState::STALE, $p->trackingState());
    }

    public function test_🔑_관제로_주입되는_메타에_모든_상태가_들어간다(): void
    {
        // 🔴 하나라도 빠지면 그 상태인 참가자가 화면에서 «알 수 없음»으로 떨어진다.
        //    상태를 추가하고 이 표를 안 고치는 실수를 여기서 잡는다.
        $meta = TrackingState::mapMeta();

        $this->assertCount(count(TrackingState::cases()), $meta);

        foreach (TrackingState::cases() as $state) {
            $this->assertArrayHasKey($state->value, $meta);
            $this->assertSame($state->label(), $meta[$state->value]['label']);
            $this->assertSame($state->needsAttention(), $meta[$state->value]['attention']);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $meta[$state->value]['color']);
        }
    }

    public function test_🔴_경보는_blocked_하나뿐이다(): void
    {
        // 관제의 경보 인원수가 이 판단을 그대로 쓴다. 여기가 늘어나면 화면도 같이
        // 늘어나는데, 반대로 stale 을 경보로 올리면 잠깐의 끊김이 blocked 를 묻는다.
        $attention = array_keys(array_filter(TrackingState::mapMeta(), fn ($m) => $m['attention']));

        $this->assertSame([TrackingState::BLOCKED->value], $attention);
    }
}
