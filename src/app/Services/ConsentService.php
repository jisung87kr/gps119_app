<?php

namespace App\Services;

use App\Enums\ConsentType;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Carbon;

/**
 * 약관 동의의 기록과 판정 (위치정보법 대응).
 *
 * 🔑 **시각과 IP 를 주입받는다.** 함수 안에서 now() 를 부르면 가짜 시계로 검증할 수 없다.
 */
class ConsentService
{
    /**
     * 동의를 남긴다.
     *
     * 🔑 **두 번 돌려도 결과가 같다.** 같은 판에 대한 재제출은 행을 늘리지 않는다 —
     *    폼 이중 제출·재시도 경로에서 기록이 부풀지 않는다.
     *
     * @param  ConsentType[]  $types
     */
    public function record(User $user, array $types, ?string $ip = null, ?Carbon $at = null): void
    {
        $at ??= Carbon::now();

        foreach ($types as $type) {
            UserConsent::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type->value,
                    'version' => $type->currentVersion(),
                ],
                [
                    'agreed_at' => $at,
                    'ip' => $ip,
                ],
            );
        }
    }

    /** 지금 «유효한 판»에 동의했는가. 판이 올라가면 자동으로 false 가 된다. */
    public function hasConsented(User $user, ConsentType $type): bool
    {
        return UserConsent::where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('version', $type->currentVersion())
            ->exists();
    }

    /** 아직 못 받은 필수 동의. 비어 있으면 통과다. */
    public function missingRequired(User $user): array
    {
        return array_values(array_filter(
            ConsentType::required(),
            fn (ConsentType $t) => ! $this->hasConsented($user, $t),
        ));
    }
}
