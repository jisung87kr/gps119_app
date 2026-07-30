{{--
    HTTP 상태코드 에러 페이지 공용 셸 (401/402/403/404/419/429/500/503 이 @extends).

    빌드된 CSS·폰트에 의존하지 않도록 스타일을 인라인으로 둔다 — 에셋 파이프라인이나
    서버가 망가진 상황에서도 떠야 하는 화면이라 @vite 를 쓰지 않는다.
    색은 "Ink + Brand" 팔레트 hex 를 그대로 옮겼다.
--}}
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GPS119 · @yield('title')</title>
    <link rel="icon" type="image/png" href="/icon-192.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
            background: #FAFAF9;                 /* ink-50 */
            color: #0E0C0A;                      /* ink-950 */
            font-family: 'Pretendard', ui-sans-serif, system-ui, -apple-system,
                'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .box { width: 100%; max-width: 24rem; text-align: center; }
        .mark {
            width: 4rem; height: 4rem; margin: 0 auto 1.5rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 1.5rem; background: #0E6E7C; color: #fff;  /* brand-600 */
        }
        .code {
            margin: 0;
            font-size: 3.5rem; line-height: 1; font-weight: 800;
            letter-spacing: -0.02em; color: #D0CDC7;                  /* ink-300 */
        }
        .message {
            margin: 0.75rem 0 0; font-size: 1.25rem; font-weight: 800;
            line-height: 1.4; word-break: keep-all;
        }
        .desc {
            margin: 0.5rem 0 0; font-size: 0.9375rem; line-height: 1.7;
            color: #79746C;                                           /* ink-500 */
            word-break: keep-all;
        }
        .actions { margin-top: 2rem; display: grid; gap: 0.625rem; }
        .btn {
            display: flex; align-items: center; justify-content: center;
            padding: 1rem; border-radius: 1rem; border: 0;
            font-size: 1rem; font-weight: 700; text-decoration: none; cursor: pointer;
            font-family: inherit;
        }
        .btn-primary { background: #0E6E7C; color: #fff; }            /* brand-600 */
        .btn-primary:active { background: #0A5560; }                  /* brand-700 */
        .btn-secondary {
            background: #fff; color: #17140F;                         /* ink-900 */
            border: 2px solid #E5E3DF;                                /* ink-200 */
        }
        .btn-secondary:active { background: #FAFAF9; }
    </style>
</head>
<body>
    <div class="box">
        <div class="mark" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" />
            </svg>
        </div>

        <p class="code">@yield('code')</p>
        <h1 class="message">@yield('message')</h1>
        <p class="desc">@yield('description', '주소를 다시 확인해 주세요. 문제가 계속되면 잠시 후 다시 시도해 주세요.')</p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">구조요청 화면으로</a>
            <button class="btn btn-secondary" type="button" onclick="history.back()">이전으로 돌아가기</button>
        </div>
    </div>
</body>
</html>
