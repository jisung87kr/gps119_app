<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Events\RequestCreated;
use App\Events\RequestStatusUpdated;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RequestService
{
    public function __construct(private DispatchService $dispatchService) {}

    public function getAllRequests(User $user): Collection
    {
        if ($user->hasRole('admin')) {
            return Request::with(['user', 'assignedRescuer'])
                ->orderBy('priority', 'desc')
                ->orderBy('requested_at', 'desc')
                ->get();
        }

        return $user->requests()->with(['assignedRescuer'])->get();
    }

    public function createRequest(array $data, User $user): Request
    {
        // 🔑 «지금 행사 중인 구급대»는 신고를 올릴 수 없다 (2026-08-12 현장 결정).
        //    화면만 숨기면 API 는 그대로 열려 있고, 이 앱의 신고는 JSON 한 번이면 만들어진다.
        //    「기능 자체를 차단」이라는 결정을 지키려면 규칙이 서비스에 있어야 한다.
        //    (본인이 도움이 필요하면 119·상황실 전화 — 차단 화면이 그 두 개를 준다.)
        //
        // 판정 기준이 시스템 롤에서 «행사 역할»로 바뀌었다. 행사가 끝나면 그 사람도
        // 평범한 사용자로 돌아가 신고할 수 있다 — 비번기에도 못 하는 건 부작용이었다.
        if ($user->usesDispatchHome()) {
            throw new \RuntimeException('구급대 계정은 구조요청을 접수할 수 없습니다. 119 또는 상황실로 전화해 주세요.');
        }

        // 🔴 행사에 «참가 중인» 사람의 신고는 그 행사로 간다.
        //
        //    화면 링크에 맡겼더니 실제로 깨졌다: 행사에 입장한 참가자가 「구조요청 하기」를
        //    누르면 slug 없는 /requests/create 로 가고, 그 신고가 「상시 운영」에 붙어서
        //    **정작 그 행사의 관제 화면에는 뜨지 않았다.** 신고는 접수됐는데 상황실은
        //    모르는 상태 — 이 도메인에서 가장 나쁜 실패다.
        //
        //    링크는 9곳이었고 고쳐도 다음에 또 생긴다. 귀속 규칙을 여기 한 곳에 둔다.
        //    (모델 creating 훅의 「상시 운영」 귀속은 그대로 최후 폴백으로 남는다 — ADR-0005)
        if (empty($data['project_id'])) {
            $data['project_id'] = $this->resolveEventFor($user);
        }

        // type 미지정 시 기본값(other). priority 미지정 시 type->defaultPriority() 자동 매핑.
        // priority 가 명시되면 그 값을 우선(상황실 수동 상향).
        $type = isset($data['type']) ? RequestType::from($data['type']) : RequestType::OTHER;
        if (empty($data['priority'])) {
            $data['priority'] = $type->defaultPriority();
        }

        $requestData = array_merge($data, [
            'user_id' => $user->id,
            'type' => $type,
            'status' => RequestStatus::PENDING,
            'requested_at' => now(),
        ]);

        $request = Request::create($requestData);

        // 🔑 도메인 이벤트는 «서비스»가 발행한다. 예전에는 Request 모델의 created 훅에
        //    있었는데, 그러면 «신고가 저장됐다»(DB 사실)와 «구조요청이 접수됐다»(도메인
        //    사건)를 구분할 수 없다. 팩토리·시드가 행을 하나 만들기만 해도 관제 브로드캐스트와
        //    통지가 전부 나갔다. 푸시가 붙으면 시드 한 번에 실제 발송이 나가게 된다.
        //    (모델의 creating 훅에 남아 있는 기본 행사 귀속(ADR-0005)은 «불변식»이라 그대로 둔다 —
        //     어떤 경로로 만들어도 깨지면 안 되는 것과, 접수 흐름에서만 일어나야 하는 것의 차이다.)
        RequestCreated::dispatch($request->load('user'));

        return $request;
    }

    /**
     * 화면이 행사를 지정하지 않았을 때 «이 사람의» 행사를 찾는다.
     *
     * 여럿이면 «마지막으로 입장한» 행사다 (User::currentEvent). 응급 화면에서 드롭다운을
     * 고르게 할 수는 없으므로, 마찰 없이 쓸 수 있는 근거는 그것뿐이다. 대신 조용히
     * 정하지 않는다 — 신고 화면이 어느 행사로 접수되는지 항상 보여준다.
     *
     * @return int|null 귀속할 행사 id. null 이면 모델 훅이 「상시 운영」으로 보낸다.
     */
    private function resolveEventFor(User $user): ?int
    {
        return $user->currentEvent()?->id;
    }

    /**
     * 신고 수정.
     *
     * 🔴 **좌표는 사후에 바꿀 수 없다.** 신고 좌표는 「신고 시점에 그 사람이 어디 있었나」라는
     *    사실 기록이고, 구조 기록·행사 리포트·법적 분쟁의 근거가 된다. 사후 수정이 가능하면
     *    그 기록 전체의 신뢰가 깨진다. 잘못된 좌표는 «고치는» 게 아니라 새 신고로 남긴다.
     */
    public function updateRequest(Request $request, array $data, User $user): Request
    {
        if (! $user->hasRole('admin') && ! $request->isOwner($user)) {
            throw new \Exception('Unauthorized to update this request');
        }

        $this->assertCoordinatesUnchanged($request, $data);

        // 🔑 취소는 이 문으로 들어올 수 없다. 여기로 들어오면 활성 지령 회수도, 신고자
        //    통지도, 「누가 왜 껐는가」 기록도 전부 건너뛴다 — 같은 결과처럼 보이지만
        //    취소의 절반이 빠진 상태다. 취소는 cancelRequest() 하나뿐이다.
        if (($data['status'] ?? null) === RequestStatus::CANCELLED || ($data['status'] ?? null) === RequestStatus::CANCELLED->value) {
            throw new \RuntimeException('신고 취소는 취소 전용 경로로만 처리할 수 있습니다.');
        }

        $request->update($data);

        if (isset($data['status']) && $data['status'] === RequestStatus::IN_PROGRESS && ! $request->responded_at) {
            $request->update(['responded_at' => now()]);
        }

        if (isset($data['status']) && $data['status'] === RequestStatus::COMPLETED && ! $request->completed_at) {
            $request->update(['completed_at' => now()]);
        }

        return $request->fresh(['user', 'assignedRescuer']);
    }

    /**
     * 좌표 수정 시도를 막는다.
     *
     * 🔑 «조용히 무시»하지 않고 던지는 이유 — 값을 슬쩍 빼버리면 부르는 쪽은 성공 응답을
     *    받고 좌표가 바뀐 줄 안다. 나중에 「분명히 고쳤는데 왜 그대로냐」로 돌아온다.
     *
     * 다만 «같은 값»을 다시 보내는 건 통과시킨다. 클라이언트가 객체 전체를 되돌려보내는
     * 흔한 패턴에서, 아무것도 바꾸지 않는 요청까지 막을 이유는 없다.
     *
     * 🔑 이 검사가 컨트롤러가 아니라 여기 있는 이유 — 현재 API 는 validate() 화이트리스트로
     *    좌표를 애초에 받지 않아서 «지금은» 안전하다. 하지만 그 안전은 「그 목록에 좌표를
     *    추가하지 않는다」는 규율에 의존한다. 두 번째 호출자(관리자 화면·콘솔 명령)가 생기면
     *    조용히 사라지는 종류의 보호다. 불변식은 그것을 소유한 층에 둔다.
     */
    private function assertCoordinatesUnchanged(Request $request, array $data): void
    {
        foreach (['latitude', 'longitude'] as $column) {
            if (! array_key_exists($column, $data) || $data[$column] === null) {
                continue;
            }

            // decimal:8 캐스팅 때문에 '37.56650000' 과 37.5665 를 비교하게 된다.
            // 양쪽을 같은 소수 자리로 정규화해서 비교한다.
            $incoming = number_format((float) $data[$column], 8, '.', '');
            $current = number_format((float) $request->getOriginal($column), 8, '.', '');

            if ($incoming !== $current) {
                throw new \RuntimeException(
                    '신고 좌표는 수정할 수 없습니다. 위치가 잘못됐다면 새 신고로 접수하세요.'
                );
            }
        }
    }

    /**
     * 신고 취소 — «모든» 취소 경로의 단일 진입점.
     *
     * 취소 권한(2026-08-12 결정):
     *   - admin: 항상
     *   - 그 행사 상황실(controller): 항상
     *   - 신고자 본인: 활성 지령이 «없을 때만». 대원이 이미 수락하고 이동 중인데
     *     신고자가 말없이 지워버리면, 그 사람은 아무도 없는 현장으로 계속 간다.
     *     배정 후에는 상황실 판단을 거친다.
     *
     * 🔑 취소는 status 만 바꾸는 일이 아니다. 활성 지령을 같이 회수하지 않으면 그 지령은
     *    고아가 되고(대원 화면은 신고 status 를 보지 않는다), 나중에 그 대원이 완료를
     *    누르면 취소가 완료로 덮여 쓰인다. 그래서 회수 → 취소가 한 트랜잭션이다.
     */
    public function cancelRequest(Request $request, User $user, ?string $reason = null): Request
    {
        if (! $request->canBeCancelled()) {
            throw new \Exception('Request cannot be cancelled in current status');
        }

        $this->assertCanCancel($request, $user);

        DB::transaction(function () use ($request, $user, $reason) {
            // 먼저 회수한다. 회수 전이는 신고를 pending 으로 되돌리려 하지만, 바로 아래에서
            // CANCELLED 로 확정되고 이후 전이는 종료상태 가드에 막힌다.
            $this->dispatchService->recallAllForRequest($request, $user, $reason ?? '신고 취소');

            $request->forceFill([
                'status' => RequestStatus::CANCELLED,
                'completed_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save();
        });

        $fresh = $request->fresh(['user', 'assignedRescuer']);

        // 신고자·상황실에 알린다. 예전엔 취소가 아무 이벤트도 쏘지 않아서, 신고자 화면은
        // 폴링이 돌 때까지 모르고 관제 화면은 끝까지 몰랐다.
        RequestStatusUpdated::dispatch($fresh);

        return $fresh;
    }

    /**
     * 취소 권한 검사. 신고자 본인은 «배정 전»에만.
     */
    private function assertCanCancel(Request $request, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $role = $request->project ? $user->eventRoleIn($request->project) : null;
        if ($role !== null && $role->canDispatch()) {
            return; // 그 행사 상황실
        }

        if (! $request->isOwner($user)) {
            throw new \Exception('Unauthorized to cancel this request');
        }

        if ($request->activeDispatch()->exists()) {
            throw new \RuntimeException(
                '이미 구조대가 배정되어 직접 취소할 수 없습니다. 상황실로 전화해 주세요.'
            );
        }
    }

    public function getRequestById(int $id, User $user): Request
    {
        $request = Request::with(['user', 'assignedRescuer'])->findOrFail($id);

        // 판정은 모델이 한다(Request::isVisibleTo) — 웹 라우트도 같은 것을 읽는다.
        // 규칙이 두 군데였을 때 웹 쪽이 조용히 비어 있었다.
        if (! $request->isVisibleTo($user)) {
            throw new \Exception('Unauthorized to view this request');
        }

        return $request;
    }
}
