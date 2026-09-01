<?php

namespace App\Services;

/**
 * 궤적 점 솎아내기 (M-25). **순수 계산이다** — DB·시간·랜덤을 건드리지 않는다.
 *
 * 🔴 **왜 솎아야 하는가.** 전송 주기가 적응형이라 «정지 중»에도 30~42초마다 점이
 *    쌓인다(02 §3-2). 4시간 행사면 한 사람이 수백~수천 점이고, 참가자 14명이면
 *    지도가 그대로 멈춘다. 그런데 그 점들은 거의 같은 자리라 **그려도 보이지 않는다.**
 *
 * 🔑 거리 필터를 «먼저» 건다. 균등 솎기(N개마다 하나)를 먼저 하면 정지 구간이
 *    예산을 다 먹고 정작 이동 구간이 뭉개진다 — 코너가 잘려 경로가 직선이 된다.
 */
class TrackSimplifier
{
    /**
     * @param  array<int, array{lat: float, lng: float}>  $points  시간순으로 정렬돼 있어야 한다
     * @param  float  $minDistanceM  이 거리 안쪽의 연속점은 «같은 자리»로 본다
     * @param  int  $maxPoints  최종 상한. 2 미만이면 의미가 없다
     * @return array<int, array{lat: float, lng: float}>
     */
    public function simplify(array $points, float $minDistanceM = 10.0, int $maxPoints = 500): array
    {
        $points = array_values($points);
        $count = count($points);

        if ($count <= 2 || $maxPoints < 2) {
            return $count > $maxPoints ? [] : $points;
        }

        $kept = $this->dropNearDuplicates($points, $minDistanceM);

        return count($kept) > $maxPoints ? $this->thin($kept, $maxPoints) : $kept;
    }

    /**
     * 직전에 «남긴» 점에서 minDistance 안쪽이면 버린다.
     *
     * 🔑 직전 «원본» 점이 아니라 직전 «남긴» 점과 비교한다. 원본과 비교하면 1m 씩
     *    100번 움직인 궤적이 100점 그대로 남는다 — 솎이는 게 없다.
     *
     * ⚠️ 마지막 점은 언제나 남긴다. 「지금 어디 있나」가 이 지도의 본래 질문이라,
     *    끝점이 잘리면 궤적이 실제보다 뒤처져 보인다.
     */
    private function dropNearDuplicates(array $points, float $minDistanceM): array
    {
        $last = $points[0];
        $kept = [$last];
        $lastIndex = count($points) - 1;

        foreach ($points as $i => $point) {
            if ($i === 0) {
                continue;
            }

            if ($i === $lastIndex || $this->distanceM($last, $point) >= $minDistanceM) {
                $kept[] = $point;
                $last = $point;
            }
        }

        return $kept;
    }

    /**
     * 균등 간격으로 줄인다. 거리 필터로도 상한을 못 맞춘 «실제로 많이 이동한» 궤적용.
     *
     * 🔑 첫 점과 끝 점은 반드시 포함한다.
     */
    private function thin(array $points, int $maxPoints): array
    {
        $count = count($points);
        $step = ($count - 1) / ($maxPoints - 1);
        $out = [];

        for ($i = 0; $i < $maxPoints; $i++) {
            $out[] = $points[(int) round($i * $step)];
        }

        return $out;
    }

    /**
     * 두 점 사이 거리(m). Haversine.
     *
     * 🔑 위도 보정 없는 «평면» 근사를 쓰지 않는다. 한국 위도(37°)에서 경도 1도는
     *    위도 1도의 약 0.8배라, 보정을 빼면 동서 이동이 25% 부풀어 실제로는 움직인
     *    점이 «안 움직였다»고 솎여 나간다.
     */
    private function distanceM(array $a, array $b): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b['lng'] - (float) $a['lng']);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1.0, sqrt($h)));
    }
}
