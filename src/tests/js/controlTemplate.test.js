import { describe, it, expect } from 'vitest';
import { compile } from '@vue/compiler-dom';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 관제 SPA 의 템플릿은 «문자열»이라 런타임에 컴파일된다 — Vite 빌드가 성공해도
 * 템플릿 오타는 브라우저를 열어야 드러난다. 여기서 컴파일러를 직접 돌려 잡는다.
 */
describe('관제 — 템플릿 컴파일', () => {
    it('🔑 ControlApp.template 이 오류 없이 컴파일된다', () => {
        const errors = [];
        compile(ControlApp.template, { onError: (e) => errors.push(e) });

        expect(errors.map((e) => e.message)).toEqual([]);
    });
});
