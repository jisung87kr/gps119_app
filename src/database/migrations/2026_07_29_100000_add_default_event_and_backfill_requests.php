<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0005: 모든 신고는 행사(Project)에 소속.
 * projects.is_default 추가 + 기존 일반 신고(project_id null)를 "상시 운영" 기본 행사로 백필.
 * 신규 신고의 자동 귀속은 Request 생성 훅이 담당.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        // 귀속 대상(project_id null 신고)이 있을 때만 기본 행사 생성·백필.
        // (신선한 DB/테스트에는 신고가 없어 유저 부재로 인한 생성 실패를 피한다.)
        if (DB::table('requests')->whereNull('project_id')->exists()) {
            $default = Project::defaultEvent();
            DB::table('requests')->whereNull('project_id')->update(['project_id' => $default->id]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
