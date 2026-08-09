<?php

namespace App\Services;

use App\Enums\PushDelivery;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\BadgeCounter;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 푸시 발송의 «정책» 계층 (mobile-app N1).
 *
 * 전송 규격은 PushSender 구현체가 맡고, 여기서는 규격과 무관한 것만 결정한다:
 *   - 이 사용자의 살아있는 통로가 무엇인가
 *   - 결과를 어떻게 처리하는가 (무효 토큰 폐기 / 실패 로깅)
 *
 * 🔑 토큰이 없거나 전송 경로가 꺼져 있으면 **조용히 아무것도 하지 않는다.**
 * 이건 편의가 아니라 안전장치다 — 신고 생성은 시드·테스트·개발 환경에서도
 * 수시로 일어나는데, 그때마다 예외가 나면 구조요청 접수 자체가 막힌다.
 * 「알림이 안 갔다」보다 「신고가 안 됐다」가 훨씬 나쁘다.
 */
class PushService
{
    /** @param  array<int, PushSender>  $senders */
    public function __construct(
        private readonly array $senders,
        private readonly ?BadgeCounter $badges = null,
    ) {}

    /**
     * 한 사용자의 모든 기기로 발송.
     *
     * @return array<string, int> 결과별 건수 (delivered/invalid/failed/skipped)
     */
    public function sendToUser(User $user, PushMessage $message): array
    {
        return $this->sendToDevices(
            DeviceToken::query()->forUser($user->id)->active()->get(),
            $this->stamped($message, $user),
        );
    }

    /**
     * 여러 사용자에게 발송. 수신자 목록은 부르는 쪽(리스너)이 정한다.
     *
     * @param  Collection<int, User>|array<int, User>  $users
     * @return array<string, int>
     */
    public function sendToUsers($users, PushMessage $message): array
    {
        $recipients = collect($users)->unique('id')->values();

        if ($recipients->isEmpty()) {
            return $this->emptyTally();
        }

        $devices = DeviceToken::query()
            ->whereIn('user_id', $recipients->pluck('id'))
            ->active()
            ->get()
            ->groupBy('user_id');

        // 🔑 **사람마다 한 번씩 돈다 — 뱃지 숫자가 수신자별로 다르기 때문이다.**
        //    예전엔 기기 전체를 한 덩어리로 보냈는데, 그러면 「내가 봐야 할 건수」를
        //    남의 숫자로 덮어쓰게 된다.
        $tally = $this->emptyTally();

        foreach ($recipients as $user) {
            $userDevices = $devices->get($user->id);

            if ($userDevices === null) {
                continue;
            }

            foreach ($this->sendToDevices($userDevices, $this->stamped($message, $user)) as $k => $v) {
                $tally[$k] += $v;
            }
        }

        return $tally;
    }

    /**
     * 나가기 «직전»에 이 사람의 뱃지 숫자를 찍는다.
     *
     * 🔑 뱃지는 메시지 내용이 아니라 **받는 사람의 상태**라서, 리스너가 아니라 여기서
     *    붙인다. 덕분에 **어떤 푸시가 나가든 뱃지가 함께 보정되고**, 볼 것이 없어진
     *    사람에게는 0 이 가서 저절로 지워진다 — 뱃지 전용 발송이 필요 없다.
     *
     * ⚠️ 이미 뱃지가 지정된 메시지는 건드리지 않는다(부르는 쪽이 명시했다는 뜻).
     *    `BadgeCounter` 가 없으면(구형 생성자로 만든 인스턴스·테스트) 그냥 통과시킨다 —
     *    뱃지 때문에 발송이 막히는 일은 없어야 한다.
     */
    private function stamped(PushMessage $message, User $user): PushMessage
    {
        if ($this->badges === null || $message->badge !== null) {
            return $message;
        }

        return $message->withBadge($this->badges->for($user));
    }

    /**
     * @param  Collection<int, DeviceToken>  $devices
     * @return array<string, int>
     */
    private function sendToDevices(Collection $devices, PushMessage $message): array
    {
        $tally = $this->emptyTally();

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $message);
            $tally[$result->value]++;
        }

        // 실패는 삼키지 않는다. 다만 «보낼 곳이 없었다»는 실패가 아니므로 로그하지 않는다.
        if ($tally[PushDelivery::FAILED->value] > 0) {
            Log::warning('푸시 전송 실패 건이 있습니다', [
                'title' => $message->title,
                'tally' => $tally,
            ]);
        }

        return $tally;
    }

    public function sendToDevice(DeviceToken $device, PushMessage $message): PushDelivery
    {
        $sender = $this->senderFor($device);

        if ($sender === null) {
            return PushDelivery::SKIPPED;
        }

        $result = $sender->send($device, $message);

        if ($result->shouldRevokeToken()) {
            // 죽은 통로를 남겨두면 매 알림마다 실패가 쌓이고, 로그가 시끄러워져
            // «진짜 실패»가 묻힌다.
            $device->revoke();

            Log::info('무효 푸시 토큰 폐기', [
                'device_token_id' => $device->id,
                'user_id' => $device->user_id,
                'platform' => $device->platform->value,
            ]);
        }

        return $result;
    }

    private function senderFor(DeviceToken $device): ?PushSender
    {
        foreach ($this->senders as $sender) {
            if ($sender->supports($device->platform) && $sender->isConfigured()) {
                return $sender;
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function emptyTally(): array
    {
        return [
            PushDelivery::DELIVERED->value => 0,
            PushDelivery::INVALID->value => 0,
            PushDelivery::FAILED->value => 0,
            PushDelivery::SKIPPED->value => 0,
        ];
    }
}
