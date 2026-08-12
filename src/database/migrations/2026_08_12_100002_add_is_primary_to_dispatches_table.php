<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * dispatches 에 «주담당 여부»를 추가한다 (ADR-0007 D4 — 다중 배차).
 *
 * 현장 피드백: 「1개의 출동을 중복으로 배차」 — 심정지·다발부상처럼 한 신고에
 * 두 명 이상이 붙어야 하는 상황에서, 활성 지령 1건 불변식이 두 번째 대원을 막았다.
 *
 * 🔑 정원 무제한이 아니라 «주담당 1명 + 보조 N명»이다. 「누가 이 환자를 책임지는가」가
 *    데이터에 남지 않으면 그건 지령이 아니라 알림이다. 신고 종결 판정도 주담당만 한다.
 *
 * 🔴 [request_id, paramedic_id] 유니크 인덱스는 «넣지 않는다». 거절→같은 대원 재배정은
 *    정당한 흐름인데(가장 가까운 대원이 잠깐 손이 묶였다가 다시 가용해지는 경우) MySQL 8
 *    에는 부분 유니크(WHERE 조건부)가 없어 종료된 행까지 같이 막힌다. 중복 배정 방지는
 *    DispatchService 가 신고 행 lockForUpdate 안에서 «활성 지령»만 보고 판정한다.
 *
 * 백필: 기존 행은 전부 주담당(true)이다 — 지금까지 한 신고에 활성 지령이 1건뿐이었으므로
 * 그 1건이 곧 주담당이다. 컬럼 기본값이 이미 그 값이지만, 나중에 기본값이 바뀌어도
 * 과거 행의 의미가 흔들리지 않도록 명시적으로 한 번 더 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->boolean('is_primary')->default(true)->after('paramedic_id');

            // 「이 신고의 활성 주담당이 있는가」가 배정 경로의 핵심 질의다.
            $table->index(['request_id', 'is_primary', 'status'], 'dispatches_request_primary_status_index');
        });

        DB::table('dispatches')->update(['is_primary' => true]);
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropIndex('dispatches_request_primary_status_index');
            $table->dropColumn('is_primary');
        });
    }
};
