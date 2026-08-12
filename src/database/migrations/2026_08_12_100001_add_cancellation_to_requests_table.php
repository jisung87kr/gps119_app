<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * requests 에 «누가 왜 취소했는가»를 남긴다.
 *
 * 지금까지 취소는 status 만 바꿨다. 그래서 사후에 「이 신고는 왜 없어졌나」를 물으면
 * 아무도 답할 수 없었다 — 신고자가 잘못 눌렀는지, 상황실이 오인신고로 판단했는지,
 * 관리자가 실수했는지 데이터에 흔적이 없다. 응급 도메인에서 이건 분쟁이 났을 때
 * 정확히 필요한 두 칸이다.
 *
 * cancelled_at 은 만들지 않는다 — 기존 completed_at 이 「종결 시각」으로 이미 쓰이고 있고,
 * 컬럼을 하나 더 두면 둘이 어긋날 때 어느 쪽이 맞는지 아무도 모르게 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('completed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancel_reason');
        });
    }
};
