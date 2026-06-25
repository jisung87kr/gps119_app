<?php

namespace App\Enums;

/**
 * 행사 참가 상태 (SPEC-02b).
 *
 * 자가선택 참가자는 즉시 active. 권한 역할(상황실/구급 등)을 수동·사전명단으로 부여할 때
 * pending(승인대기)으로 둘 수 있으며, active 전까지 권한 채널/기능 접근이 차단된다.
 */
enum ParticipantStatus: string
{
    case PENDING = 'pending';  // 승인대기
    case ACTIVE = 'active';    // 활동중
    case LEFT = 'left';        // 퇴장

    /**
     * 사용자에게 보여줄 한글 라벨.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '승인대기',
            self::ACTIVE => '활동중',
            self::LEFT => '퇴장',
        };
    }

    /**
     * 활동중 여부.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
