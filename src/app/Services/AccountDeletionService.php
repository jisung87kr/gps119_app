<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\EventParticipant;
use App\Models\EventRoster;
use App\Models\LocationPing;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 회원 탈퇴 = «사람»은 지우고 «행사 기록»은 남긴다 (ADR-0010).
 *
 * users 행을 물리 삭제하지 않는다. dispatches.paramedic_id / assigned_by 가 RESTRICT 라
 * 지령 이력이 있는 구급대원·상황실은 삭제가 500 으로 죽었고(2026-09-08 실측), 설령 지워진다 해도
 * 「누가 출동했는가」라는 행사 운영 기록이 같이 사라지는 게 더 문제였다.
 *
 * 지우는 것: 이름·전화·이메일·소셜 연결·아바타·비밀번호·2FA·세션·API 토큰·푸시 토큰·
 *           행사 참가·위치 이력·역할·명단 연결·(본인이 쓴) 신고 연락처.
 * 남기는 것: users 행(id, 「탈퇴 회원」), 신고·지령 행(좌표·시각·상태), 동의 기록.
 *
 * 🔑 시각은 주입받는다. 두 번 돌려도 결과가 같다(이미 탈퇴한 계정은 건드리지 않는다).
 */
class AccountDeletionService
{
    public const DELETED_NAME = '탈퇴 회원';

    /** 위치 이력은 한 사람 것도 수십만 행일 수 있어 청크로 지운다(운영 DB 락 시간). */
    private const PING_CHUNK = 5000;

    public function delete(User $user, ?Carbon $at = null): void
    {
        $at ??= Carbon::now();

        // 가장 민감한 것부터, 트랜잭션 «밖»에서. 중간에 실패해도 재실행이 나머지를 마저 지운다.
        while (LocationPing::where('user_id', $user->id)->limit(self::PING_CHUNK)->delete() >= self::PING_CHUNK) {
            // 다음 청크
        }

        DB::transaction(function () use ($user, $at) {
            /** @var User|null $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if (! $locked || $locked->account_deleted_at !== null) {
                return; // 이미 탈퇴했거나 없다 — 멱등
            }

            // 1. 사람에게 속한 것 — 지운다
            DeviceToken::where('user_id', $locked->id)->delete();
            EventParticipant::where('user_id', $locked->id)->delete();
            $locked->tokens()->delete();                                   // Sanctum
            DB::table('sessions')->where('user_id', $locked->id)->delete(); // 다른 기기의 로그인
            EventRoster::where('user_id', $locked->id)->update(['user_id' => null]);
            $locked->syncRoles([]);
            $locked->syncPermissions([]);

            // 2. 행사 기록에 남은 «이 사람» 식별자 — 좌표·시각·상태는 남기고 연락처·자유 입력만 비운다
            RescueRequest::where('user_id', $locked->id)
                ->update(['contact_phone' => null, 'description' => null]);
            RescueRequest::where('cancelled_by', $locked->id)
                ->update(['cancel_reason' => null]);

            // 3. 계정 행 — 익명화. 로그인 경로(전화·이메일·소셜·비밀번호·remember) 를 전부 끊는다.
            $locked->forceFill([
                'name' => self::DELETED_NAME,
                'email' => null,
                'phone' => null,
                'provider' => null,
                'provider_id' => null,
                'avatar' => null,
                'password' => Hash::make(Str::random(48)),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'must_change_password' => false,
                'account_deleted_at' => $at,
            ])->save();
        });

        $user->refresh();
    }
}
