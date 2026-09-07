// 관제 화면 알림음 (2026-09-07 현장 요청: 「사고 접수되면 토스트 + 알림음」).
//
// 지령 화면(dispatch/index.blade.php)의 사이렌과 같은 방식이다. 번들은 public/js 를 import 할 수
// 없어(vitest.config.js 주석) 여기 따로 둔다 — 두 벌이지만 짧고, 패턴·음량은 화면마다 정하는 값이다.
//
// 🔑 웹뷰·브라우저 모두 «첫 제스처» 전에는 AudioContext 가 잠겨 있다(suspended). 상황실은 관제
//    화면을 켜 두고 다른 창을 보므로, 마운트 때 첫 클릭·터치에서 무음으로 잠금을 풀어 둔다(unlock).
//    울릴 때도 resume() 을 한 번 더 시도한다 — 실패해도 조용히 넘어간다(알림음이 화면을 죽이면 안 된다).

/** 신고 접수: 1200/800Hz 를 0.25초 간격으로 6번 ≈ 1.5초. [시작 오프셋(s), 주파수, 길이(s)] */
export const NEW_REQUEST_PATTERN = [
    [0, 1200, 0.22], [0.25, 800, 0.22], [0.5, 1200, 0.22],
    [0.75, 800, 0.22], [1.0, 1200, 0.22], [1.25, 800, 0.22],
];

/**
 * @param {object} env  AudioContext 를 가진 전역(테스트에서 가짜를 넣는다)
 */
export function createAlertSound(env = globalThis) {
    let ctx = null;

    function context() {
        const AC = env.AudioContext || env.webkitAudioContext;
        if (!AC) return null;

        ctx = ctx || new AC();
        if (ctx.state === 'suspended' && typeof ctx.resume === 'function') {
            Promise.resolve(ctx.resume()).catch(() => {});
        }

        return ctx;
    }

    function tone(c, at, freq, dur, peak) {
        const o = c.createOscillator();
        const g = c.createGain();
        o.connect(g);
        g.connect(c.destination);
        o.type = 'square';
        o.frequency.value = freq;
        g.gain.setValueAtTime(0.001, at);
        g.gain.exponentialRampToValueAtTime(peak, at + 0.02);
        g.gain.setValueAtTime(peak, at + dur - 0.03);
        g.gain.exponentialRampToValueAtTime(0.001, at + dur);
        o.start(at);
        o.stop(at + dur);
    }

    return {
        /** 첫 제스처에서 «무음»으로 잠금만 푼다. @returns {boolean} 컨텍스트를 만들었는가 */
        unlock() {
            try {
                const c = context();
                if (!c) return false;

                const o = c.createOscillator();
                const g = c.createGain();
                g.gain.value = 0;
                o.connect(g);
                g.connect(c.destination);
                o.start();
                o.stop(c.currentTime + 0.01);

                return true;
            } catch (e) {
                return false;
            }
        },

        /** 패턴을 울린다. @returns {number} 예약한 음의 수 (0 이면 못 울렸다) */
        play(pattern = NEW_REQUEST_PATTERN, peak = 0.6) {
            try {
                const c = context();
                if (!c) return 0;

                const t0 = c.currentTime + 0.01;
                pattern.forEach(([offset, freq, dur]) => tone(c, t0 + offset, freq, dur, peak));

                return pattern.length;
            } catch (e) {
                return 0;
            }
        },
    };
}
