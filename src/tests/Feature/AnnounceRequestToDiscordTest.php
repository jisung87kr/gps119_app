<?php

namespace Tests\Feature;

use App\Events\RequestCreated;
use App\Listeners\AnnounceRequestToDiscord;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 신규 신고 → 디스코드 공지.
 *
 * 이 리스너가 `NotifyRescuers` 에서 분리된 이유는 재시도 의미가 다르기 때문이고,
 * 그 «분리되어 있음» 자체를 마지막 테스트가 고정한다. 다시 합쳐지면 디스코드 실패가
 * 이미 성공한 통지를 재발송시킨다.
 */
class AnnounceRequestToDiscordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Http::fake();

        // ⚠️ RequestCreated 는 Request 모델의 created 훅에서 발행된다. 즉 «팩토리로 신고를
        //    하나 만들기만 해도» 등록된 리스너가 (sync 큐라) 전부 동기 실행된다.
        //    그대로 두면 아래 테스트들이 «내가 부른 리스너»와 «팩토리가 부른 리스너»를
        //    구분하지 못한다. 픽스처 생성 동안에는 이벤트를 막고, 리스너는 직접 호출한다.
        Event::fake([RequestCreated::class]);
    }

    private function newRequestEvent(): RequestCreated
    {
        $request = RescueRequest::factory()->for(User::factory()->create())->create([
            'address' => '강원특별자치도 춘천시 세실로 261',
        ]);

        return new RequestCreated($request->load('user'));
    }

    public function test_does_nothing_when_the_webhook_is_not_configured(): void
    {
        config(['services.discord.webhook_url' => null]);

        (new AnnounceRequestToDiscord)->handle($this->newRequestEvent());

        Http::assertNothingSent();
    }

    public function test_posts_to_the_configured_webhook(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.test/webhook/abc']);
        $event = $this->newRequestEvent();

        (new AnnounceRequestToDiscord)->handle($event);

        Http::assertSent(function ($request) use ($event) {
            return $request->url() === 'https://discord.test/webhook/abc'
                && str_contains($request['content'], $event->request->address)
                && str_contains($request['content'], '/requests/'.$event->request->id);
        });
    }

    public function test_reads_the_url_from_config_not_env(): void
    {
        // 리스너가 env() 를 직접 읽으면 `php artisan config:cache` 후 null 이 되어
        // 조용히 꺼진다. config() 만 바꿔도 동작이 따라오면 config 경유라는 뜻이다.
        putenv('DISCORD_WEBHOOK_URL=https://discord.test/from-env');
        config(['services.discord.webhook_url' => 'https://discord.test/from-config']);

        (new AnnounceRequestToDiscord)->handle($this->newRequestEvent());

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/from-config');
        putenv('DISCORD_WEBHOOK_URL');
    }

    public function test_notifying_rescuers_does_not_also_post_to_discord(): void
    {
        // 부작용 하나에 리스너 하나. 다시 합치면 이 테스트가 깨진다.
        config(['services.discord.webhook_url' => 'https://discord.test/webhook/abc']);

        (new \App\Listeners\NotifyRescuers)->handle($this->newRequestEvent());

        Http::assertNothingSent();
    }
}
