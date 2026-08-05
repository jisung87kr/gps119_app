<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * event_participants 에 마지막 위치 «정확도» 캐시를 추가한다.
 *
 * 왜 필요한가: accuracy 는 클라이언트가 보내고(locationShare.js), 검증되고
 * (StoreLocationPingRequest), location_pings 에 적재되고, 브로드캐스트
 * 페이로드(ParticipantLocationUpdated)에도 실려 있었다. 그런데 «참가자 캐시»에만
 * 없어서, 관제 화면이 처음 로드할 때 받는 roster 에는 정확도가 빠져 있었다.
 *
 * 결과적으로 오차 5m 인 사람과 500m 인 사람이 지도에 똑같은 점으로 찍혔다.
 * 산에서 500m 는 사람을 못 찾는 거리다.
 *
 * 단위: 미터. location_pings.accuracy 와 같은 unsignedSmallInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_accuracy')->nullable()->after('last_lng');
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropColumn('last_accuracy');
        });
    }
};
