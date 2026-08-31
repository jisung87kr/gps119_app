import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createLocationSharer } from '../../public/js/components/locationShare.js';

/**
 * 참가자 위치 자동공유 (FE-2.2) 단위 테스트.
 *
 * 내부 함수(distanceM/shouldSend/flushBuffer)는 export 되지 않으므로 공개 API 로만
 * 구동한다 — 구현이 아니라 «계약»을 고정하기 위해서다.
 *
 * 브라우저가 필요 없다. 전역 의존이 window.axios · navigator.geolocation 둘뿐이라
 * 둘만 스텁하면 순수 Node 에서 돈다.
 *
 * 2026-08-05 에 429 백오프를 «실제 브라우저 + 실제 서버»로 검증하는 데 약 2분이
 * 걸렸다(스로틀을 임시로 낮추고 15·18·65초씩 실시간 대기). 그 로직은 시간과 상태의
 * 순수 함수라 가짜 타이머로 수십 ms 면 끝난다 — 이 파일이 그 대체물이다.
 */

const MOVE_INTERVAL_MS = 5000;
const STATIONARY_INTERVAL_MS = 30000;
const THROTTLE_BACKOFF_MS = 20000;

/** 위·경도 1도 ≈ 111km. 10m 임계를 넘기려면 0.0002도(≈22m)면 충분. */
const MOVED = 0.0002;

function makeError(status) {
    return { response: { status } };
}

/**
 * 테스트용 하네스.
 * - feed(i): watchPosition 콜백에 위치를 1건 흘린다(i 만큼 북쪽으로 이동)
 * - tick(ms): 가짜 시계를 진행시키며 대기 중인 프라미스도 함께 흘린다
 */
function harness({ post } = {}) {
    let onPosition = null;

    const postMock = post ?? vi.fn().mockResolvedValue({});
    const patchMock = vi.fn().mockResolvedValue({});

    globalThis.window = { axios: { post: postMock, patch: patchMock } };
    globalThis.navigator = {
        geolocation: {
            watchPosition: (success) => { onPosition = success; return 1; },
            clearWatch: vi.fn(),
        },
    };

    const sharer = createLocationSharer({ projectId: 7 });

    return {
        sharer,
        post: postMock,
        patch: patchMock,
        clearWatch: globalThis.navigator.geolocation.clearWatch,
        isWatching: () => onPosition !== null,
        feed(step = 0, accuracy = 15) {
            if (!onPosition) throw new Error('watchPosition 미등록 — enable() 를 먼저 호출할 것');
            onPosition({
                coords: {
                    latitude: 37.8456 + step * MOVED,
                    longitude: 127.7428,
                    accuracy,
                    heading: null,
                    speed: null,
                },
                timestamp: Date.now(),
            });
        },
        async tick(ms = 0) {
            await vi.advanceTimersByTimeAsync(ms);
        },
    };
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-08-05T00:00:00.000Z'));
});

afterEach(() => {
    vi.useRealTimers();
    delete globalThis.window;
    delete globalThis.navigator;
});

describe('전송 주기 판정', () => {
    it('첫 위치는 즉시 전송한다', async () => {
        const h = harness();
        await h.sharer.enable();

        h.feed(0);
        await h.tick();

        expect(h.post).toHaveBeenCalledTimes(1);
        expect(h.post.mock.calls[0][0]).toBe('/api/events/7/location');
    });

    it('이동해도 5초가 안 지났으면 보내지 않는다', async () => {
        const h = harness();
        await h.sharer.enable();

        h.feed(0);
        await h.tick();
        expect(h.post).toHaveBeenCalledTimes(1);

        await h.tick(MOVE_INTERVAL_MS - 1000);
        h.feed(5); // 충분히 이동했지만 4초밖에 안 지났다
        await h.tick();

        expect(h.post).toHaveBeenCalledTimes(1);
    });

    it('5초 경과 + 임계 이상 이동이면 보낸다', async () => {
        const h = harness();
        await h.sharer.enable();

        h.feed(0);
        await h.tick();

        await h.tick(MOVE_INTERVAL_MS);
        h.feed(5);
        await h.tick();

        expect(h.post).toHaveBeenCalledTimes(2);
    });

    it('제자리에 있어도 30초마다 하트비트를 보낸다', async () => {
        const h = harness();
        await h.sharer.enable();

        h.feed(0);
        await h.tick();

        // 10초 경과 — 움직이지 않았으므로 아직 안 보낸다
        await h.tick(10_000);
        h.feed(0);
        await h.tick();
        expect(h.post).toHaveBeenCalledTimes(1);

        // 30초 경과 — 하트비트
        await h.tick(STATIONARY_INTERVAL_MS - 10_000);
        h.feed(0);
        await h.tick();
        expect(h.post).toHaveBeenCalledTimes(2);
    });
});

