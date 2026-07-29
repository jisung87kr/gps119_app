<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-01 #2 — requests.type 추가.
 *
 * App\Enums\RequestType. default 'other'(기존 행 보존). priority 는 유지하며
 * 신고 생성 시 type->defaultPriority() 로 자동 매핑(상황실 수동 상향 허용).
 *
 * 기존 행의 description → type 추정 보정은 별도 일회성 커맨드(마이그레이션 본문 금지).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('type')->default('other')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
