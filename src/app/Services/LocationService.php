<?php

namespace App\Services;

use App\Events\ParticipantLocationUpdated;
use App\Jobs\PersistLocationPing;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

/**
 * 위치 ping 처리 (SPEC-04c).
 *
 * record():
 *   ① event_participants 캐시(last_lat/last_lng/last_seen_at) 즉시 갱신
 *   ② location_pings INSERT 는 큐(PersistLocationPing) 적재 (append-only, 고빈도)
 *   ③ ParticipantLocationUpdated 브로드캐스트(locations + control)
 *
 * sharing_location=false 면 캐시 갱신/브로드캐스트/적재 모두 스킵(05).
 */
class LocationService
{
    /**
     * @param  array{latitude: float|string, longitude: float|string, accuracy?: int|null, heading?: int|null, speed?: int|null, recorded_at: string}  $data
     */
    public function record(Project $project, User $user, array $data): ?EventParticipant
    {
        $participant = EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            throw new RuntimeException('해당 행사에 참가하고 있지 않습니다.');
        }

        // 위치공유 off 면 수신해도 캐시/브로드캐스트/적재 스킵
        if (! $participant->sharing_location) {
            return null;
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        $accuracy = $data['accuracy'] ?? null;
        $recordedAt = Carbon::parse($data['recorded_at']);

        // ① 캐시 즉시 갱신
        //    last_accuracy 를 같이 저장한다 — 관제가 처음 로드할 때 받는 roster 의
        //    유일한 정확도 출처다(이후 갱신은 브로드캐스트 페이로드로 들어온다).
        $participant->forceFill([
            'last_lat' => $latitude,
            'last_lng' => $longitude,
            'last_accuracy' => $accuracy !== null ? (int) $accuracy : null,
            'last_seen_at' => $recordedAt,
        ])->save();

        // ② location_pings 큐 적재
        PersistLocationPing::dispatch([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'recorded_at' => $recordedAt->toDateTimeString(),
        ]);

        // ③ 브로드캐스트(연락처 없음, locations + control 동일 payload)
        ParticipantLocationUpdated::dispatch(
            $project->id,
            $user->id,
            $participant->role,
            $latitude,
            $longitude,
            $accuracy !== null ? (int) $accuracy : null,
            $recordedAt->toISOString(),
        );

        return $participant;
    }
}
