<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Events\RequestCreated;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RequestService
{
    public function getAllRequests(User $user): Collection
    {
        if ($user->hasRole('admin') || $user->hasRole('rescuer')) {
            return Request::with(['user', 'assignedRescuer'])
                ->orderBy('priority', 'desc')
                ->orderBy('requested_at', 'desc')
                ->get();
        }

        return $user->requests()->with(['assignedRescuer'])->get();
    }

    public function createRequest(array $data, User $user): Request
    {
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
     * 신고 수정.
     *
     * 🔴 **좌표는 사후에 바꿀 수 없다.** 신고 좌표는 「신고 시점에 그 사람이 어디 있었나」라는
     *    사실 기록이고, 구조 기록·행사 리포트·법적 분쟁의 근거가 된다. 사후 수정이 가능하면
     *    그 기록 전체의 신뢰가 깨진다. 잘못된 좌표는 «고치는» 게 아니라 새 신고로 남긴다.
     */
    public function updateRequest(Request $request, array $data, User $user): Request
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('rescuer') && ! $request->isOwner($user)) {
            throw new \Exception('Unauthorized to update this request');
        }

        $this->assertCoordinatesUnchanged($request, $data);

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

    public function cancelRequest(Request $request, User $user): Request
    {
        if (! $request->isOwner($user) && ! $user->hasRole('admin')) {
            throw new \Exception('Unauthorized to cancel this request');
        }

        if (! $request->canBeCancelled()) {
            throw new \Exception('Request cannot be cancelled in current status');
        }

        $request->update([
            'status' => RequestStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        return $request->fresh(['user', 'assignedRescuer']);
    }

    /**
     * @deprecated ADR-0003 — DispatchService::assign 으로 대체. 레거시 라우트만 잠정 유지.
     */
    public function assignRescuer(Request $request, User $rescuer, User $admin): Request
    {
        if (! $admin->hasRole('admin') && ! $admin->hasRole('rescuer')) {
            throw new \Exception('Unauthorized to assign rescuer');
        }

        if (! $rescuer->hasRole('rescuer')) {
            throw new \Exception('Selected user is not a rescuer');
        }

        $request->update([
            'assigned_rescuer_id' => $rescuer->id,
            'status' => RequestStatus::IN_PROGRESS,
            'responded_at' => now(),
        ]);

        return $request->fresh(['user', 'assignedRescuer']);
    }

    public function getRequestById(int $id, User $user): Request
    {
        $request = Request::with(['user', 'assignedRescuer'])->findOrFail($id);

        if (! $user->hasRole('admin') && ! $user->hasRole('rescuer') && ! $request->isOwner($user)) {
            throw new \Exception('Unauthorized to view this request');
        }

        return $request;
    }
}
