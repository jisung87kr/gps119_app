<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 안전영역은 «한 규약»으로만 쓴다 (2026-09-01 실기기).
 *
 * 🔴 안드로이드 웹뷰는 `env(safe-area-inset-*)` 를 채워주지 않는다. iOS 는 시스템이
 *    넣어주지만 안드로이드는 Capacitor 가 «CSS 변수» --safe-area-inset-* 로 준다.
 *    env 를 직접 쓴 화면은 **안드로이드에서만 조용히 0 이 되고**, Android 15+ 는
 *    edge-to-edge 가 강제라 하단이 내비게이션바에 가린다.
 *
 * 🔑 화면에서는 --safe-top/--safe-right/--safe-bottom/--safe-left 만 쓴다.
 *    정의는 app.css 한 곳이고 거기서 둘 중 큰 값을 고른다.
 *
 * 🔑 **iOS 로만 QA 하면 절대 안 걸린다.** 그쪽은 env 가 정상이라 멀쩡해 보인다.
 */
class SafeAreaInsetTest extends TestCase
{
    /** @return string[] 화면을 그리는 소스 전부 */
    private function sources(): array
    {
        $found = [];

        foreach ([resource_path('views'), resource_path('js')] as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if (preg_match('/\.(blade\.php|js)$/', $file->getFilename())) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    public function test_🔴_화면에서_env_safe_area_를_직접_쓰지_않는다(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            $body = file_get_contents($path);

            // 주석에서 «설명»하는 것은 허용한다. 실제 CSS 값으로 쓰는 것만 잡는다.
            foreach (preg_split('/\R/', $body) as $line) {
                if (! str_contains($line, 'env(safe-area-inset')) {
                    continue;
                }
                if (preg_match('/^\s*(\*|\/\/|\{\{--|<!--)/', $line) || str_contains($line, '가 없으면')) {
                    continue;
                }

                $offenders[] = basename($path).': '.trim($line);
            }
        }

        $this->assertSame([], $offenders,
            "env(safe-area-inset) 를 직접 썼다. 안드로이드에서는 0 이 된다 — app.css 의 --safe-* 를 쓸 것.\n"
            .implode("\n", $offenders));
    }

    public function test_변수_정의가_한_곳에_있다(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        foreach (['--safe-top', '--safe-right', '--safe-bottom', '--safe-left'] as $name) {
            $this->assertStringContainsString($name.':', $css);
        }

        // 🔑 둘 중 큰 값을 골라야 양쪽 플랫폼에서 맞는다.
        $this->assertStringContainsString('max(env(safe-area-inset-bottom', $css);
        $this->assertStringContainsString('var(--safe-area-inset-bottom', $css);
    }
}
