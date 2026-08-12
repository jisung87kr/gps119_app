<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use Tests\TestCase;

/**
 * 지령 상태의 JS 미러가 백엔드 enum 과 어긋나지 않는지 감시한다.
 *
 * 🔴 이 테스트가 생긴 이유: `DispatchStatus::CANCELLED` 를 추가했을 때 JS 쪽 상태 메타
 *    두 곳에 항목을 안 넣었다. 두 파일 모두 «모르는 값은 assigned 로 폴백»하기 때문에
 *    화면이 깨지지 않고 **회수된 지령이 조용히 「배정」으로 표시됐다.** 관제사가 이력을
 *    보면 회수한 건이 아직 배정 중인 것처럼 보인다 — 가장 나쁜 종류의 UI 버그다.
 *
 * 역할 색상은 `EventRole::mapMeta()` 를 주입해 사본 자체를 없앴지만(roleMeta 사고),
 * 지령 상태는 아직 두 화면이 각자 라벨·클래스를 들고 있다. 사본을 지울 수 없다면
 * 최소한 «빠진 것»은 알려주게 한다.
 */
class DispatchStatusJsMirrorTest extends TestCase
{
    /** 데이터 프로바이더는 앱 부팅 «전»에 돈다 — resource_path() 를 여기서 부를 수 없다. */
    public static function mirrorFiles(): array
    {
        return [
            '관제 SPA' => ['resources/js/control/roleMeta.js'],
            '구급대원 지령 화면' => ['public/js/components/dispatchMeta.js'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mirrorFiles')]
    public function test_every_dispatch_status_has_a_js_entry(string $relative): void
    {
        $path = base_path($relative);
        $this->assertFileExists($path);

        // 🔑 파일 전체가 아니라 DISPATCH_STATUS_META «블록 안»에서 찾는다.
        //    처음엔 파일 전체를 훑었는데, dispatchMeta.js 는 TRANSITIONS 에도 같은
        //    키를 갖고 있어서 META 에서 항목을 지워도 테스트가 통과했다 —
        //    감시하려던 바로 그 드리프트를 못 잡는 감시자였다.
        $block = $this->metaBlock(file_get_contents($path));

        foreach (DispatchStatus::cases() as $status) {
            $this->assertMatchesRegularExpression(
                '/(^|\{|,)\s*'.preg_quote($status->value, '/').'\s*:/m',
                $block,
                "{$status->value} 가 ".basename($path)." 의 DISPATCH_STATUS_META 에 없다. "
                .'모르는 값은 조용히 assigned 로 폴백되므로 화면에서는 티가 안 난다.'
            );
        }
    }

    /** DISPATCH_STATUS_META = { ... } 의 중괄호 안쪽만 잘라낸다. */
    private function metaBlock(string $source): string
    {
        preg_match('/DISPATCH_STATUS_META\s*=\s*\{(.*?)\n\};/s', $source, $m);
        $this->assertNotEmpty($m, 'DISPATCH_STATUS_META 블록을 찾지 못했다.');

        return $m[1];
    }

    public function test_control_status_order_lists_every_status(): void
    {
        $source = file_get_contents(base_path('resources/js/control/roleMeta.js'));

        preg_match('/DISPATCH_STATUS_ORDER\s*=\s*\[(.*?)\]/s', $source, $m);
        $this->assertNotEmpty($m, 'DISPATCH_STATUS_ORDER 를 찾지 못했다.');

        foreach (DispatchStatus::cases() as $status) {
            $this->assertStringContainsString(
                "'{$status->value}'",
                $m[1],
                "{$status->value} 가 DISPATCH_STATUS_ORDER 에 없어 필터·집계에서 누락된다."
            );
        }
    }

    /**
     * 대원 화면의 전이표는 그 화면의 «버튼»을 만든다. 회수는 상황실 권한이라
     * 대원의 목표 상태로 등장하면 안 된다 — 눌러도 서버가 403 을 준다.
     */
    public function test_the_paramedic_transition_table_never_offers_recall(): void
    {
        $source = file_get_contents(base_path('public/js/components/dispatchMeta.js'));

        preg_match('/TRANSITIONS\s*=\s*\{(.*?)\n\};/s', $source, $m);
        $this->assertNotEmpty($m, 'TRANSITIONS 를 찾지 못했다.');

        // 키(cancelled:)는 있어야 하지만, 값 배열 안에 'cancelled' 가 있으면 안 된다.
        $this->assertStringContainsString('cancelled:', $m[1]);
        $this->assertDoesNotMatchRegularExpression(
            "/\[[^\]]*'cancelled'[^\]]*\]/",
            $m[1],
            '대원 전이표에 회수가 목표 상태로 들어갔다 — 대원 화면에 눌러도 403 나는 버튼이 생긴다.'
        );
    }
}
