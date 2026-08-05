<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\PushService;
use Illuminate\Console\Command;

/**
 * 푸시 수동 검증 (mobile-app N1 / N3).
 *
 * 자동 테스트가 못 밟는 두 구간을 사람이 확인하기 위한 도구다:
 *   1) 알림이 실제로 «표시»되는가 (OS 알림 센터)
 *   2) 알림을 «탭했을 때» 딥링크가 착지하는가 (notificationclick)
 * 둘 다 브라우저 DOM 밖이라 헤드리스로도, 실브라우저 자동조작으로도 잡을 수 없다.
 */
class SendTestPush extends Command
{
    protected $signature = 'push:test
                            {--user= : 대상 사용자 id 또는 이메일 (기본: 활성 구독이 있는 가장 최근 사용자)}
                            {--request= : 딥링크에 쓸 신고 id (기본: 가장 최근 신고)}
                            {--url= : 딥링크 URL 을 직접 지정 (--request 보다 우선)}';

    protected $description = '등록된 구독으로 테스트 푸시를 보냅니다(알림 표시·딥링크 착지 수동 확인용).';

    public function handle(PushService $push): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('활성 구독이 있는 사용자를 찾지 못했습니다.');
            $this->line('  브라우저에서 프로필 → 「알림 받기」 → 켜기 를 먼저 하세요.');

            return self::FAILURE;
        }

        $devices = DeviceToken::query()->forUser($user->id)->active()->get();

        if ($devices->isEmpty()) {
            $this->error("{$user->name} 에게 활성 구독이 없습니다.");

            return self::FAILURE;
        }

        [$url, $label] = $this->resolveUrl();

        $this->line("대상   : {$user->name} (id={$user->id}, 통로 {$devices->count()}개)");
        $this->line("딥링크 : {$url}  {$label}");
        $this->newLine();

        $tally = $push->sendToUser($user, new PushMessage(
            title: '🚨 [테스트] 신규 구조요청',
            body: '알림을 탭하면 관제 화면으로 이동해야 합니다.',
            url: $url,
            data: ['test' => 1],
            // 매번 다른 tag — 같은 tag 면 이전 알림을 «대체»해서 반복 검증이 헷갈린다.
            tag: 'push-test-'.now()->timestamp,
        ));

        foreach ($tally as $result => $count) {
            if ($count > 0) {
                $this->line("  {$result}: {$count}");
            }
        }

        if ($tally['delivered'] > 0) {
            $this->newLine();
            $this->info('✅ 전송됨. 이제 알림을 «탭»해서 확인하세요:');
            $this->line('   · 이미 열린 탭이 있으면 → 새 창이 아니라 «그 탭»이 위 URL 로 이동해야 한다');
            $this->line('   · 열린 탭이 없으면 → 새 창이 열려야 한다');
            $this->line('   · 착지한 화면에서 «그 행사»가 선택되어 있어야 한다(딥링크의 요점)');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('전송되지 않았습니다. delivered 가 0 입니다.');
        $this->line('  invalid → 구독이 죽었다(자동 폐기됨). 브라우저에서 다시 켜세요.');
        $this->line('  skipped → 전송 경로 미설정. VAPID_PUBLIC_KEY/PRIVATE_KEY 를 확인하세요.');

        return self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        $key = $this->option('user');

        if ($key) {
            return is_numeric($key)
                ? User::find($key)
                : User::where('email', $key)->first();
        }

        // 가장 최근에 구독한 사람 — 방금 브라우저에서 켠 사람이 잡힌다.
        $latest = DeviceToken::query()->active()->latest('id')->first();

        return $latest?->user;
    }

    /** @return array{0: string, 1: string} */
    private function resolveUrl(): array
    {
        if ($custom = $this->option('url')) {
            return [$custom, '(직접 지정)'];
        }

        $request = $this->option('request')
            ? RescueRequest::find($this->option('request'))
            : RescueRequest::latest('id')->first();

        if ($request === null) {
            return ['/control', '(신고가 없어 행사 미지정)'];
        }

        return [
            "/control?project={$request->project_id}&request={$request->id}",
            "(신고 #{$request->id})",
        ];
    }
}
