<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * QR·링크를 «앱»으로 받기 위한 검증 파일 (Universal Links / App Links).
 *
 * 🔴 이게 깨지면 행사 현장에서 참가자가 입장 QR 을 찍는 순간 앱이 아니라 브라우저가
 *    열린다. 그러면 백그라운드 위치도 푸시도 그 사람에게는 무의미해진다 —
 *    앱을 만든 이유가 반감된다.
 *
 * 🔑 **실패가 조용하다.** OS 는 파일을 못 읽거나 지문이 안 맞으면 «그냥 브라우저로»
 *    떨어뜨린다. 오류도 로그도 없다. 그래서 사람이 눈치채기 전에 테스트가 잡아야 한다.
 */
class UniversalLinksTest extends TestCase
{
    private const TEAM_APP_ID = 'KWL346JAR4.kr.co.gps119.app';
    private const PACKAGE = 'kr.co.gps119.app';

    private function wellKnown(string $name): array
    {
        $path = public_path('.well-known/'.$name);
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_🔴_aasa_가_팀ID_붙은_앱ID_를_가리킨다(): void
    {
        // appIDs 는 «팀ID.번들ID» 여야 한다. 번들ID 만 적으면 iOS 가 조용히 무시한다.
        $aasa = $this->wellKnown('apple-app-site-association');

        $this->assertSame(
            [self::TEAM_APP_ID],
            $aasa['applinks']['details'][0]['appIDs'],
        );
    }

    public function test_aasa_가_모든_경로를_앱으로_받는다(): void
    {
        // 앱이 곧 이 웹이다(원격 URL 로딩). 경로를 가릴 이유가 없다.
        $components = $this->wellKnown('apple-app-site-association')['applinks']['details'][0]['components'];
        $catchAll = array_filter($components, fn ($c) => ($c['/'] ?? null) === '*' && empty($c['exclude']));

        $this->assertNotEmpty($catchAll, '전체 경로 규칙이 사라졌다');
    }

    public function test_🔑_assetlinks_에_서명_지문이_있다(): void
    {
        // 지문이 안 맞으면 안드로이드가 링크를 «조용히» 브라우저로 보낸다.
        $links = $this->wellKnown('assetlinks.json');

        $this->assertSame(self::PACKAGE, $links[0]['target']['package_name']);
        $this->assertContains('delegate_permission/common.handle_all_urls', $links[0]['relation']);

        foreach ($links[0]['target']['sha256_cert_fingerprints'] as $fp) {
            $this->assertMatchesRegularExpression(
                '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/',
                $fp,
                'SHA-256 지문 형식이 아니다(대문자 콜론 구분 32바이트)',
            );
        }
    }

    public function test_🔴_웹서버가_이_파일들을_그대로_돌려준다(): void
    {
        // Laravel 의 .htaccess 는 «파일이 실제로 있으면» index.php 로 넘기지 않는다.
        // 이 전제가 깨지면 OS 가 HTML 을 받아 파싱에 실패하고, 역시 조용히 넘어간다.
        foreach (['apple-app-site-association', 'assetlinks.json'] as $name) {
            $this->get('/.well-known/'.$name)->assertNotFound();
            // ↑ 라우트가 «없어야» 정상이다. 정적 파일은 Apache 가 처리하므로
            //   Laravel 로 오면 그 자체가 설정이 틀어졌다는 뜻이다.
        }
    }
}
