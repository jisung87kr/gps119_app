<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dispatches 에 «회수» 시각을 추가한다 (ADR-0007).
 *
 * DispatchStatus::CANCELLED 는 상황실이 발령을 되돌린 상태다. 지금까지 관제사는
 * 잘못 보낸 지령을 뺄 방법이 아예 없었다 — 전이표에 역행 경로가 없어서, 대원에게
 * 「그냥 무시하세요」라고 무전하고 지령은 화면에 영원히 활성으로 남겼다.
 *
 * status 는 string 컬럼이라 값 추가 자체에는 스키마 변경이 필요 없지만,
 * DispatchService::timestampColumn() 이 모든 상태에 대응 컬럼을 요구한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
