<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 페이지별 CDN Vue 의 «미컴파일 템플릿 번쩍임» 가드.
 *
 * 🔴 이 가드가 생긴 이유: v-if/v-show 로 숨겨야 할 모달·오류 배너가 Vue 가 붙기 전에
 *    «전부 보였다가» 사라지는 것이 사용자에게 그대로 보였다(2026-09-04). 루트에 v-cloak 을
 *    두고 CSS 로 숨기면 Vue 가 mount() 시점에 속성을 떼어 낸다. 새 페이지에서 v-cloak 을
 *    빠뜨리면 같은 번쩍임이 돌아오므로 «마운트되는 모든 루트»를 훑는다.
 *
 * 반대 방향도 위험하다: 마운트되지 않는 요소에 v-cloak 을 붙이면 영영 숨는다. 그래서
 * 검사 범위를 «.mount() 를 부르는 뷰와 그 뷰가 @include 하는 부분 뷰»로 한정한다 —
 * 예컨대 관리자 셸에 id="app" 이 생겨도 여기서 v-cloak 을 요구하지 않는다.
 *
 * 관제 SPA(/control)는 Vite 번들이라 루트가 비어 있어 대상이 아니다.
 */
class VueMountCloakTest extends TestCase
{
    public function test_app_css_hides_v_cloak_elements(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\[v-cloak\]\s*\{\s*display:\s*none\s*!important;?\s*\}/',
            $css,
            'app.css 에 [v-cloak]{display:none !important} 규칙이 없다 — 루트의 v-cloak 이 아무 일도 안 한다'
        );
    }

    public function test_every_per_page_vue_mount_root_carries_v_cloak(): void
    {
        $views = collect(File::allFiles(resource_path('views')))
            ->mapWithKeys(fn ($f) => [$f->getRelativePathname() => File::get($f->getPathname())]);

        $checkedRoots = 0;
        $mountingViews = 0;

        foreach ($views as $file => $html) {
            preg_match_all('/\.mount\(\s*[\'"]#([\w-]+)[\'"]\s*\)/', $html, $m);
            if ($m[1] === []) {
                continue;
            }
            $mountingViews++;

            // 루트는 이 뷰 안이거나, 이 뷰가 @include 하는 부분 뷰 안에 있다.
            preg_match_all('/@include\(\s*[\'"]([\w.\-]+)[\'"]/', $html, $inc);
            $candidates = [$file => $html];
            foreach ($inc[1] as $name) {
                $path = str_replace('.', '/', $name).'.blade.php';
                if ($views->has($path)) {
                    $candidates[$path] = $views[$path];
                }
            }

            foreach (array_unique($m[1]) as $id) {
                $found = 0;
                foreach ($candidates as $candidateFile => $candidateHtml) {
                    preg_match_all('/<\w+\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $candidateHtml, $tags);
                    foreach ($tags[0] as $tag) {
                        $found++;
                        $checkedRoots++;
                        $this->assertStringContainsString(
                            'v-cloak',
                            $tag,
                            "{$candidateFile}: #{$id} 마운트 루트에 v-cloak 이 없다 — Vue 가 붙기 전에 숨긴 요소가 다 보인다"
                        );
                    }
                }

                $this->assertGreaterThan(0, $found, "{$file}: .mount('#{$id}') 의 루트 요소를 뷰 안에서 못 찾았다");
            }
        }

        // 2026-09-04 기준 7개 뷰·6개 루트(#app 은 _map-screen 부분 뷰를 두 뷰가 공유). 구조가 바뀌면 갱신.
        $this->assertGreaterThanOrEqual(7, $mountingViews, '마운트하는 뷰가 예상보다 적다 — 정규식이 뷰 구조와 어긋났는지 볼 것');
        $this->assertGreaterThanOrEqual(6, $checkedRoots);
    }
}
