<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 «운영진 계정 일괄 발급» (ADR-0009).
 *
 * - must_change_password: 발급 계정은 첫 로그인에서 비밀번호 변경 + 필수 동의를 강제한다.
 *   기존/일반 가입 계정은 기본값 false 라 게이트에 걸리지 않는다.
 * - issued_at / issued_by: «관리자가 대리 발급했고 본인이 아직 안 쓴» 계정을 식별한다.
 *   재발급을 그 계정에만 허용하고, 회원 목록에서 배지로 보이게 하는 근거다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('issued_at')->nullable()->after('must_change_password');
            $table->foreignId('issued_by')->nullable()->after('issued_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn(['must_change_password', 'issued_at']);
        });
    }
};
