import { describe, it, expect, afterEach } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 기록 CSV 다운로드 — 웹뷰에서는 «되는 척»을 하지 않는다 (M-21).
 *
 * 웹뷰는 파일 다운로드를 기본 처리하지 않는다. 링크를 눌러도 아무 일도 일어나지 않고
 * 오류도 안 난다 — 상황실에겐 「눌렀는데 안 받아진다」이고, 원인을 찾을 단서가 없다.
 *
 * 🔑 이 파일이 고정하는 계약: 판정 기준은 「앱인가」가 아니라 **「그 셸이 다운로드를
 *    아는가」**다. 나중에 셸이 file-download 를 지원하면 «웹 재배포 없이» 링크가 살아나야
 *    한다 — 웹이 앱보다 최신인 상태가 정상이기 때문이다(native/bridge.js).
 */
describe('관제 — 기록 CSV 다운로드 가능 판정', () => {
    const canDownload = () => ControlApp.computed.reportsDownloadable.call({});

    /** 셸이 심는 전역을 흉내낸다. capabilities=null 이면 «구버전 셸»(전역 자체가 없음). */
    function pretendApp(capabilities) {
        globalThis.Capacitor = { isNativePlatform: () => true };
        if (capabilities !== null) {
            globalThis.__gps119Native = { version: '1.0.0', capabilities };
        }
    }

    afterEach(() => {
        delete globalThis.Capacitor;
        delete globalThis.__gps119Native;
    });

    it('웹 브라우저에서는 받을 수 있다', () => {
        // Capacitor 전역이 없다 = 웹. 여기서 막으면 «있던 기능»이 사라진다.
        expect(canDownload()).toBe(true);
    });

    it('🔑 앱인데 다운로드를 모르면 못 받는다', () => {
        pretendApp(['background-location']);

        expect(canDownload()).toBe(false);
    });

    it('🔑 앱이라도 file-download 를 알면 받을 수 있다', () => {
        // 「앱이면 무조건 숨김」이었다면 이 케이스가 영원히 안 열린다.
        pretendApp(['file-download']);

        expect(canDownload()).toBe(true);
    });

    it('기능 목록을 안 알리는 구버전 셸은 «모르는 것»으로 본다', () => {
        // 없는 키는 오류가 아니라 「아직 모르는 기능」이다. 되는 척하면 안 된다.
        pretendApp(null);

        expect(canDownload()).toBe(false);
    });

    it('다운로드 주소 자체는 바뀌지 않는다', () => {
        // 판정이 붙었다고 URL 규약까지 흔들리면 서버 라우트와 어긋난다.
        const url = ControlApp.methods.reportUrl.call({ selectedProjectId: 8 }, 'tracks');

        expect(url).toBe('/api/events/8/report/tracks.csv');
    });
});
