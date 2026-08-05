// PWA: 서비스워커 등록 + 설치(홈 화면 추가) 온보딩 (Phase 4).
//
// 위치/알림 권한은 기존 위치공유 흐름(locationShare)에서 in-flow 로 요청하므로
// 여기서는 과한 권한 프롬프트 없이 "홈 화면에 추가"만 안내한다.
// beforeinstallprompt 는 manifest 링크가 있는 참가자 셸(layouts/app)에서만 발생.

import { isNativeApp } from './native/bridge';

const DISMISS_KEY = 'gps119-pwa-install-dismissed';

// 1) 서비스워커 등록 (window load 이후 — 초기 로드 경쟁 방지)
export function registerServiceWorker(env = globalThis) {
    // 네이티브 셸 안에서는 등록하지 않는다(01 §5).
    // 셸이 이미 자체 캐시·오프라인 폴백(errorPath)을 갖고 있어 SW 와 «두 겹»이 된다.
    // 두 겹이면 「어느 쪽 캐시 때문에 옛 화면이 뜨는지」를 추적할 수 없게 되고,
    // 푸시도 네이티브(FCM)와 SW 웹푸시가 동시에 와서 알림이 두 번 뜬다.
    if (isNativeApp(env)) {
        return;
    }

    if (!('serviceWorker' in env.navigator)) return;
    env.addEventListener('load', () => {
        env.navigator.serviceWorker.register('/sw.js').catch((e) => {
            console.warn('[pwa] SW 등록 실패', e);
        });
    });
}

// 2) 설치 배너
let deferredPrompt = null;

function buildBanner() {
    if (document.getElementById('pwa-install-banner')) return null;
    const el = document.createElement('div');
    el.id = 'pwa-install-banner';
    el.style.cssText = [
        'position:fixed', 'left:12px', 'right:12px', 'bottom:12px', 'z-index:9999',
        'background:#fff', 'border:1px solid #e2e8f0', 'border-radius:16px',
        'box-shadow:0 10px 25px -5px rgba(0,0,0,.15)', 'padding:14px 16px',
        'display:flex', 'align-items:center', 'gap:12px', 'max-width:460px', 'margin:0 auto',
        'font-family:system-ui,-apple-system,sans-serif',
    ].join(';');
    el.innerHTML = `
        <div style="width:40px;height:40px;background:#2563EB;border-radius:10px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div style="flex:1 1 auto;min-width:0;">
            <div style="font-size:14px;font-weight:700;color:#0f172a;">GPS119 앱 설치</div>
            <div style="font-size:12px;color:#64748b;">홈 화면에 추가하면 더 빠르게 신고할 수 있어요.</div>
        </div>
        <button id="pwa-install-accept" style="flex:0 0 auto;background:#2563EB;color:#fff;border:0;padding:9px 14px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">설치</button>
        <button id="pwa-install-dismiss" aria-label="닫기" style="flex:0 0 auto;background:transparent;border:0;color:#94a3b8;font-size:18px;cursor:pointer;padding:4px;">&times;</button>
    `;
    document.body.appendChild(el);
    return el;
}

function removeBanner() {
    const el = document.getElementById('pwa-install-banner');
    if (el) el.remove();
}

export function initInstallOnboarding() {
    // 이미 앱으로 실행 중이면 「앱 설치」 안내는 무의미하다(혼란만 준다).
    if (isNativeApp()) return;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); // 브라우저 기본 미니바 억제 → 커스텀 배너로 유도
        if (localStorage.getItem(DISMISS_KEY)) return; // 닫기 기억(1회)
        deferredPrompt = e;

        const banner = buildBanner();
        if (!banner) return;

        banner.querySelector('#pwa-install-accept').addEventListener('click', async () => {
            removeBanner();
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            try { await deferredPrompt.userChoice; } catch (err) { /* noop */ }
            deferredPrompt = null;
        });
        banner.querySelector('#pwa-install-dismiss').addEventListener('click', () => {
            localStorage.setItem(DISMISS_KEY, '1');
            removeBanner();
        });
    });

    // 설치 완료 시 배너 정리
    window.addEventListener('appinstalled', () => {
        localStorage.setItem(DISMISS_KEY, '1');
        removeBanner();
    });
}

export function initPwa() {
    registerServiceWorker();
    initInstallOnboarding();
}

export default initPwa;
