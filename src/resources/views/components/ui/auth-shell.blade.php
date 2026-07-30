{{--
    인증 화면 공용 껍데기 — src/tmp/login.html 기준.
    흰 배경 + 세로 중앙 정렬 + max-w-md. 로고 마크와 워드마크는 고정이고
    아래 한 줄(subtitle)로 화면 목적을 구분한다.
--}}
@props(['subtitle' => '언제 어디서든, 가장 빠른 구조 요청'])

<div class="min-h-screen bg-white">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-10">
        <div class="mb-10 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-600 text-white shadow-sm">
                <x-ui.icon name="bolt-outline" class="h-[30px] w-[30px]" />
            </span>
            <h1 class="mt-4 text-[26px] font-extrabold tracking-tight text-ink-950">
                GPS<span class="text-brand-600">119</span>
            </h1>
            <p class="mt-1.5 text-base text-ink-500">{{ $subtitle }}</p>
        </div>

        {{ $slot }}
    </div>
</div>
