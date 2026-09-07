<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 시간대 (2026-09-07 현장: 「출동이력 시간이 현재시간과 차이가 있음」).
 *
 * 앱이 UTC 였다 — 서버가 그리는 모든 시각(출동이력·통계·CSV)이 9시간 어긋났다.
 * 앱은 Asia/Seoul, MySQL 세션은 +09:00 — 둘은 «한 쌍»이다. 이 앱의 시각 열은 전부 TIMESTAMP 라
 * 저장값(UTC)은 그대로 두고 세션 시간대로만 변환된다. 한쪽만 바뀌면 9시간이 다시 어긋난다.
 */
class TimezoneTest extends TestCase
{
    public function test_app_timezone_is_seoul(): void
    {
        $this->assertSame('Asia/Seoul', config('app.timezone'));
        $this->assertSame('Asia/Seoul', now()->getTimezone()->getName());
        $this->assertSame('+09:00', now()->format('P'));
    }

    public function test_mysql_session_timezone_pairs_with_the_app(): void
    {
        $this->assertSame('+09:00', config('database.connections.mysql.timezone'));
    }

    public function test_no_migration_uses_datetime_columns(): void
    {
        // DATETIME 은 세션 시간대 변환이 없어 UTC 로 저장된 값이 KST 로 잘못 읽힌다.
        // TIMESTAMP 만 써야 앱 시간대 전환이 데이터 이관 없이 성립한다.
        $offenders = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (preg_match('/->dateTime\(/', file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'dateTime() 컬럼이 생겼다 — TIMESTAMP 로 바꾸거나 시간대 설계를 다시 볼 것');
    }
}