describe('429 백오프', () => {
    it('429 를 받으면 백오프에 들어가고 전용 메시지를 노출한다', async () => {
        const post = vi.fn().mockRejectedValue(makeError(429));
        const h = harness({ post });
        await h.sharer.enable();

        h.feed(0);
        await h.tick();

        expect(h.sharer.state.error).toBe('전송 속도 조절 중');
        expect(h.sharer.state.bufferedCount).toBe(1); // 페이로드는 버려지지 않는다
        expect(h.sharer.state.sentCount).toBe(0);
    });

    it('백오프 창 동안에는 아무리 위치가 들어와도 전송하지 않는다', async () => {
        const post = vi.fn().mockRejectedValue(makeError(429));
        const h = harness({ post });
        await h.sharer.enable();

        h.feed(0);
        await h.tick();
        const callsAtBackoff = post.mock.calls.length;

        // 창이 끝나기 전까지 5초 간격으로 계속 밀어넣는다
        for (let i = 1; i <= 3; i++) {
            await h.tick(MOVE_INTERVAL_MS);
            h.feed(i * 5);
            await h.tick();
        }

        expect(post).toHaveBeenCalledTimes(callsAtBackoff); // 단 한 번도 늘지 않아야 한다
    });

    it('백오프 창이 지나면 전송을 재개하고 버퍼부터 복구한다', async () => {
        const post = vi.fn().mockRejectedValueOnce(makeError(429)).mockResolvedValue({});
        const h = harness({ post });
        await h.sharer.enable();

        h.feed(0);
        await h.tick();
        expect(h.sharer.state.bufferedCount).toBe(1);

        await h.tick(THROTTLE_BACKOFF_MS);
        h.feed(5);
        await h.tick();

        expect(h.sharer.state.bufferedCount).toBe(0); // 밀린 것이 복구됐다
        expect(post.mock.calls.length).toBeGreaterThan(1);
    });

    it('버퍼 복구 중 429 를 만나면 현재 건까지 멈춘다 — 한 건을 더 때리지 않는다', async () => {
        // 1) 먼저 429 로 버퍼에 1건 쌓는다
        const post = vi.fn().mockRejectedValue(makeError(429));
        const h = harness({ post });
        await h.sharer.enable();

        h.feed(0);
        await h.tick();
        expect(h.sharer.state.bufferedCount).toBe(1);

        // 2) 백오프가 풀린 뒤 재개 — 서버는 여전히 429 를 준다
        await h.tick(THROTTLE_BACKOFF_MS);
        post.mockClear();

        h.feed(5);
        await h.tick();

        // 버퍼 1건을 시도해 429 를 받은 시점에서 멈춰야 한다.
        // 이 확인이 없으면 현재 건까지 보내 2회가 된다.
        expect(post).toHaveBeenCalledTimes(1);
        expect(h.sharer.state.error).toBe('전송 속도 조절 중');
    });

    it('429 가 아닌 실패는 백오프를 걸지 않는다 — 다음 주기에 바로 재시도한다', async () => {
        const post = vi.fn().mockRejectedValue(makeError(500));
        const h = harness({ post });
        await h.sharer.enable();

        h.feed(0);
        await h.tick();
        expect(h.sharer.state.error).toBe('전송 지연(재시도 중)');

        await h.tick(MOVE_INTERVAL_MS);
        h.feed(5);
        await h.tick();

        expect(post.mock.calls.length).toBeGreaterThan(1); // 백오프였다면 멈췄을 것
    });
});

describe('페이로드', () => {
    it('accuracy 를 정수로 클램프하고 heading/speed 가 없으면 null 로 보낸다', async () => {
        const h = harness();
        await h.sharer.enable();

        h.feed(0, 99_999.7); // 상한(65535) 초과 + 소수
        await h.tick();

        const body = h.post.mock.calls[0][1];
        expect(body.accuracy).toBe(65535);
        expect(body.heading).toBeNull();
        expect(body.speed).toBeNull();
        expect(body.latitude).toBeCloseTo(37.8456, 6);
        expect(typeof body.recorded_at).toBe('string');
    });
});

