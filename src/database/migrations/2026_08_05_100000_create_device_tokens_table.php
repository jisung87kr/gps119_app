<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mobile-app 에픽 N1 — 푸시 수신 통로.
 *
 * 앱(FCM 토큰)과 웹(웹푸시 구독)을 한 표에 담는다. 도메인에서는 둘 다
 * «이 사람에게 닿는 통로»이고, 나누면 수신자 조회가 두 벌이 된다.
 *
 * 🔴 `token` 은 그 자체로 «이 기기에 푸시를 보낼 수 있는 자격증명»이다.
 *    그래서 조회·중복판정·로그는 전부 `token_hash` 로 하고, 원문은 발송에만 쓴다.
 *    (03 §3-2: URL path 에 토큰을 넣지 않는 것과 같은 이유 — 액세스 로그·프록시
 *     로그·에러 리포트에 남는다. 인덱스를 원문에 걸면 그 습관이 되살아난다.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);          // App\Enums\PushPlatform

            // FCM 등록 토큰(앱) 또는 웹푸시 endpoint URL(웹). 둘 다 길이가 가변이라 text.
            $table->text('token');

            // sha256(token) hex 64자. 조회·중복판정은 «전부» 이 컬럼으로 한다.
            $table->char('token_hash', 64)->unique();

            // 웹푸시 전용 공개키 쌍({p256dh, auth}). 앱 토큰은 null.
            $table->json('keys')->nullable();

            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // 폐기 시각. 행을 지우지 않는 이유 — 같은 기기가 재구독할 때 되살리면
            // 되고, 「언제부터 안 받고 있었나」를 나중에 물을 수 있다.
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // 발송 시 수신자 조회: 「이 사용자의 살아있는 통로」
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
