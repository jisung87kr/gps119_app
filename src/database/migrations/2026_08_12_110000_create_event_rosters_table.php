<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 행사 «사전명단» — 관리자가 올린 운영진 명단(전화번호 → 역할).
 *
 * 운영 흐름(2026-08-12 확정):
 *   ① 프로젝트 생성
 *   ②-1 관리자가 운영진 명단을 엑셀(CSV)로 업로드  ← 이 테이블
 *   ②-2 참가자·운영진 «모두» 같은 입장 QR 로 들어옴 → 명단에 있으면 그 역할, 없으면 참가자
 *
 * 🔑 계정을 미리 만들지 않는다. 예전 임포트는 회원을 생성했는데, 그러면 그 사람은
 *    ① 임의 비밀번호라 로그인할 수 없고 ② 재설정은 이메일 기반이라 못 쓰고
 *    ③ 전화번호가 점유돼 «본인이 회원가입도 못 한다». 명단은 들어가는데 사람이 못 들어오는
 *    상태가 된다. 명단만 두면 본인은 평소처럼 가입하고 입장 시 역할이 자동으로 붙는다.
 *
 * 🔑 event_participants 를 재활용하지 않는 이유: 거긴 user_id 가 NOT NULL + FK 인 것이
 *    불변식이다. user_id 없는 «예정» 행을 섞으면 위치·지령·채널·리포트 쿼리 전부에
 *    유령 행이 흘러든다. 명단과 참가는 다른 것이다.
 *
 * phone 은 «정규화된»(숫자만) 값으로만 저장한다 — User::setPhoneAttribute 와 같은 규칙이라야
 * 입장 시 조회가 맞는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('name')->nullable();
            $table->string('role');
            // 입장으로 «소진»된 명단 — 누가 언제 가져갔는지.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // 같은 행사에 같은 번호는 한 줄. 재업로드가 멱등이 되는 근거.
            $table->unique(['project_id', 'phone']);
            // 「명단에 있는데 아직 안 들어온 사람」 조회 — 행사 시작 전 점검용.
            $table->index(['project_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rosters');
    }
};
