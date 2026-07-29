<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-01 #4 — location_pings 생성.
 *
 * 참가자 위치 이력(append-only). UPDATE/DELETE 없음(아카이브 이관만).
 * 고빈도 수신이므로 INSERT 는 큐(PersistLocationPing)로 적재한다.
 *
 * timestamps() 생략 — recorded_at 단일 시각으로 충분(SPEC-01 명시).
 *
 * OI-3 메모: projects 는 SoftDeletes 이지만 자식 FK 는 cascadeOnDelete(물리삭제) 다.
 * 행사를 소프트 삭제해도 ping 은 cascade 되지 않고 보존되며, forceDelete 시에만 cascade.
 * 보존기간/자동파기 정책은 OPEN(OI-7, 개인정보) — OPS lane 에서 처리.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedSmallInteger('accuracy')->nullable(); // m
            $table->unsignedSmallInteger('heading')->nullable();  // 0-359
            $table->unsignedSmallInteger('speed')->nullable();    // m/s
            $table->timestamp('recorded_at');

            $table->index(['project_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_pings');
    }
};
