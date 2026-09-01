<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 약관 동의 기록 (위치정보법 대응).
 *
 * 🔑 **users 에 불리언 컬럼을 붙이지 않는다.** 그러면 «어느 판에, 언제» 동의했는지가
 *    남지 않아서, 약관이 바뀐 뒤 누가 재동의했는지 구분할 수 없다.
 *
 * 🔑 (user_id, type, version) 유니크. 같은 판에 두 번 동의해도 행이 하나다 —
 *    재시도·중복 제출에도 결과가 같다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('version', 20);
            $table->timestamp('agreed_at');
            // 분쟁 시 «언제 어디서» 동의했는지가 근거가 된다. IPv6 까지 45자.
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'version']);
            $table->index(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
