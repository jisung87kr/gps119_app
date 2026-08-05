<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * 웹 푸시 VAPID 키 쌍 생성 (mobile-app N1).
 *
 * 키는 «서비스 1개당 1쌍»이고 바꾸면 **기존 구독이 전부 무효가 된다** —
 * 모든 사용자가 알림을 다시 허용해야 한다. 그래서 출력만 하고 .env 를 직접
 * 건드리지 않는다. 붙여넣는 순간을 사람이 보게 하는 편이 안전하다.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid-keys';

    protected $description = '웹 푸시(VAPID) 키 쌍을 생성해 출력합니다. .env 에 직접 붙여넣으세요.';

    public function handle(): int
    {
        if (config('push.vapid.public_key')) {
            $this->warn('⚠️  이미 VAPID 키가 설정되어 있습니다.');
            $this->line('   키를 바꾸면 기존 웹 푸시 구독이 «전부» 무효가 되고,');
            $this->line('   사용자는 알림을 다시 허용해야 합니다.');

            if (! $this->confirm('그래도 새 키를 생성할까요?', false)) {
                return self::SUCCESS;
            }
        }

        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->info('아래 두 줄을 .env 에 붙여넣으세요:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('공개키는 브라우저에 노출됩니다(정상). 개인키는 절대 커밋하지 마세요.');

        return self::SUCCESS;
    }
}
