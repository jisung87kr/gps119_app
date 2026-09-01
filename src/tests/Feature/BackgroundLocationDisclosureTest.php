<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 백그라운드 위치 «사전 고지»는 권한 요청 앞에 서 있어야 한다.
 *
 * 🔴 Play 정책은 `ACCESS_BACKGROUND_LOCATION` 을 요청하기 «전에», OS 대화상자가
 *    아니라 **우리 화면**으로 두 가지를 말하도록 요구한다 —
 *    ① 앱을 쓰지 않는 동안에도 위치를 수집한다는 «사실»
 *    ② 그것이 «무엇에 쓰이는지»
 *    하나라도 빠지거나, 고지 없이 바로 프롬프트로 가면 반려된다.
 *
 * 🔑 이 결함은 **화면상으로는 아무 이상이 없다.** 버튼을 눌러 권한이 잘 올라가도
 *    정책만 어긴 상태가 되므로, 사람 눈으로 QA 해서는 절대 안 걸린다.
 *    그래서 배선 자체를 테스트가 지킨다 — 누가 버튼을 requestAlways() 로
 *    직결해 되돌려도 여기서 멈춘다.
 *
 * 시연 영상 컷 3 이 이 화면이다 → docs/store/background-location-video.md
 */
class BackgroundLocationDisclosureTest extends TestCase
{
    private function activeView(): string
    {
        return file_get_contents(resource_path('views/event/active.blade.php'));
    }

    public function test_🔴_승격_버튼은_고지를_거쳐야_한다(): void
    {
        $view = $this->activeView();

        // 「항상 허용으로 바꾸기」가 권한 요청으로 «직결»되면 고지가 사라진다.
        $this->assertStringNotContainsString(
            "requestAlways()\"",
            $view,
            '승격 버튼이 requestAlways() 로 직결됐다. 사전 고지를 건너뛰면 Play 반려 사유다.'
        );

        $this->assertStringContainsString('openDisclosure()', $view);
        $this->assertStringContainsString('confirmDisclosure', $view);
    }

    public function test_고지는_사용자_행동_없이_닫히지_않는다(): void
    {
        $view = $this->activeView();

        // 바깥 클릭으로 닫히면 «지나칠 수 있는» 고지가 되어 인정되지 않는다.
        // (같은 파일의 다른 모달이 click.self 를 쓰더라도, 이 모달은 쓰면 안 된다.)
        $modal = $this->disclosureModal($view);
        $this->assertStringNotContainsString('click.self', $modal);

        // 토스트·스낵바가 아니라 모달이어야 한다.
        $this->assertStringContainsString('role="dialog"', $modal);
    }

    public function test_🔴_고지_문구가_두_요소를_모두_말한다(): void
    {
        $modal = $this->disclosureModal($this->activeView());

        // ① 앱을 쓰지 않는 동안에도 수집한다는 사실
        $this->assertStringContainsString('화면이 꺼져 있는', $modal);
        $this->assertStringContainsString('앱을 보고 있지 않을 때', $modal);

        // ② 용도
        $this->assertStringContainsString('상황실 지도', $modal);

        // ③ 언제 멈추는지 — 「사용자가 통제한다」의 근거
        $this->assertStringContainsString('공유를 끄면 즉시 멈춥니다', $modal);
    }

    public function test_🔴_개인정보처리방침도_백그라운드_수집을_말한다(): void
    {
        // 🔴 심사자는 «앱 화면의 고지»와 «방침»을 대조한다. 앱에서는 고지하는데
        //    방침에 없으면 어긋난 상태로 제출된다 — 이것도 화면상 증상이 없다.
        $privacy = file_get_contents(resource_path('views/legal/privacy.blade.php'));

        $this->assertStringContainsString('화면이 꺼져 있는 동안에도', $privacy);
        $this->assertStringContainsString('앱을 보고 있지 않을 때', $privacy);
        // 언제 멈추는지도 함께 말해야 한다.
        $this->assertStringContainsString('위치 공유를 끄면 즉시 중단', $privacy);
    }

    public function test_위치기반서비스_약관도_같이_말한다(): void
    {
        $terms = file_get_contents(resource_path('views/legal/location-terms.blade.php'));

        $this->assertStringContainsString('화면이 꺼져 있는 동안에도', $terms);
    }

    /** 고지 모달의 마크업만 잘라낸다. 파일 전체를 검사하면 다른 모달과 섞인다. */
    private function disclosureModal(string $view): string
    {
        $start = strpos($view, 'v-if="disclosureOpen"');
        $this->assertNotFalse($start, '고지 모달이 없다.');

        // 다음 최상위 블록 전까지. 넉넉히 자르되 스크립트 영역은 넘지 않는다.
        $end = strpos($view, '<script type="module">', $start);

        return substr($view, $start, $end - $start);
    }
}
