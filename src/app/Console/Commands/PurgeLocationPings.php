<?php

namespace App\Console\Commands;

use App\Models\LocationPing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 보존기간이 지난 위치 이력을 파기한다.
 *
 * 🔴 이 앱에서 가장 민감한 데이터다 — 「누가 언제 어디 있었는지」의 이력이고, 지금까지
 *    자동 파기가 없어 쌓이기만 했다. 개인정보처리방침에 적는 보존기간과 이 명령이
 *    실제로 하는 일이 같아야 한다(약속과 구현이 어긋나면 그건 방침이 아니라 문구다).
 *
 * 멱등: 두 번 돌려도 결과가 같다. 지울 게 없으면 조용히 0을 남긴다.
 * 청크로 지우는 이유는 운영 DB 에서 한 문장으로 수백만 행을 지우면 락이 오래 걸려서다.
 */
class PurgeLocationPings extends Command
{
    protected $signature = 'location:purge
                            {--days= : 보존기간(일). 미지정 시 config(location.retention_days)}
                            {--dry-run : 지우지 않고 대상 건수만 센다}';

    protected $description = '보존기간이 지난 위치 이력(location_pings)을 파기한다';

    public function handle(): int
    {
        // ⚠️ `?:` 를 쓰면 `--days=0` 이 falsy 라 «조용히» 기본값으로 바뀐다.
        //    요청한 것과 다르게 도는 건 거부보다 나쁘다 — 0 은 0 으로 받아서 아래에서 막는다.
        $option = $this->option('days');
        $days = $option !== null ? (int) $option : (int) config('location.retention_days');

        if ($days < 1) {
            // 0 이나 음수를 허용하면 «전부 삭제»가 된다. 오타 한 번에 이력이 사라진다.
            $this->error('보존기간은 1일 이상이어야 합니다.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $chunk = max(1, (int) config('location.purge_chunk'));

        $total = LocationPing::where('recorded_at', '<', $cutoff)->count();

        if ($this->option('dry-run')) {
            $this->info("[dry-run] {$cutoff->toDateTimeString()} 이전 {$total}건이 대상입니다.");

            return self::SUCCESS;
        }

        $deleted = 0;
        while (true) {
            $n = LocationPing::where('recorded_at', '<', $cutoff)->limit($chunk)->delete();
            $deleted += $n;
            if ($n < $chunk) {
                break;
            }
        }

        // 삭제는 «남는 게 없는» 작업이라 로그가 유일한 증거다. 감사 요청이 오면 이걸 낸다.
        Log::info('위치 이력 파기', [
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
        ]);

        $this->info("{$cutoff->toDateTimeString()} 이전 위치 이력 {$deleted}건을 파기했습니다.");

        return self::SUCCESS;
    }
}
