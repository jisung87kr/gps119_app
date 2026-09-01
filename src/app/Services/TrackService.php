<?php

namespace App\Services;

use App\Models\LocationPing;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * 관제 지도용 이동 궤적 조회 (M-25).
 *
 * 🔑 **계산은 TrackSimplifier 에 있다.** 여기는 «읽어서 넘기는» 일만 한다 —
 *    섞으면 솎아내기 로직이 DB 없이는 검증 불가능해진다.
 */
class TrackService
{
    /** 한 번에 볼 수 있는 시간 범위 상한(분). 행사 하루치를 통째로 긁는 것을 막는다. */
    public const MAX_MINUTES = 720;

    /** 사람당 점 상한. 14명 × 500점 = 7,000점이면 카카오맵이 감당한다. */
    public const MAX_POINTS = 500;

    public function __construct(private TrackSimplifier $simplifier) {}

    /**
     * @return array<int, array{user_id: int, points: array, count: int, from: string, to: string}>
     */
    public function forProject(Project $project, Carbon $since, ?array $userIds = null): array
    {
        $query = LocationPing::query()
            ->where('project_id', $project->id)
            ->where('recorded_at', '>=', $since)
            // 🔑 (project_id, recorded_at) 인덱스를 그대로 탄다.
            ->orderBy('recorded_at')
            ->select(['user_id', 'latitude', 'longitude', 'recorded_at']);

        if ($userIds) {
            $query->whereIn('user_id', $userIds);
        }

        $byUser = [];
        // 🔴 chunk 로 읽는다. 행사 전체를 한 번에 메모리에 올리면 12시간 × 14명에서 터진다.
        $query->chunk(2000, function ($rows) use (&$byUser) {
            foreach ($rows as $row) {
                $byUser[$row->user_id][] = [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                    't' => $row->recorded_at,
                ];
            }
        });

        $tracks = [];
        foreach ($byUser as $userId => $points) {
            $simplified = $this->simplifier->simplify($points, 10.0, self::MAX_POINTS);

            // 점이 하나면 선이 아니다. 마커가 이미 그 자리에 있으므로 보낼 게 없다.
            if (count($simplified) < 2) {
                continue;
            }

            $tracks[] = [
                'user_id' => (int) $userId,
                // 🔑 [lat, lng] 배열로 보낸다. 키 있는 객체면 점 500개 × 14명에서
                //    페이로드가 두 배가 되고, 선을 긋는 데 필요한 것은 좌표뿐이다.
                'points' => array_map(fn ($p) => [$p['lat'], $p['lng']], $simplified),
                'count' => count($points),
                'from' => $points[0]['t']?->toIso8601String(),
                'to' => end($points)['t']?->toIso8601String(),
            ];
        }

        return $tracks;
    }
}
