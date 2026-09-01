<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 위치 취득 트래커는 «모든» 화면에서 같은 방식으로 정해진다.
 *
 * 🔴 예전에는 호출부가 `tracker:` 를 직접 넘겨야 했고, 세 곳 중 활동 화면 하나만
 *    넘기고 있었다. 그래서 지령·출동 화면과 셸 전송기는 「항상 허용」 권한이 있어도
 *    웹 `watchPosition` 으로 돌았고 **화면을 끄는 순간 위치가 끊겼다.**
 *    실서버에서 출동을 수락한 대원의 기록이 5분 만에 멈춘 것이 그것이다(2026-09-01).
 *
 * 🔑 이제 `createLocationSharer` 가 기본값으로 정한다. 이 테스트는 **호출부가 다시
 *    제각각 배선하지 않는지**를 지킨다 — 한 곳이 특별해지는 순간 같은 결함이 돌아온다.
 */
class LocationTrackerWiringTest extends TestCase
{
    /** @return string[] createLocationSharer 를 부르는 화면 전부 */
    private function callSites(): array
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            if (str_contains(file_get_contents($file->getPathname()), 'createLocationSharer(')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    public function test_호출부가_셋_이상_있다(): void
    {
        // 전제 확인. 한 곳뿐이면 이 테스트의 의미가 없어진다.
        $this->assertGreaterThanOrEqual(3, count($this->callSites()));
    }

    public function test_🔴_어느_화면도_tracker_를_직접_넘기지_않는다(): void
    {
        // 넘기기 시작하면 「넘기는 곳」과 「안 넘기는 곳」이 갈리고,
        // 안 넘긴 화면만 조용히 웹 경로로 돈다. 기본값 한 곳으로 통일한다.
        foreach ($this->callSites() as $view) {
            $this->assertStringNotContainsString(
                'tracker:',
                file_get_contents($view),
                basename($view).' 가 tracker 를 직접 넘긴다 — 기본값에 맡길 것',
            );
        }
    }

    public function test_🔑_기본값이_네이티브를_먼저_본다(): void
    {
        // 이 순서가 뒤집히면 앱에서도 웹 경로로 돌아 배경 추적이 죽는다.
        $src = file_get_contents(public_path('js/components/locationShare.js'));

        $this->assertMatchesRegularExpression(
            '/config\.tracker\s*\|\|\s*env\.__gps119Bridge\?\.locationTracker\s*\|\|\s*webTracker/s',
            $src,
        );
    }
}
