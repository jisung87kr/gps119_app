<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Events\DispatchAssigned;
use App\Events\DispatchStatusUpdated;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\DispatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * 지령 이벤트는 «커밋된 뒤에만» 나간다.
 *
 * DispatchService 는 배정·전이를 `DB::transaction` 안에서 처리하면서 그 안에서
 * 이벤트를 발행한다. 트랜잭션이 나중에 롤백되면 **DB 에 없는 지령이 이미
 * 브로드캐스트된 상태**가 된다. 지금은 화면 한 칸이 틀리는 정도지만, 푸시(N1)를
 * 붙이면 «구급대원 폰이 울렸는데 지령이 없다»가 된다 — 안전 도메인에서 최악이다.
 *
 * 그래서 이벤트에 `ShouldDispatchAfterCommit` 를 달았고, 이 파일이 그 계약을 고정한다.
 * 인터페이스가 붙어 있는지 «구조»를 보는 게 아니라 롤백·커밋 양쪽 «동작»을 본다 —
 * 인터페이스만 확인하면 나중에 서비스가 트랜잭션 밖으로 나가도 초록불이 뜬다.
 *
 * Event::fake() 를 쓰지 않는 이유: 가짜 디스패처는 after-commit 대기를 재현하지 않아
 * 검증하려는 바로 그 동작이 사라진다. 진짜 리스너를 달아 기록한다.
 */
class DispatchEventAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    private DispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(DispatchService::class);
    }

    /** @return array{project: Project, controller: User, paramedic: User, request: RescueRequest} */
    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $paramedic->id,
        ]);

        $request = RescueRequest::factory()->for(User::factory()->create())->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'controller', 'paramedic', 'request');
    }

    /**
     * 지정한 이벤트가 발행될 때마다 카운트를 올리는 실제 리스너를 단다.
     */
    private function counterFor(string $eventClass): object
    {
        $counter = new class
        {
            public int $count = 0;
        };

        Event::listen($eventClass, function () use ($counter) {
            $counter->count++;
        });

        return $counter;
    }

    public function test_rolled_back_assignment_is_never_broadcast(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $fired = $this->counterFor(DispatchAssigned::class);

        try {
            DB::transaction(function () use ($r, $p, $c) {
                $this->service->assign($r, $p, $c);

                // 배정 «뒤에» 실패하는 후속 작업. 지령 행은 롤백된다.
                throw new RuntimeException('후속 작업 실패');
            });
        } catch (RuntimeException) {
            // 기대한 실패
        }

        $this->assertSame(0, Dispatch::count(), '롤백됐으므로 지령 행이 남지 않아야 한다');
        $this->assertSame(0, $fired->count, '롤백된 지령이 브로드캐스트됐다 — 폰은 울리고 지령은 없는 상태');
    }

    public function test_committed_assignment_is_broadcast(): void
    {
        // 음성 대조군만 있으면 «아무 일도 안 일어나는» 구현도 통과한다. 양성 대조군이 필요하다.
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $fired = $this->counterFor(DispatchAssigned::class);

        $this->service->assign($r, $p, $c);

        $this->assertSame(1, Dispatch::count());
        $this->assertSame(1, $fired->count, '커밋된 지령은 반드시 나가야 한다');
    }

    public function test_rolled_back_transition_is_never_broadcast(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->service->assign($r, $p, $c);

        $fired = $this->counterFor(DispatchStatusUpdated::class);

        try {
            DB::transaction(function () use ($dispatch, $p) {
                $this->service->transition($dispatch, DispatchStatus::ACCEPTED, $p);

                throw new RuntimeException('후속 작업 실패');
            });
        } catch (RuntimeException) {
            // 기대한 실패
        }

        $this->assertSame(
            DispatchStatus::ASSIGNED,
            $dispatch->fresh()->status,
            '롤백됐으므로 상태가 그대로여야 한다'
        );
        $this->assertSame(0, $fired->count, '롤백된 상태 전이가 브로드캐스트됐다');
    }

    public function test_committed_transition_is_broadcast(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->service->assign($r, $p, $c);

        $fired = $this->counterFor(DispatchStatusUpdated::class);

        $this->service->transition($dispatch, DispatchStatus::ACCEPTED, $p);

        $this->assertSame(DispatchStatus::ACCEPTED, $dispatch->fresh()->status);
        $this->assertSame(1, $fired->count);
    }
}
