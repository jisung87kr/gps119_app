<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 번들을 «안 거치는» JS 모듈은 매번 검증받아야 한다.
 *
 * 🔴 `public/js/**` 는 Vite 번들이 아니라 브라우저에 원본 그대로 서빙된다. 파일명에
 *    해시가 없으니 고쳐도 옛 사본이 계속 쓰인다 — 실기기에서 3시간 전 사본이 돌아
 *    새 메서드가 없었고, 버튼을 눌러도 아무 일이 없었다(2026-08-31).
 *    증상이 「버튼이 안 눌린다」 하나뿐이라 UI 문제로 오진하기 딱 좋다.
 *
 * 🔑 **막는 곳은 Apache 이고 이 테스트가 지키는 것은 «전제»다.** 헤더 자체는 PHPUnit 이
 *    볼 수 없다(정적 파일은 Laravel 을 거치지 않는다). 대신 그 전제 — 「이 모듈들은
 *    번들 밖에 있고 서로를 절대경로로 부른다」 — 가 유지되는지 본다.
 *    누군가 이 파일들을 Vite 로 옮기면 이 테스트가 깨지고, 그때 Apache 규칙도 함께
 *    걷어내면 된다.
 */
class RawModuleCachingTest extends TestCase
{
    private const RAW_DIR = 'js/components';

    public function test_원본_서빙_모듈이_여전히_번들_밖에_있다(): void
    {
        $this->assertDirectoryExists(public_path(self::RAW_DIR));
        $this->assertNotEmpty(glob(public_path(self::RAW_DIR.'/*.js')));
    }

    public function test_🔴_apache_가_이_경로에_재검증을_강제한다(): void
    {
        // 이 규칙이 사라지면 위 결함이 그대로 돌아온다. 세 vhost 가 모두
        // common.conf 를 include 하므로 여기 한 곳이면 개발·운영에 함께 걸린다.
        // 🔑 «실제로 적용되는» 파일을 본다. 컨테이너에는 저장소 루트가 없고
        //    Apache 설정만 /etc/apache2/gps119/ 로 마운트된다. 저장소 사본을 읽으면
        //    「고쳐놨는데 컨테이너엔 안 들어간」 상태를 못 잡는다.
        $candidates = ['/etc/apache2/gps119/common.conf', base_path('../docker/apache/common.conf')];
        $conf = null;

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                $conf = file_get_contents($path);
                break;
            }
        }

        $this->assertNotNull($conf, 'common.conf 를 찾지 못했다: '.implode(', ', $candidates));
        $this->assertStringContainsString('^/js/.*\.js$', $conf);
        $this->assertStringContainsString('no-cache', $conf);
    }

    public function test_🔑_모듈끼리도_절대경로로_부른다(): void
    {
        // 이게 「URL 에 ?v= 를 붙이는 방식으로는 못 덮는다」의 근거다.
        // 진입점에만 버전을 붙여도 그 안에서 부르는 것들은 캐시된 채 남는다.
        $inner = 0;

        foreach (glob(public_path(self::RAW_DIR.'/*.js')) as $file) {
            $inner += substr_count(file_get_contents($file), "from '/js/");
        }

        $this->assertGreaterThan(0, $inner, '모듈 간 import 가 사라졌다면 Apache 규칙을 재검토할 것');
    }

    public function test_blade_들이_버전_쿼리_없이_부른다(): void
    {
        // 헤더로 막으므로 ?v= 를 흩뿌릴 이유가 없다. 한 곳만 붙이면 나머지가
        // 안 붙은 채 남아 「어떤 파일은 갱신되고 어떤 파일은 안 되는」 상태가 된다.
        foreach (glob(resource_path('views/**/*.blade.php')) + glob(resource_path('views/*.blade.php')) as $view) {
            $this->assertStringNotContainsString(
                "/js/components/locationShare.js?v=",
                file_get_contents($view),
                basename($view).' 에 임시 캐시 버스팅이 남아 있다',
            );
        }
    }
}
