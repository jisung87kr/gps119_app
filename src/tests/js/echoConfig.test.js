import { describe, it, expect } from 'vitest';
import { resolveReverbConfig } from '../../resources/js/echo.js';

/**
 * Echo 접속 주소는 «현재 페이지»에서 유도된다.
 *
 * 예전엔 VITE_REVERB_HOST/PORT 를 .env 에 박아 뒀고, 그게 두 번 사고를 냈다:
 *   ① 노트북 LAN IP 가 바뀌자 실기기에서 실시간이 조용히 죽었다(빌드 타임 상수라 재빌드 전까지 안 고쳐진다)
 *   ② https 를 켜자 ws:// 라 혼합 콘텐츠로 차단됐다
 *
 * 🔑 이 파일이 고정하는 계약: **아무 설정 없이도, 페이지가 떠 있는 주소로 붙는다.**
 *    Apache 가 같은 오리진에서 /app 을 reverb 로 프록시하므로 그게 항상 맞다.
 */
describe('Echo 접속 설정 — 페이지 주소에서 유도', () => {
    it('🔑 https 페이지면 wss + 같은 host·port 로 붙는다', () => {
        const cfg = resolveReverbConfig({}, {
            protocol: 'https:', hostname: '172.30.1.11', port: '9051',
        });

        expect(cfg).toEqual({ scheme: 'https', forceTLS: true, host: '172.30.1.11', port: 9051 });
    });

    it('🔑 http 페이지면 ws + 같은 host·port 로 붙는다', () => {
        const cfg = resolveReverbConfig({}, {
            protocol: 'http:', hostname: 'localhost', port: '9050',
        });

        expect(cfg).toEqual({ scheme: 'http', forceTLS: false, host: 'localhost', port: 9050 });
    });

    it('🔑 호스트가 바뀌어도 «설정 변경 없이» 따라간다', () => {
        // LAN IP 가 바뀌든 .local 이든 운영 도메인이든, 재빌드 없이 맞아야 한다.
        const hosts = ['192.168.0.57', '172.30.1.11', 'jisungui-macbookpro16.local', 'app.gps119.co.kr'];

        for (const hostname of hosts) {
            const cfg = resolveReverbConfig({}, { protocol: 'https:', hostname, port: '9051' });
            expect(cfg.host).toBe(hostname);
        }
    });

    it('기본 포트(443)면 location.port 가 비므로 scheme 에서 되살린다', () => {
        // 운영 도메인이 여기 해당한다. 0 이나 NaN 이 되면 연결이 조용히 실패한다.
        const cfg = resolveReverbConfig({}, { protocol: 'https:', hostname: 'app.gps119.co.kr', port: '' });

        expect(cfg.port).toBe(443);
    });

    it('기본 포트(80)도 마찬가지다', () => {
        const cfg = resolveReverbConfig({}, { protocol: 'http:', hostname: 'example.test', port: '' });

        expect(cfg.port).toBe(80);
    });
});

describe('Echo 접속 설정 — 명시 override', () => {
    it('VITE_REVERB_* 가 있으면 그쪽이 이긴다', () => {
        // WS 만 다른 곳(직노출 9055 등)으로 뺄 여지는 남겨 둔다.
        const cfg = resolveReverbConfig(
            { VITE_REVERB_HOST: 'reverb.example', VITE_REVERB_PORT: '9055', VITE_REVERB_SCHEME: 'http' },
            { protocol: 'https:', hostname: '172.30.1.11', port: '9051' },
        );

        expect(cfg).toEqual({ scheme: 'http', forceTLS: false, host: 'reverb.example', port: 9055 });
    });

    it('override 는 «부분»으로도 먹는다 — 준 것만 이긴다', () => {
        const cfg = resolveReverbConfig(
            { VITE_REVERB_PORT: '9055' },
            { protocol: 'https:', hostname: '172.30.1.11', port: '9051' },
        );

        expect(cfg.host).toBe('172.30.1.11');   // 페이지에서 유도
        expect(cfg.port).toBe(9055);            // override
        expect(cfg.forceTLS).toBe(true);
    });

    it('빈 문자열 override 는 «없음»으로 취급한다', () => {
        // .env 에 키만 남기고 값을 비우는 일이 흔하다. 그때 host 가 '' 가 되면 연결이 죽는다.
        const cfg = resolveReverbConfig(
            { VITE_REVERB_HOST: '', VITE_REVERB_PORT: '' },
            { protocol: 'https:', hostname: '172.30.1.11', port: '9051' },
        );

        expect(cfg.host).toBe('172.30.1.11');
        expect(cfg.port).toBe(9051);
    });
});
