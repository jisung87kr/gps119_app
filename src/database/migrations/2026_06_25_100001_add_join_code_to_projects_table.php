<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-01 #1 — projects.join_code 추가.
 *
 * 행사 입장용 코드. nullable(기존 행 보존) + unique(행사당 유니크).
 * 신규 행은 Project::booted() creating 훅에서 자동 발급한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('join_code', 12)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
    }
};
