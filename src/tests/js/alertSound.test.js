import { describe, it, expect, vi } from 'vitest';
import { createAlertSound, NEW_REQUEST_PATTERN } from '../../resources/js/control/alertSound.js';

/**
 * 관제 알림음 (2026-09-07 현장: 「사고 접수되면 토스트 + 알림음」).
 * 브라우저 없이 가짜 AudioContext 로 «무엇을 예약했는가»만 본다. 실제 소리는 실기기 QA.
 */
function fakeAudio({ state = 'suspended' } = {}) {
    const oscillators = [];
    class FakeContext {
        constructor() {
            this.state = state;
            this.currentTime = 10;
            this.destination = {};
            this.resume = vi.fn(async () => { this.state = 'running'; });
        }
        createOscillator() {
            const o = { type: '', frequency: { value: 0 }, connect: vi.fn(), start: vi.fn(), stop: vi.fn() };
            oscillators.push(o);
            return o;
        }
        createGain() {
            return {
                gain: { value: 1, setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() },
                connect: vi.fn(),
            };
        }
    }
    const env = { AudioContext: FakeContext };

    return { env, oscillators };
}

describe('createAlertSound', () => {
    it('unlock 은 무음(gain 0) 오실레이터 하나로 컨텍스트를 깨운다', () => {
        const { env, oscillators } = fakeAudio();
        const sound = createAlertSound(env);

        expect(sound.unlock()).toBe(true);
        expect(oscillators).toHaveLength(1);
        expect(oscillators[0].start).toHaveBeenCalled();
    });

    it('play 는 패턴의 음 수만큼 예약하고 주파수를 그대로 쓴다', () => {
        const { env, oscillators } = fakeAudio({ state: 'running' });
        const sound = createAlertSound(env);

        expect(sound.play()).toBe(NEW_REQUEST_PATTERN.length);
        expect(oscillators.map((o) => o.frequency.value)).toEqual(NEW_REQUEST_PATTERN.map(([, f]) => f));
        expect(oscillators.every((o) => o.type === 'square')).toBe(true);
    });

    it('잠겨 있으면(suspended) 울리기 전에 resume 을 시도한다', () => {
        const { env } = fakeAudio({ state: 'suspended' });
        const sound = createAlertSound(env);
        sound.play();

        // 컨텍스트는 하나만 만들고 재사용한다
        expect(sound.play()).toBe(NEW_REQUEST_PATTERN.length);
    });

    it('AudioContext 가 없는 환경에서는 조용히 0/false — 화면을 죽이지 않는다', () => {
        const sound = createAlertSound({});

        expect(sound.unlock()).toBe(false);
        expect(sound.play()).toBe(0);
    });

    it('오실레이터 생성이 던져도 삼킨다', () => {
        class Broken { constructor() { this.state = 'running'; this.currentTime = 0; this.destination = {}; } createOscillator() { throw new Error('nope'); } createGain() { return {}; } }
        const sound = createAlertSound({ AudioContext: Broken });

        expect(sound.play()).toBe(0);
        expect(sound.unlock()).toBe(false);
    });
});
