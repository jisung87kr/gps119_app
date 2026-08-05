<?php

namespace App\Enums;

/**
 * 푸시 1건의 전송 결과.
 *
 * 🔑 `FAILED` 와 `INVALID` 를 가르는 것이 이 열거형의 존재 이유다.
 * 둘 다 「안 갔다」지만 대응이 정반대다 — 실패는 «다시 시도할 것»,
 * 무효는 «이 통로를 폐기할 것»이다. 구분하지 않으면 죽은 토큰에
 * 영원히 재시도하거나(큐가 쌓인다), 일시 장애로 살아있는 기기를 지운다
 * (그 사람은 다시 구독하기 전까지 지령을 못 받는다).
 */
enum PushDelivery: string
{
    /** 전송처가 받았다. 사용자 기기 도착까지 보장하지는 않는다(푸시의 본질적 한계). */
    case DELIVERED = 'delivered';

    /** 구독/토큰이 죽었다(404·410·UNREGISTERED). 폐기 대상 — 재시도 금지. */
    case INVALID = 'invalid';

    /** 일시 장애(5xx·네트워크·인증). 재시도 여지 있음. */
    case FAILED = 'failed';

    /** 전송 경로가 설정되지 않았다. 오류가 아니라 «아직 안 켰다». */
    case SKIPPED = 'skipped';

    public function shouldRevokeToken(): bool
    {
        return $this === self::INVALID;
    }
}
