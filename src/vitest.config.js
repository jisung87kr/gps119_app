import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * JS 단위 테스트 (PHP 는 phpunit — `php artisan test`).
 *
 * environment 는 'node' 다. 대상이 DOM 을 쓰지 않기 때문 —
 * locationShare.js 의 전역 의존은 window.axios · navigator.geolocation 두 개뿐이라
 * jsdom 을 얹을 이유가 없다(느려지기만 한다). DOM 이 필요한 대상을 넣게 되면
 * 그 파일에만 `// @vitest-environment jsdom` 을 붙인다.
 *
 * 화면 통합(Vue + 카카오맵 + Echo)은 여기 대상이 아니다 — gstack browse 로 본다.
 */
export default defineConfig({
    // 🔑 `public/` 을 «퍼블릭 디렉터리로 취급하지 않는다».
    //    public/js/components/* 는 브라우저에 그대로 서빙되는 모듈이라 서로를
    //    `/js/components/X.js` 라는 «절대 경로»로 import 한다. 기본 설정에서는 Vite 가
    //    "Cannot import non-asset file ... inside /public" 으로 막아 테스트조차 못 짠다.
    //    여기서는 번들링이 아니라 «읽어서 실행»만 하므로 그 가드가 필요 없다.
    publicDir: false,

    resolve: {
        alias: [
            // 브라우저에서의 `/js/...` 를 실제 파일 경로로 옮긴다.
            // 이게 없으면 위 모듈들을 단위 테스트에서 import 할 수 없다.
            {
                find: /^\/js\//,
                replacement: fileURLToPath(new URL('./public/js/', import.meta.url)),
            },
        ],
    },

    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
        // Vite 의 Laravel 플러그인은 테스트에 불필요하므로 config 를 상속하지 않는다.
    },
});
