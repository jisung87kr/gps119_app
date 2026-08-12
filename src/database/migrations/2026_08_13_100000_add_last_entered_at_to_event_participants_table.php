<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 「마지막으로 이 행사에 «입장»한 시각」.
 *
 * 왜 필요한가: 한 사람이 동시에 진행 중인 두 행사에 참가할 수 있다. 그때 신고를 어느
 * 행사에 붙일지 정해야 하는데, 응급 화면에서 드롭다운을 고르게 할 수는 없다.
 * 「마지막으로 입장 QR 을 찍은 곳」이 마찰 없이 쓸 수 있는 유일한 근거다.
 *
 * ⚠️ `joined_at` 으로는 안 된다. 그건 «최초» 입장이고, joinByCode 가 firstOrCreate 라
 *    재입장해도 갱신되지 않는다. 두 행사를 오가는 사람에게는 영원히 처음 들어간 쪽이
 *    이긴다 — 그건 「마지막으로 있던 현장」이 아니다.
 *
 * 기존 행은 joined_at 으로 백필한다(그 시점에는 그게 유일한 입장 기록이다).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->timestamp('last_entered_at')->nullable()->after('joined_at');
        });

        \Illuminate\Support\Facades\DB::table('event_participants')
            ->whereNull('last_entered_at')
            ->update(['last_entered_at' => \Illuminate\Support\Facades\DB::raw('joined_at')]);
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropColumn('last_entered_at');
        });
    }
};
