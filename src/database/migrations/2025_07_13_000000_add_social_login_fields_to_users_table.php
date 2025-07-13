<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('phone'); // 소셜 로그인 제공자 (naver, kakao 등)
            $table->string('provider_id')->nullable()->after('provider'); // 소셜 로그인 제공자의 사용자 ID
            $table->string('avatar')->nullable()->after('provider_id'); // 프로필 이미지 URL
            
            // 소셜 로그인 사용자는 비밀번호가 없을 수 있음
            $table->string('password')->nullable()->change();
            
            // provider + provider_id 조합에 대한 유니크 인덱스
            $table->unique(['provider', 'provider_id'], 'users_provider_provider_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_provider_provider_id_unique');
            $table->dropColumn(['provider', 'provider_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};