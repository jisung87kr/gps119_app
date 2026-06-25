<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-01 #3 — event_participants 생성.
 *
 * 행사 내 참가자(역할/상태/위치캐시)를 보관한다.
 *
 * OI-3 메모: projects 는 SoftDeletes 이지만 자식 FK 는 cascadeOnDelete(물리삭제) 다.
 * 즉 행사를 "소프트 삭제(deleted_at)"해도 참가자는 cascade 되지 않고 보존된다.
 * 실제 cascade 는 projects 를 forceDelete(물리삭제)할 때만 발동한다.
 * (권장안: 자식 cascade 유지, 소프트삭제는 스코프 쿼리로 가림 — 정리정책은 OPS lane)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');                          // App\Enums\EventRole
            $table->string('status')->default('active');     // App\Enums\ParticipantStatus
            $table->boolean('sharing_location')->default(false);
            $table->decimal('last_lat', 10, 8)->nullable();  // 최신 위치 캐시(관제 초기 1쿼리 로드용)
            $table->decimal('last_lng', 11, 8)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();   // 온라인 판정
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'role', 'status']); // 가용 인력 조회(역할+active)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
