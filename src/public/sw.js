/* GPS119 서비스워커 (PWA 마감, Phase 4).
 *
 * 전략:
 *  - /api/*, /broadcasting/* → 캐시 안 함(network-only). 실시간 데이터 stale 방지.
 *  - 교차 출처(kakao 지도/타일·unpkg·fonts·reverb WS) → 패스스루(가로채지 않음).
 *  - 페이지 내비게이션 → network-first, 실패 시 offline.html.
 *  - 동일 출처 정적 자산(/build/ 해시 자산·/icons·/js·css) → stale-while-revalidate.
 *  WebSocket(Reverb)은 fetch 가 아니므로 SW가 가로채지 않음 → 실시간 영향 없음.
 */

const VERSION = 'gps119-v1';
const SHELL_CACHE = `${VERSION}-shell`;
const ASSET_CACHE = `${VERSION}-assets`;

const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icon-192.png',
    '/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

function isStaticAsset(url) {
    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/js/')
        || /\.(css|js|png|jpg|jpeg|svg|webp|woff2?)$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // GET 만 처리
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // 교차 출처는 가로채지 않음(지도/폰트/CDN/WS)
    if (url.origin !== self.location.origin) return;

    // 실시간/동적: 캐시 금지
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/broadcasting/')) {
        return; // 기본 네트워크 동작
    }

    // 페이지 내비게이션: network-first → 실패 시 offline 셸
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // 정적 자산: stale-while-revalidate
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const cached = await cache.match(request);
                const network = fetch(request).then((res) => {
                    if (res && res.status === 200) cache.put(request, res.clone());
                    return res;
                }).catch(() => cached);
                return cached || network;
            })
        );
    }
});
