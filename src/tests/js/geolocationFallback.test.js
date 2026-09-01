import { describe, it, expect, vi } from 'vitest';
import {
    GEO_OPTIONS,
    GEO_FALLBACK_OPTIONS,
    getCurrentPositionOnce,
} from '../../public/js/components/mapHelpers.js';

/**
 * 구조요청 화면의 위치 취득 (실기기 결함, 2026-09-01).
 *
 * 🔴 예전 옵션은 `timeout: 5000, maximumAge: 0` 이었고, 안드로이드 실기기에서
 *    「시간 초과」가 떴다(Galaxy A36 / Android 16). 콜드 GPS 픽스는 10~30초가 걸린다.
 *    **데스크톱 브라우저는 Wi-Fi 측위라 즉시 떠서 개발 중에는 절대 안 걸린다** —
 *    사람 눈으로 잡을 수 없는 종류라 테스트가 지킨다.
 */

const POSITION = { coords: { latitude: 33.45622, longitude: 126.57504, accuracy: 20 } };

/** GeolocationPositionError 흉내. code 상수는 인스턴스에 붙어 있다. */
function geoError(code) {
    return { code, PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 };
}

function env(getCurrentPosition) {
    return { navigator: { geolocation: { getCurrentPosition } } };
}

describe('기본 옵션', () => {
    it('🔴 콜드 픽스를 기다릴 만큼 준다', () => {
        expect(GEO_OPTIONS.timeout).toBeGreaterThanOrEqual(10000);
    });

    it('🔴 캐시를 거부하지 않는다 — 30초 전 좌표가 «없는 것»보다 낫다', () => {
        expect(GEO_OPTIONS.maximumAge).toBeGreaterThan(0);
    });

    it('2차 시도는 저정밀이다 — 기지국·Wi-Fi 라 거의 즉시 온다', () => {
        expect(GEO_FALLBACK_OPTIONS.enableHighAccuracy).toBe(false);
    });
});

describe('getCurrentPositionOnce', () => {
    it('성공하면 그대로 돌려준다', async () => {
        const spy = vi.fn((ok) => ok(POSITION));

        await expect(getCurrentPositionOnce(GEO_OPTIONS, env(spy))).resolves.toBe(POSITION);
        expect(spy).toHaveBeenCalledTimes(1);
    });

    it('🔴 시간 초과면 저정밀로 한 번 더 시도한다', async () => {
        const spy = vi.fn()
            .mockImplementationOnce((ok, fail) => fail(geoError(3)))
            .mockImplementationOnce((ok) => ok(POSITION));

        await expect(getCurrentPositionOnce(GEO_OPTIONS, env(spy))).resolves.toBe(POSITION);
        expect(spy).toHaveBeenCalledTimes(2);
        expect(spy.mock.calls[1][2].enableHighAccuracy).toBe(false);
    });

    it('🔴 «거부»는 다시 시도하지 않는다 — 프롬프트가 두 번 뜬다', async () => {
        const spy = vi.fn((ok, fail) => fail(geoError(1)));

        await expect(getCurrentPositionOnce(GEO_OPTIONS, env(spy))).rejects.toMatchObject({ code: 1 });
        expect(spy).toHaveBeenCalledTimes(1);
    });

    it('위치를 «쓸 수 없는» 실패도 다시 시도하지 않는다', async () => {
        const spy = vi.fn((ok, fail) => fail(geoError(2)));

        await expect(getCurrentPositionOnce(GEO_OPTIONS, env(spy))).rejects.toMatchObject({ code: 2 });
        expect(spy).toHaveBeenCalledTimes(1);
    });

    it('2차도 시간 초과면 «무한 재시도»하지 않는다', async () => {
        const spy = vi.fn((ok, fail) => fail(geoError(3)));

        await expect(getCurrentPositionOnce(GEO_OPTIONS, env(spy))).rejects.toMatchObject({ code: 3 });
        expect(spy).toHaveBeenCalledTimes(2);
    });

    it('geolocation 이 없으면 UNSUPPORTED', async () => {
        await expect(getCurrentPositionOnce(GEO_OPTIONS, { navigator: {} }))
            .rejects.toThrow('UNSUPPORTED');
    });
});
