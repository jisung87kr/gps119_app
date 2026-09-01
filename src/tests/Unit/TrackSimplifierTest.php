<?php

namespace Tests\Unit;

use App\Services\TrackSimplifier;
use PHPUnit\Framework\TestCase;

/**
 * 궤적 솎아내기 (M-25).
 *
 * 🔑 값이 틀려도 «지도는 멀쩡해 보인다» — 선이 그려지긴 하니까. 사람 눈으로는
 *    「경로가 약간 다르다」를 못 잡는다. 그래서 순수 함수로 떼어 테스트가 지킨다.
 */
class TrackSimplifierTest extends TestCase
{
    private TrackSimplifier $simplifier;

    protected function setUp(): void
    {
        $this->simplifier = new TrackSimplifier();
    }

    /** 위도 1도 ≈ 111km. 0.0001도 ≈ 11m */
    private function line(int $n, float $stepDeg = 0.0001): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = ['lat' => 37.5665 + $i * $stepDeg, 'lng' => 126.9780];
        }

        return $out;
    }

    public function test_점이_둘_이하면_그대로_둔다(): void
    {
        $points = $this->line(2);

        $this->assertSame($points, $this->simplifier->simplify($points));
    }

    public function test_🔴_정지_구간은_한_점으로_줄어든다(): void
    {
        // 적응형 주기 탓에 «같은 자리»에서도 30~42초마다 점이 쌓인다.
        $points = array_fill(0, 100, ['lat' => 37.5665, 'lng' => 126.9780]);

        $result = $this->simplifier->simplify($points, 10.0);

        // 첫 점 + 끝 점(끝은 언제나 남긴다)
        $this->assertCount(2, $result);
    }

    public function test_🔴_끝점은_언제나_남는다(): void
    {
        // 「지금 어디 있나」가 이 지도의 본래 질문이다. 끝이 잘리면 뒤처져 보인다.
        $points = array_fill(0, 50, ['lat' => 37.5665, 'lng' => 126.9780]);
        $points[] = ['lat' => 37.5666, 'lng' => 126.9781];

        $result = $this->simplifier->simplify($points, 10.0);

        $this->assertSame(end($points), end($result));
    }

    public function test_🔴_직전_«남긴»_점과_비교한다(): void
    {
        // 1m 씩 100번 움직인 궤적. 원본과 비교하면 하나도 안 솎인다.
        $points = $this->line(100, 0.000009); // ≈ 1m 간격

        $result = $this->simplifier->simplify($points, 10.0);

        $this->assertLessThan(20, count($result));
        $this->assertGreaterThan(2, count($result));
    }

    public function test_실제로_이동한_점은_남는다(): void
    {
        $points = $this->line(20, 0.0002); // ≈ 22m 간격

        $result = $this->simplifier->simplify($points, 10.0);

        $this->assertCount(20, $result);
    }

    public function test_상한을_넘으면_균등하게_줄인다(): void
    {
        $points = $this->line(1000, 0.0002);

        $result = $this->simplifier->simplify($points, 10.0, 100);

        $this->assertCount(100, $result);
        $this->assertSame($points[0], $result[0]);
        $this->assertSame(end($points), end($result));
    }

    public function test_🔴_경도는_위도로_보정한다(): void
    {
        // 한국 위도(37°)에서 경도 1도는 위도 1도의 약 0.8배다. 보정을 빼면
        // 동서 이동이 부풀려져, 실제로 «움직인» 점이 안 움직였다고 솎인다.
        // 경도 0.0001도 ≈ 8.8m 이므로 10m 문턱에서는 «안 움직인» 것으로 봐야 한다.
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $points[] = ['lat' => 37.5665, 'lng' => 126.9780 + $i * 0.0001];
        }

        $result = $this->simplifier->simplify($points, 10.0);

        // 보정이 없으면(11.1m 로 계산) 전부 남는다. 보정이 있으면 대부분 솎인다.
        $this->assertLessThan(10, count($result));
    }

    public function test_상한이_2면_처음과_끝만_남는다(): void
    {
        $points = $this->line(50, 0.0002);

        $result = $this->simplifier->simplify($points, 0.0, 2);

        $this->assertCount(2, $result);
        $this->assertSame($points[0], $result[0]);
        $this->assertSame(end($points), end($result));
    }
}
