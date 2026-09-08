<?php

namespace App\Console\Commands;

use App\Enums\ConsentType;
use App\Enums\EventRole;
use App\Models\Project;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\EventParticipantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 스토어 심사용 데모 — 계정 3종(참가자·구급대원·상황실) + 심사용 행사.
 *
 * Apple 은 «계정 종류별» 자격증명을 요구한다(2026-09-08, 2.1 Information Needed). 심사 기간 동안
 * 살아 있어야 하므로 행사 종료일은 오늘 + N일이고, 다시 돌리면 종료일만 늘어난다.
 *
 * 멱등: 계정은 전화번호로, 행사는 slug 로 찾는다. 있으면 만들지 않고 «맞춘다»(동의·참가·역할·종료일).
 * 비밀번호는 «만들 때»만 정한다 — 재실행이 심사자에게 준 비밀번호를 바꾸면 안 된다(--reset-password 로만).
 *
 * 🔴 비밀번호는 stdout 에 한 번만 나온다. 저장소·로그·문서에 적지 않는다. App Store Connect 에만.
 *
 * 참가는 실제 입장 경로(joinByCode)를 탄다 — 심사자가 보는 상태(위치 공유 켜짐으로 시작)가
 * 진짜 참가자와 같아야 녹화·답변과 실제가 어긋나지 않는다.
 */
class ReviewDemoSetup extends Command
{
    protected $signature = 'review:demo
                            {--password= : 세 계정의 공통 비밀번호. 미지정 시 생성해 «한 번만» 출력한다}
                            {--days=30 : 심사용 행사 종료일까지의 일수(오늘부터)}
                            {--reset-password : 이미 있는 계정의 비밀번호도 다시 설정한다}';

    protected $description = '스토어 심사용 데모 계정 3종(참가자·구급대원·상황실)과 심사용 행사를 만든다 (멱등)';

    public const PROJECT_SLUG = 'app-review-demo';

    public const PROJECT_NAME = 'App Review 데모 행사';

    /** 010-0000-xxxx 는 통신사가 배정하지 않는 대역이라 실제 사람과 겹치지 않는다. */
    public const ACCOUNTS = [
        ['phone' => '01000000001', 'name' => '심사 데모 참가자', 'role' => EventRole::PARTICIPANT],
        ['phone' => '01000000002', 'name' => '심사 데모 구급대원', 'role' => EventRole::PARAMEDIC],
        ['phone' => '01000000003', 'name' => '심사 데모 상황실', 'role' => EventRole::CONTROLLER],
    ];

    public function handle(ConsentService $consents, EventParticipantService $participants): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days 는 1 이상이어야 합니다.');

            return self::FAILURE;
        }

        // role() 스코프는 역할 자체가 없으면 예외를 던진다 — 빈 DB 에서도 «없다»로 끝나게 whereHas 로 찾는다
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->orderBy('id')->first();
        if (! $admin) {
            $this->error('관리자 계정이 없어 행사를 만들 수 없습니다 (projects.created_by). RolePermissionSeeder 를 먼저 실행하세요.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(12, symbols: false);
        }
        $reset = (bool) $this->option('reset-password');

        $rows = [];
        $project = DB::transaction(function () use ($days, $admin, $password, $reset, $consents, $participants, &$rows) {
            $project = $this->upsertProject($days, $admin);

            foreach (self::ACCOUNTS as $spec) {
                $user = User::where('phone', $spec['phone'])->first();
                $passwordShown = $password;

                if (! $user) {
                    $user = User::create([
                        'name' => $spec['name'],
                        'phone' => $spec['phone'],
                        'password' => Hash::make($password),
                    ]);
                } elseif ($reset) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                } else {
                    // 같은 번호의 계정이 이미 있다 — 누가 만든 것이든 데모 행사에 «넣기만» 한다.
                    // 비밀번호를 모르면 --reset-password. 조용히 넘어가면 심사자에게 못 들어가는 계정을 준다.
                    $passwordShown = '(기존 유지)';
                    $this->warn("{$spec['phone']} 는 이미 있던 계정({$user->name}, #{$user->id})을 재사용했습니다. 비밀번호를 모르면 --reset-password 로 다시 설정하세요.");
                }

                // 발급 계정 셋업 게이트(EnsurePasswordSetup)에 걸리지 않게
                if ($user->must_change_password) {
                    $user->forceFill(['must_change_password' => false])->save();
                }

                // 동의가 없으면 위치 공유를 켤 수 없고 ping 이 409 다 — 심사자가 그 벽을 만나면 안 된다
                $consents->record($user, ConsentType::required());

                // 실제 입장 경로 → 필요하면 역할만 올린다 (재입장은 역할을 안 건드린다)
                $participants->joinByCode($project->join_code, $user);
                if ($spec['role'] !== EventRole::PARTICIPANT) {
                    $participants->assignRole($project, $user, $spec['role']);
                }

                $rows[] = [
                    $spec['role']->label(),
                    $spec['phone'],
                    $passwordShown,
                    $this->landingFor($spec['role'], $project),
                ];
            }

            return $project;
        });

        $this->info("심사용 행사: {$project->name} (#{$project->id}, {$project->status}, 종료 {$project->end_date->toDateString()})");
        $this->line("입장 코드: {$project->join_code}   입장 URL: {$project->getJoinUrl()}");
        $this->newLine();
        $this->table(['역할', 'ID(전화번호)', '비밀번호', '로그인 후 첫 화면'], $rows);
        $this->newLine();
        if ($generated) {
            $this->warn('비밀번호는 여기 한 번만 표시됩니다. App Store Connect 에만 적고, 저장소·문서에는 남기지 마세요.');
        }
        $this->line('심사자에게: 계정 삭제는 새로 가입한 계정으로 시험하도록 안내한다 — 데모 계정을 지우면 남은 심사가 막힌다.');

        return self::SUCCESS;
    }

    private function upsertProject(int $days, User $admin): Project
    {
        // updateOrCreate 는 soft-delete 된 행을 못 봐서 slug unique 에 걸린다 — 지웠던 것이면 되살린다
        $project = Project::withTrashed()->firstOrNew(['slug' => self::PROJECT_SLUG]);
        if ($project->exists && $project->trashed()) {
            $project->restore();
        }

        if (! $project->exists) {
            $project->name = self::PROJECT_NAME;
            $project->description = '스토어 심사(App Store · Play) 데모용 행사. 실제 행사가 아닙니다.';
            $project->start_date = today();
            $project->created_by = $admin->id;
        }

        $project->end_date = today()->addDays($days);
        $project->is_active = true;
        // creating 훅이 status 를 정하지만, 재실행(종료일 연장)에는 훅이 없다 — 여기서 맞춘다
        $project->status = $project->getComputedStatus();
        $project->save();

        return $project;
    }

    private function landingFor(EventRole $role, Project $project): string
    {
        return match ($role) {
            EventRole::CONTROLLER => url("/control?project={$project->id}"),
            EventRole::PARAMEDIC => url("/events/{$project->id}/dispatch"),
            default => url('/requests/create'),
        };
    }
}
