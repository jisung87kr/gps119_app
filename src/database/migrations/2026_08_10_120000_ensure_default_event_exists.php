<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0005 의 기본 행사("상시 운영")를 «신고가 없어도» 보장한다.
 *
 * 앞선 2026_07_29_100000 마이그레이션은 project_id 가 null 인 기존 신고가 있을 때만
 * 기본 행사를 만들었다(유저가 없는 신규 DB 에서 created_by FK 가 터지는 것을 피하려고).
 * 그 결과 «빈 DB 로 시작한 실서버»에는 행이 아예 생기지 않아, 첫 일반 신고가 들어오기
 * 전까지 행사 목록에 기본 행사가 보이지 않았다.
 *
 * 유저가 하나도 없는 시점(신규 설치의 migrate 단계)에는 여전히 만들 수 없으므로
 * 그 경우는 RolePermissionSeeder 가 admin 을 만든 «뒤» 담당한다. 양쪽 다 멱등이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('users')->exists()) {
            return; // 신규 설치 — 시더가 admin 생성 직후 만든다.
        }

        Project::defaultEvent();
    }

    public function down(): void
    {
        // 기본 행사는 데이터이지 스키마가 아니다. 되돌리지 않는다
        // (일반 신고들이 이 행사에 귀속돼 있어 지우면 FK 가 끊긴다).
    }
};
