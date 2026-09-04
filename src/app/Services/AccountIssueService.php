<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * 운영진 회원계정 «일괄 발급» (ADR-0009).
 *
 * 명단(EventRoster)에는 있는데 아직 계정이 없는 사람에게 초기 비밀번호로 계정을 만든다.
 * 첫 로그인에서 비밀번호 변경 + 필수 동의를 강제하는 것은 EnsurePasswordSetup 미들웨어다.
 *
 * 설계상 지켜야 하는 것 —
 *  1. **명단 임포트(ParticipantImportService)는 여전히 계정을 만들지 않는다.** 발급은 관리자가
 *     «별도 버튼»으로 명시적으로 일으키는 것이다. 두 경로를 섞으면 명단 재업로드가 계정을
 *     건드리는 통로가 된다.
 *  2. **역할 배정의 단일 writer 는 EventParticipantService::assignRole** 이다(임포트와 같다).
 *  3. **멱등.** 발급은 명단 행을 claim 하므로, 다시 눌러도 이미 발급된 행은 대기 목록에서 빠져
 *     건너뛴다. 초기 비밀번호를 잃었을 때의 재발급은 reissuePassword(계정 단위)로 따로 한다.
 *  4. **초기 비밀번호는 모두 «password» 로 통일한다.** 현장에서 100명 넘는 사람에게 서로 다른
 *     임의 비밀번호를 읽어 줄 수 없다(운영 요청). 첫 로그인에서 반드시 바꾸므로 이 값이 노출돼도
 *     창은 짧고, 회원 목록의 「미로그인 발급」 배지로 아직 안 바꾼 계정이 보인다(ADR-0009).
 */
class AccountIssueService
{
    /** 발급 계정의 초기 비밀번호 — 전원 동일. 첫 로그인에서 강제로 바뀐다(ADR-0009). */
    public const INITIAL_PASSWORD = 'password';

    public function __construct(private EventParticipantService $participants) {}

    /**
     * 이 행사의 claim-대기 명단에 대해 계정을 발급한다.
     *
     * @return array{pending:int,issued:int,linked:int,failed:int,errors:list<array{phone:string,reason:string}>}
     */
    public function issueForRoster(Project $project, User $issuedBy): array
    {
        $rows = EventRoster::forProject($project->id)->unclaimed()
            ->orderBy('role')->orderBy('name')->get();

        $report = [
            'pending' => $rows->count(),
            'issued' => 0,
            'linked' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $roster) {
            // 🔑 한 행마다 실행 시간 타이머를 되돌린다. bcrypt 해시(≈60ms) + DB 쓰기가 행마다 쌓여
            //    160명이면 45초가 걸렸고, 웹 SAPI 의 max_execution_time(30s)에 걸려 터졌다.
            //    행 단위로 리셋하면 «한 행»만 제한 안에 끝나면 되므로 명단 크기와 무관하게 안전하다.
            if (function_exists('set_time_limit')) {
                @set_time_limit(30);
            }

            try {
                $outcome = $this->issueRow($project, $roster, $issuedBy);
                $report[$outcome]++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['errors'][] = ['phone' => (string) $roster->phone, 'reason' => $e->getMessage()];
            }
        }

        return $report;
    }

    /**
     * 계정 한 개 재발급 — «아직 활성화 전»(isIssuedPending) 계정에만.
     *
     * 초기 비밀번호를 다시 «password» 로 되돌린다. 본인이 이미 비밀번호를 정한 계정은 덮지 않는다.
     *
     * @throws RuntimeException 활성화된 계정이거나 발급 계정이 아닐 때
     */
    public function reissuePassword(User $user): void
    {
        if (! $user->isIssuedPending()) {
            throw new RuntimeException('이미 본인이 사용 중인 계정은 재발급할 수 없습니다.');
        }

        $user->forceFill([
            'password' => Hash::make(self::INITIAL_PASSWORD),
            'must_change_password' => true,
        ])->save();
    }

    /**
     * @return 'issued'|'linked'
     */
    private function issueRow(Project $project, EventRoster $roster, User $issuedBy): string
    {
        // 명단 저장 시 이미 숫자만 정규화돼 있다(ParticipantImportService). 그래도 방어적으로 한 번 더.
        $phone = preg_replace('/[^0-9]/', '', (string) $roster->phone);
        $role = $roster->role;
        $name = trim((string) $roster->name) ?: $phone;

        return DB::transaction(function () use ($project, $roster, $issuedBy, $phone, $role, $name) {
            $user = User::where('phone', $phone)->first();

            if ($user) {
                // 업로드와 발급 사이에 본인이 가입했다 — 계정을 만들지 않고 역할만 붙인다.
                $this->participants->assignRole($project, $user, $role, ParticipantStatus::ACTIVE);
                $this->claim($roster, $user);

                return 'linked';
            }

            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(self::INITIAL_PASSWORD),
                'must_change_password' => true,
                'issued_at' => now(),
                'issued_by' => $issuedBy->id,
            ]);

            $this->participants->assignRole($project, $user, $role, ParticipantStatus::ACTIVE);
            $this->claim($roster, $user);

            return 'issued';
        });
    }

    private function claim(EventRoster $roster, User $user): void
    {
        if (! $roster->isClaimed()) {
            $roster->forceFill(['user_id' => $user->id, 'claimed_at' => now()])->save();
        }
    }
}
