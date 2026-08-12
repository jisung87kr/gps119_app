<?php

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 시스템 롤 `rescuer` 를 없애고, 그 사람들을 「상시 운영」 기본 행사의 «구급대»로 옮긴다.
 *
 * 왜 — 대응 인력의 체계가 둘이라 관리자가 헷갈렸다. 신고는 ADR-0005 로 「모든 신고는
 * 행사에 속한다」로 이미 일원화됐는데 사람만 두 벌로 남아 있었다. 「상시 운영」 행사가
 * 항상 활성이므로, 상시 구급 인력은 시스템 롤이 아니라 그 행사의 역할로 표현하면 된다.
 * 그러면 「신고도 행사, 사람도 행사」로 대칭이 맞는다.
 *
 * 🔴 순서가 중요하다. 참가 행을 «먼저» 만들고 그다음 롤을 뗀다. 반대로 하면 그 사이에
 *    누가 신고를 올렸을 때 알림 대상이 0명이 된다 — 길가 신고가 조용히 사라지고,
 *    화면은 멀쩡해 보인다.
 *
 * 멱등: 이미 그 행사에 참가 중이면 역할을 덮지 않는다(관리자가 상황실로 올려뒀을 수 있다).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rescuerRoleId = DB::table('roles')->where('name', 'rescuer')->value('id');

        if ($rescuerRoleId === null) {
            return; // 이미 정리된 환경(테스트·신규 설치)
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $rescuerRoleId)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');

        if ($userIds->isNotEmpty()) {
            $projectId = Project::defaultEvent()->id;
            $now = now();

            foreach ($userIds as $userId) {
                $exists = DB::table('event_participants')
                    ->where('project_id', $projectId)
                    ->where('user_id', $userId)
                    ->exists();

                if ($exists) {
                    continue; // 이미 참가 중 — 역할을 덮지 않는다
                }

                DB::table('event_participants')->insert([
                    'project_id' => $projectId,
                    'user_id' => $userId,
                    'role' => EventRole::PARAMEDIC->value,
                    'status' => ParticipantStatus::ACTIVE->value,
                    'sharing_location' => false,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 이관이 끝난 뒤에야 롤을 뗀다.
        DB::table('model_has_roles')->where('role_id', $rescuerRoleId)->delete();
        DB::table('role_has_permissions')->where('role_id', $rescuerRoleId)->delete();
        DB::table('roles')->where('id', $rescuerRoleId)->delete();
    }

    /**
     * 롤은 되살릴 수 있지만 «누가 rescuer 였는지»는 복원하지 않는다.
     * 그 정보는 이제 event_participants 에 있고, 여기서 되돌리면 두 벌이 다시 생긴다.
     */
    public function down(): void
    {
        if (! DB::table('roles')->where('name', 'rescuer')->exists()) {
            DB::table('roles')->insert([
                'name' => 'rescuer',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
