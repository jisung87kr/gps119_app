/* GPS119 서비스워커 (PWA 마감, Phase 4).
 *
 * 전략:
 *  - /api/*, /broadcasting/* → 캐시 안 함(network-only). 실시간 데이터 stale 방지.
 *  - 교차 출처(kakao 지도/타일·unpkg·fonts·reverb WS) → 패스스루(가로채지 않음).
 *  - 페이지 내비게이션 → network-first, 실패 시 offline.html.
 *  - 동일 출처 정적 자산(/build/ 해시 자산·/icons·/js·css) → stale-while-revalidate.
 *  WebSocket(Reverb)은 fetch 가 아니므로 SW가 가로채지 않음 → 실시간 영향 없음.
 */

const VERSION = 'gps119-v2';
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

/* ─────────────────────────────────────────────────────────────────────────────
 * 웹 푸시 (mobile-app 에픽 N1)
 *
 * Reverb 는 «앱/탭이 떠 있을 때»만 닿는다. 상황실이 관제 탭을 접어두거나 화면을
 * 껐을 때 신규 신고를 놓치는 구멍을 이 경로가 메운다.
 *
 * 페이로드에는 연락처가 없다(ADR-0004). 푸시는 «무슨 일이 났으니 열어라»까지만
 * 말하고, 상세는 앱이 열려서 인가된 채널로 받아온다.
 * ────────────────────────────────────────────────────────────────────────── */

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        // JSON 이 아니면 본문만이라도 띄운다. 조용히 삼키면 «알림이 안 왔다»가 된다.
        payload = { title: 'GPS119', body: event.data ? event.data.text() : '' };
    }

    const url = payload.url || '/';

    event.waitUntil(
        self.registration.showNotification(payload.title || 'GPS119', {
            body: payload.body || '',
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            // 같은 tag 면 기기에서 «대체»된다 — 같은 신고로 알림이 쌓이지 않는다.
            tag: payload.tag || undefined,
            renotify: Boolean(payload.tag),
            // 안전 도메인이라 사용자가 직접 지울 때까지 남긴다(자동 소멸 금지).
            requireInteraction: true,
            data: Object.assign({ url }, payload.data || {}),
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url) || '/';

    // 이미 열려 있는 창이 있으면 새 창을 띄우지 않고 «그 창»을 옮긴다.
    // 관제 중에 탭이 여러 개로 늘어나면 어느 창이 최신인지 알 수 없게 된다.
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.endsWith(target) && 'focus' in client) {
                    return client.focus();
                }
            }

            const open = clientList.find((c) => 'navigate' in c);
            if (open) {
                return open.navigate(target).then((c) => (c ? c.focus() : null));
            }

            return self.clients.openWindow(target);
        })
    );
});
