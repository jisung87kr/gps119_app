<?php

namespace App\Http\Controllers\Api;

use App\Enums\PushPlatform;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 푸시 수신 통로 등록/해제 (mobile-app N1, 03 §3-2).
 *
 * 🔴 **토큰을 URL path 에 넣지 않는다.** `DELETE /api/devices/{token}` 형태면
 * 액세스 로그·리버스 프록시 로그·에러 리포트에 자격증명이 그대로 남는다.
 * 등록·해제 모두 본문(body)으로 받는다.
 */
class DeviceTokenApiController extends Controller
{
    /**
     * 기기 등록/갱신. 같은 토큰이 다시 오면 되살린다(폐기 → 재구독).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::enum(PushPlatform::class)],
            'token' => ['required', 'string', 'max:2048'],
            // 웹 푸시 구독 공개키. 앱(FCM)은 보내지 않는다.
            //
            // 🔑 «형식»까지 여기서 본다. 길이가 틀린 키는 암호화 단계에서 실패하는데,
            //    그 실패는 네트워크 장애와 구분되지 않아 FAILED(재시도 대상)로 분류된다.
            //    즉 영영 성공하지 못할 통로를 영원히 재시도하게 된다. 경계에서 막는다.
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['required_with:keys', 'string', $this->base64UrlBytes(65)],
            'keys.auth' => ['required_with:keys', 'string', $this->base64UrlBytes(16)],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $platform = PushPlatform::from($validated['platform']);

        if ($platform === PushPlatform::WEB && empty($validated['keys'])) {
            // 키 없는 웹 구독은 암호화가 불가능해 «등록은 되지만 영영 안 가는» 통로가 된다.
            // 조용히 받아두면 나중에 「알림이 안 온다」로 돌아온다. 여기서 거절한다.
            return response()->error('웹 푸시 구독에는 keys(p256dh, auth)가 필요합니다.', 422);
        }

        $device = DeviceToken::register(
            $request->user(),
            $platform,
            $validated['token'],
            $validated['keys'] ?? null,
            $validated['app_version'] ?? null,
        );

        return response()->success([
            'id' => $device->id,
            'platform' => $device->platform->value,
        ], '기기가 등록되었습니다.', 201);
    }

    /**
     * base64url 로 인코딩된 «정확히 N바이트»인가.
     *
     * 웹 푸시 규격상 p256dh 는 비압축 P-256 점 65바이트(0x04‖x32‖y32),
     * auth 는 16바이트다. 길이가 다르면 브라우저가 만든 구독이 아니다.
     */
    private function base64UrlBytes(int $bytes): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($bytes) {
            $decoded = base64_decode(strtr((string) $value, '-_', '+/'), true);

            if ($decoded === false || strlen($decoded) !== $bytes) {
                $fail("{$attribute} 는 base64url 로 인코딩된 {$bytes}바이트여야 합니다.");
            }
        };
    }

    /**
     * 기기 해제. 본인 소유일 때만.
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
        ]);

        $device = DeviceToken::query()
            ->where('token_hash', DeviceToken::hashFor($validated['token']))
            ->forUser($request->user()->id)
            ->first();

        // 남의 토큰인지 없는 토큰인지 구분해 알려주지 않는다 — 토큰 존재 여부가 새면
        // 유출된 토큰의 유효성을 확인하는 데 쓸 수 있다. 어느 쪽이든 «해제됨»으로 답한다.
        $device?->revoke();

        return response()->success(null, '기기 등록이 해제되었습니다.');
    }
}
