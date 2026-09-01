<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 웹뷰·브라우저에서 올라온 JS 에러를 받는다 (M-16).
 *
 * 🔴 셸은 원격 URL 을 그대로 띄우므로 **앱 안에서 난 JS 에러를 볼 방법이 없었다.**
 *    인스펙터를 붙이지 않는 한 콘솔이 없고, 그래서 증상을 화면에 글자로 그려서 쫓았다.
 *
 * 🔑 **DB 가 아니라 전용 로그 채널로 간다.** 이건 «관측»이지 업무 데이터가 아니다 —
 *    테이블을 만들면 보존기간·삭제요청(M-6) 대상이 하나 더 늘어난다.
 *
 * 🔑 **라라벨 로그와 섞지 않는다.** 클라이언트발 잡음이 laravel.log 를 덮으면
 *    정작 서버 장애를 못 찾는다.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request)
    {
        // 경계에서 한 번만 검증한다. 상한은 클라이언트(errorReport.js LIMITS)와 맞춘다.
        $data = $request->validate([
            'kind' => ['required', Rule::in(['error', 'unhandledrejection'])],
            'message' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:300'],
            'line' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'column' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'stack' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'appVersion' => ['nullable', 'string', 'max:40'],
        ]);

        Log::channel('client')->warning('[client] '.$data['message'], [
            'kind' => $data['kind'],
            'source' => $data['source'] ?? null,
            'line' => $data['line'] ?? null,
            'column' => $data['column'] ?? null,
            'stack' => $data['stack'] ?? null,
            'url' => $data['url'] ?? null,
            'platform' => $data['platform'] ?? 'web',
            'appVersion' => $data['appVersion'] ?? null,
            // 🔑 «누구»는 id 까지만. 이름·연락처를 로그에 남기지 않는다.
            'userId' => Auth::guard('web')->id(),
            'ua' => substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->noContent();
    }
}
