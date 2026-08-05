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
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
        // Vite 의 Laravel 플러그인은 테스트에 불필요하므로 config 를 상속하지 않는다.
    },
});