describe('공유 토글', () => {
    it('enable 은 sharing on 을 PATCH 하고 watch 를 시작한다', async () => {
        const h = harness();
        await h.sharer.enable();

        expect(h.patch).toHaveBeenCalledWith(
            '/api/events/7/sharing',
            { sharing_location: true },
            expect.anything()
        );
        expect(h.isWatching()).toBe(true);
        expect(h.sharer.state.sharing).toBe(true);
    });

    it('disable 은 watch 를 멈추고 sharing off 를 PATCH 한다', async () => {
        const h = harness();
        await h.sharer.enable();
        await h.sharer.disable();

        expect(h.clearWatch).toHaveBeenCalled();
        expect(h.patch).toHaveBeenLastCalledWith(
            '/api/events/7/sharing',
            { sharing_location: false },
            expect.anything()
        );
        expect(h.sharer.state.sharing).toBe(false);
    });
});

describe('취득 위임 (N3 / 02 §3-3)', () => {
    /** 주입되는 트래커의 최소 계약만 흉내낸다. */
    function fakeTracker(over = {}) {
        return {
            kind: 'native',
            supportsBackground: true,
            supported: true,
            start: vi.fn(),
            stop: vi.fn(),
            ...over,
        };
    }

    /** enable() 이 PATCH 를 부르므로 axios 스텁이 있어야 한다(없으면 3초 폴링에 걸린다). */
    function stubEnv() {
        globalThis.window = { axios: { post: vi.fn().mockResolvedValue({}), patch: vi.fn().mockResolvedValue({}) } };
        const watchPosition = vi.fn(() => 1);
        globalThis.navigator = { geolocation: { watchPosition, clearWatch: vi.fn() } };

        return { watchPosition };
    }

    it('🔑 트래커를 주면 navigator.geolocation 을 쓰지 않는다', async () => {
        // 이 파일은 «언제 보내는가»만 갖는다. 취득 경로가 둘로 갈리면
        // 셸을 바꿀 때마다 전송 로직이 흔들린다.
        const { watchPosition } = stubEnv();
        const tracker = fakeTracker();

        await createLocationSharer({ projectId: 1, tracker }).enable();

        expect(tracker.start).toHaveBeenCalledTimes(1);
        expect(watchPosition).not.toHaveBeenCalled();
    });

    it('트래커를 안 주면 기존 웹 경로를 그대로 쓴다', async () => {
        // 하위호환. 앱이 아니거나 구버전 셸이면 여기로 떨어져야 한다.
        const { watchPosition } = stubEnv();

        await createLocationSharer({ projectId: 1 }).enable();

        expect(watchPosition).toHaveBeenCalledTimes(1);
    });

    it('🔑 restart 는 공유 상태를 건드리지 않고 옵션을 트래커에 넘긴다', async () => {
        // disable()+enable() 로 하면 sharing 이 잠깐 «꺼짐»으로 서버에 기록되고,
        // 관제는 그 순간 이 사람을 「공유 꺼짐」으로 본다. 구조 앱에서 버튼 한 번에
        // 위치가 끊기면 안 된다.
        stubEnv();
        const tracker = fakeTracker();
        const sharer = createLocationSharer({ projectId: 1, tracker });

        await sharer.enable();
        const patchCalls = globalThis.window.axios.patch.mock.calls.length;

        await sharer.restart({ requestPermissions: true });

        expect(tracker.stop).toHaveBeenCalled();
        expect(tracker.start).toHaveBeenLastCalledWith(
            expect.any(Function), expect.any(Function), { requestPermissions: true },
        );
        expect(globalThis.window.axios.patch.mock.calls.length).toBe(patchCalls);
        expect(sharer.state.sharing).toBe(true);
    });

    it('disable 은 트래커를 멈춘다', async () => {
        stubEnv();
        const tracker = fakeTracker();
        const sharer = createLocationSharer({ projectId: 1, tracker });

        await sharer.enable();
        await sharer.disable();

        expect(tracker.stop).toHaveBeenCalled();
    });

    it('트래커가 지원 불가라고 하면 공유를 시작하지 않는다', async () => {
        stubEnv();
        const tracker = fakeTracker({ supported: false });
        const sharer = createLocationSharer({ projectId: 1, tracker });

        await sharer.enable();

        expect(tracker.start).not.toHaveBeenCalled();
        expect(sharer.state.permission).toBe('unsupported');
    });
});
