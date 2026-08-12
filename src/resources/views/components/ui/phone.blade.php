{{--
    전화번호 표시 — 기본은 «가린 상태».

    왜 필요한가: 관리자 화면은 전화번호를 목록으로 펼쳐 놓는 자리다. 화면공유·스크린샷·
    어깨너머로 수십 명의 연락처가 한 번에 새는 경로가 여기다. DB 컬럼 암호화는 이걸
    전혀 막지 못한다 — 관리자 화면은 어차피 복호화해서 보여주기 때문이다.

    ⚠️ 이건 «우발적 노출»을 막는 장치이지 접근통제가 아니다. reveal 을 켜면 원문이
       DOM 에 들어가므로 개발자도구로는 보인다. 볼 권한이 있는 사람(관리자)에게
       실수로 보이는 것을 줄이는 것이 목적이다.

    프롭:
      value   원문 전화번호(숫자만 또는 형식 포함). 없으면 '-' 를 그린다
      reveal  「보기」 토글 제공 여부. 목록 화면은 false(고정 마스킹), 상세는 true
      tel     tel: 링크로 감쌀지. 가린 상태에서도 «전화는 걸린다» — 구조 현장에서
              번호를 눈으로 읽을 필요는 없고 거는 것이 필요하다
--}}
@props([
    'value' => null,
    'reveal' => false,
    'tel' => false,
])

@php
    $digits = preg_replace('/[^0-9]/', '', (string) $value);

    // 010-****-5678 — 앞 3자리와 뒤 4자리만 남긴다. 뒤 4자리는 현장에서 사람을
    // 구분하는 최소 단서라 남기고, 가운데를 가린다.
    $masked = strlen($digits) >= 7
        ? substr($digits, 0, 3).'-****-'.substr($digits, -4)
        : ($digits !== '' ? str_repeat('*', strlen($digits)) : null);

    $full = strlen($digits) === 11 && str_starts_with($digits, '010')
        ? substr($digits, 0, 3).'-'.substr($digits, 3, 4).'-'.substr($digits, 7, 4)
        : ($value ?: null);
@endphp

@if (! $masked)
    <span {{ $attributes->merge(['class' => 'tabular-nums text-slate-400']) }}>-</span>
@elseif ($reveal)
    <span x-data="{ shown: false }" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
        @if ($tel)
            <a href="tel:{{ $digits }}" class="tabular-nums text-blue-600 hover:text-blue-700"
               x-text="shown ? @js($full) : @js($masked)">{{ $masked }}</a>
        @else
            <span class="tabular-nums" x-text="shown ? @js($full) : @js($masked)">{{ $masked }}</span>
        @endif
        <button type="button" @click="shown = !shown"
                class="text-xs font-medium text-slate-400 hover:text-slate-600"
                x-text="shown ? '가리기' : '보기'">보기</button>
    </span>
@elseif ($tel)
    <a href="tel:{{ $digits }}" {{ $attributes->merge(['class' => 'tabular-nums text-blue-600 hover:text-blue-700']) }}>{{ $masked }}</a>
@else
    <span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $masked }}</span>
@endif
