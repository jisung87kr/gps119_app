<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-01 #5 — dispatches 생성.
 *
 * 지령(출동) 상태머신. "한 시점 활성 지령 1건"은 거절 row 가 남아 DB 유니크로 강제 불가 →
 * 서비스 레이어 불변식(트랜잭션 + lockForUpdate, OI-2)으로 보장.
 *
 * OI-3 메모: projects 는 SoftDeletes 이지만 자식 FK 는 cascadeOnDelete(물리삭제).
 * 소프트삭제 시 cascade 안 되고 forceDelete 시에만 발동.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users');  // 발령자(controller/admin)
            $table->foreignId('paramedic_id')->constrained('users'); // 수령 구급대원
            $table->string('status')->default('assigned');           // App\Enums\DispatchStatus
            $table->text('note')->nullable();
            $table->text('reject_reason')->nullable();               // 거절 사유(필수 전이)
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']); // 출동 현황 보드 집계
            $table->index(['request_id', 'status']); // 활성 지령 1건 검색(재지령)
            $table->index('paramedic_id');           // /dispatches/mine
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
