import { describe, it, expect } from 'vitest';
import {
    formatCoords, kakaoMapUrl, shareText, clampMenuPosition, MENU_SIZE,
    pickAddress, locationText,
} from '../../resources/js/control/mapContextMenu.js';

/**
 * 관제 지도 우클릭 메뉴 — 순수 로직.
 *
 * 좌표 문자열·공유 URL 은 무전/메신저로 현장에 전달되는 값이라, 한 자리 틀리면
 * 엉뚱한 곳으로 구조를 보낸다 — 화면으로는 안 걸리는 종류라 여기서 고정한다.
 */
describe('관제 우클릭 메뉴 — 좌표·공유', () => {
    describe('formatCoords', () => {
        it('기본 6자리로 포맷한다', () => {
            expect(formatCoords(37.5665, 126.978)).toBe('37.566500, 126.978000');
        });

        it('반올림한다', () => {
            expect(formatCoords(37.56659999, 126.9781234, 4)).toBe('37.5666, 126.9781');
        });

        it('음수(남반구·서반구)도 다룬다', () => {
            expect(formatCoords(-33.8688, -70.5, 2)).toBe('-33.87, -70.50');
        });

        it('유효하지 않은 값은 빈 문자열', () => {
            expect(formatCoords(null, 126.978)).toBe('');
            expect(formatCoords(NaN, NaN)).toBe('');
            expect(formatCoords(undefined, undefined)).toBe('');
        });
    });

    describe('kakaoMapUrl', () => {
        it('좌표 링크를 6자리로 만든다', () => {
            expect(kakaoMapUrl(37.5665, 126.978, '위치'))
                .toBe('https://map.kakao.com/link/map/%EC%9C%84%EC%B9%98,37.566500,126.978000');
        });

        it('라벨의 쉼표·공백을 인코딩한다 — URL 구조가 깨지지 않게', () => {
            const url = kakaoMapUrl(37.5, 127, 'A, B');
            expect(url).toContain('/link/map/A%2C%20B,37.500000,127.000000');
        });
    });

    describe('shareText', () => {
        it('라벨·좌표·링크를 3줄로 묶는다', () => {
            const t = shareText(37.5665, 126.978);
            expect(t.split('\n')).toEqual([
                '구조 지점',
                '37.566500, 126.978000',
                'https://map.kakao.com/link/map/%EA%B5%AC%EC%A1%B0%20%EC%A7%80%EC%A0%90,37.566500,126.978000',
            ]);
        });
    });

    describe('pickAddress', () => {
        it('도로명 주소를 우선한다', () => {
            const result = [{
                road_address: { address_name: '서울 중구 세종대로 110' },
                address: { address_name: '서울 중구 태평로1가 31' },
            }];
            expect(pickAddress(result)).toBe('서울 중구 세종대로 110');
        });

        it('도로명이 없으면 지번으로 폴백', () => {
            const result = [{ road_address: null, address: { address_name: '서울 중구 태평로1가 31' } }];
            expect(pickAddress(result)).toBe('서울 중구 태평로1가 31');
        });

        it('둘 다 없거나 빈 결과면 null', () => {
            expect(pickAddress([])).toBeNull();
            expect(pickAddress(null)).toBeNull();
            expect(pickAddress([{ road_address: null, address: null }])).toBeNull();
        });
    });

    describe('locationText', () => {
        it('주소가 있으면 주소 + 좌표(두 줄)', () => {
            expect(locationText('서울 중구 세종대로 110', 37.5665, 126.978))
                .toBe('서울 중구 세종대로 110\n37.566500, 126.978000');
        });

        it('주소가 없으면 좌표만', () => {
            expect(locationText(null, 37.5665, 126.978)).toBe('37.566500, 126.978000');
        });
    });

    describe('clampMenuPosition', () => {
        const container = { width: 800, height: 600 };

        it('여유가 있으면 우클릭 지점 그대로', () => {
            expect(clampMenuPosition({ x: 100, y: 120 }, MENU_SIZE, container))
                .toEqual({ x: 100, y: 120 });
        });

        it('오른쪽 경계를 넘으면 안쪽으로 당긴다', () => {
            const { x } = clampMenuPosition({ x: 790, y: 100 }, MENU_SIZE, container);
            expect(x).toBe(800 - MENU_SIZE.width - 8);
        });

        it('아래 경계를 넘으면 위로 당긴다', () => {
            const { y } = clampMenuPosition({ x: 100, y: 595 }, MENU_SIZE, container);
            expect(y).toBe(600 - MENU_SIZE.height - 8);
        });

        it('음수 좌표는 margin 으로 밀어낸다', () => {
            expect(clampMenuPosition({ x: -50, y: -50 }, MENU_SIZE, container))
                .toEqual({ x: 8, y: 8 });
        });

        it('컨테이너가 메뉴보다 작아도 margin 아래로는 안 간다', () => {
            expect(clampMenuPosition({ x: 10, y: 10 }, MENU_SIZE, { width: 0, height: 0 }))
                .toEqual({ x: 8, y: 8 });
        });
    });
});
