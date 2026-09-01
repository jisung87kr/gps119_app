<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 화면을 떠나지 않고 동의를 남긴다.
 *
 * 🔑 위치 공유를 켜는 «그 자리»에서 받아야 한다. 동의 페이지로 내보내면 사용자는
 *    행사 화면을 잃고, 돌아왔을 때 무엇을 하려던 참이었는지 다시 찾아야 한다.
 */
class ConsentApiController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    /** 지금 무엇이 비어 있는가. 화면이 물어보고 그린다. */
    public function index(Request $request): JsonResponse
    {
        return response()->success([
            'missing' => array_map(fn (ConsentType $t) => [
                'type' => $t->value,
                'label' => $t->label(),
                'url' => route($t->routeName()),
            ], $this->consents->missingRequired($request->user())),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $required = array_map(fn (ConsentType $t) => $t->value, ConsentType::required());

        $validated = $request->validate([
            'consents' => ['required', 'array'],
            'consents.*' => [Rule::in($required)],
        ]);

        // 🔴 «전부» 있어야 한다. 부분 동의를 받아 두면 나중에 「동의했다」로 읽힌다.
        if (array_diff($required, $validated['consents'])) {
            return response()->error('필수 약관에 모두 동의해야 합니다.', 422, [
                'code' => 'consent_incomplete',
            ]);
        }

        $this->consents->record($request->user(), ConsentType::required(), $request->ip());

        return response()->success(['missing' => []], '동의가 기록되었습니다.');
    }
}
