<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 앱이 보고하는 OS 위치 권한 상태 (M-5, ADR-0008).
 *
 * `sharing_location`(의도) 옆에 «능력»을 둔다. 둘이 어긋난 상태 — 켜뒀는데 권한이
 * 없는 — 를 관제가 구분하지 못하는 것이 M-5 다.
 *
 * 🔑 **nullable 이고 기본값이 없다.** null 은 「권한 없음」이 아니라
 *    **「보고한 적이 없다」**(웹 사용자·구버전 앱)이고, 그 둘을 같게 취급하면
 *    웹으로 잘 쓰고 있는 사람이 전부 «위치 권한 없음»으로 붉게 뜬다.
 *    TrackingState 는 이 경우를 UNKNOWN 으로 따로 낸다.
 *
 * `location_permission_at` 은 «언제 본 상태인가»다. 권한이 끊기면 보고도 끊기므로
 * 값이 오래됐을 수 있다 — 신선도를 모르면 낡은 값을 현재로 읽는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->string('location_permission', 20)->nullable()->after('sharing_location');
            $table->timestamp('location_permission_at')->nullable()->after('location_permission');
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropColumn(['location_permission', 'location_permission_at']);
        });
    }
};
