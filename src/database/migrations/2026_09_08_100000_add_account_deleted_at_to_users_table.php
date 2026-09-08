<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 탈퇴를 «익명화»로 바꾼다 (ADR-0010).
 *
 * - account_deleted_at: 탈퇴 표식. SoftDeletes 의 deleted_at 이 «아닌» 이유는 전역 스코프 때문이다 —
 *   지령·신고의 belongsTo 가 null 이 되면 과거 기록 화면이 줄줄이 깨진다. 탈퇴한 사람은
 *   기록 위에 «탈퇴 회원»으로 남아야 한다.
 * - phone nullable: 탈퇴 시 전화번호를 비운다. NOT NULL 이면 빈 문자열을 넣어야 하는데
 *   unique 라 두 번째 탈퇴자부터 충돌한다. NULL 은 unique 에 걸리지 않는다.
 *
 * ⚠️ timestamp() 다 — dateTime() 은 세션 시간대 변환이 없어 9시간 어긋난다(TimezoneTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_deleted_at')->nullable()->after('remember_token');
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_deleted_at');
            $table->string('phone')->nullable(false)->change();
        });
    }
};
