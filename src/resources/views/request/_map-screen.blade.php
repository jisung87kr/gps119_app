{{--
    신고 화면 공용 마크업 — src/tmp/dispatch.html(전체화면 지도 + 바텀시트) 기준.
    request/create(상시)와 request/create-project(행사 QR)가 함께 쓴다.
    Vue 마운트 루트(#app) 이므로 _confirm-modal 도 이 안에 include 해야 한다.

    변수: $project (없으면 상시 신고)

    탭 바를 두지 않는다 — 긴급 상황 화면은 방해 요소를 없애고 뒤로가기만 남긴다
    (레퍼런스 동일). 진입은 홈의 "긴급 구조 요청" CTA 와 하단 탭 "구조요청".
--}}
@php
    use App\Enums\RequestType;

    $heading = $project ? $project->name : '구조 요청';

    // 상황 버튼별 Vue 핸들러. 긴급전화만 즉시 통화, 나머지는 주소확인 모달을 띄운다.
    $handlers = collect(RequestType::cases())
        ->mapWithKeys(fn (RequestType $t) => [
            $t->value => $t === RequestType::EMERGENCY
                ? 'emergencyCall'
                : "openConfirm('{$t->value}')",
        ]);
@endphp

{{-- 다른 사용자 화면과 같은 폭(max-w-2xl 중앙 정렬) — 레퍼런스 tmp/dispatch.html 동일 --}}
<div id="app" class="mx-auto flex h-[100dvh] max-w-2xl flex-col overflow-hidden bg-ink-50">
    <map-loader v-on:scripts-loaded="initMap"></map-loader>
    <intro-screen :show="showIntro" title="응급상황 위치공유 서비스"></intro-screen>

    <x-ui.page-header :heading="$heading" :back="route('dashboard')" class="shrink-0" />

    {{-- 지도가 남은 영역을 채우고 바텀시트가 그 위에 뜬다 (접으면 지도가 드러남) --}}
    <div class="relative flex-1">
        <map-container ref="mapContainer"></map-container>

        {{--
            시트는 2겹이다. 바깥은 위치 재조회 버튼이 시트 위로 떠야 해서 overflow 를
            두지 않고, 스크롤은 안쪽 박스가 담당한다. (한 겹으로 만들면 overflow-y-auto 가
            top 이 음수인 버튼을 잘라먹는다)
        --}}
        <div id="bottom-sheet"
             class="absolute inset-x-0 bottom-0 z-20 transition-transform duration-300 ease-out"
             {{-- 접힘 높이 = 그랩 핸들 행(h-11=44px) + 시트 border-t(1px) = 45px.
                  이 값이 어긋나면 아래 내용 한 줄이 반쯤 걸쳐 잘린다 --}}
             :class="sheetExpanded ? 'translate-y-0' : 'translate-y-[calc(100%_-_2.8125rem)]'">

            {{-- 현재 위치 재조회 — 시트를 따라 움직인다 --}}
            <location-button :loading="loading" v-on:get-location="getLocation"></location-button>

            {{-- 높이 단위는 컨테이너(h-[100dvh])와 맞춰 dvh 로. vh 로 두면 모바일
                 주소창이 보일 때 70vh 가 실제 가용 높이를 넘어선다 --}}
            <div class="max-h-[70dvh] overflow-y-auto rounded-t-3xl border-t border-ink-200 bg-white shadow-[0_-8px_24px_-8px_rgba(0,0,0,0.15)]">
                {{-- 그랩 핸들 (탭하면 시트 접기/펼치기) --}}
                <div class="flex h-11 cursor-pointer items-center justify-center" v-on:click="toggleSheet"
                     role="button" aria-label="패널 펼치기/접기">
                    <span class="h-1.5 w-12 rounded-full bg-ink-200"></span>
                </div>

                @if ($project)
                    <div class="px-5 pb-1">
                        <p class="text-sm leading-relaxed text-ink-500">
                            {{ $project->description ?? '위치를 공유하여 구조를 요청하세요.' }}
                        </p>
                    </div>
                @endif

                <location-info :latitude="lat" :longitude="long" :address="address"
                               title="현재 발송 위치"></location-info>

                {{-- 주소 직접 검색 (Daum 우편번호) --}}
                <div class="px-5 py-3">
                    <input type="text" id="address" name="address" readonly
                           placeholder="다른 위치로 검색하려면 탭하세요"
                           class="w-full rounded-2xl border-2 border-ink-200 bg-ink-50 px-4 py-3.5 text-base text-ink-950 placeholder:text-ink-400 focus:border-brand-600 focus:bg-white focus:outline-none"
                           v-model="address" v-on:click="execDaumPostcode">
                </div>

                {{-- 상황 버튼 2x2 — 2단계 구분은 RequestType::actionTone() 이 결정 --}}
                <div class="grid grid-cols-2 gap-2.5 px-5 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-1">
                    @foreach (RequestType::cases() as $type)
                        <x-ui.action-button
                            :tone="$type->actionTone()"
                            :icon="$type->icon()"
                            :vue-click="$handlers[$type->value]">
                            {{ $type->actionLabel() }}
                        </x-ui.action-button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 주소 검색 오버레이 (Daum 위젯 마운트 지점) — 본문도 같은 폭으로 --}}
    <div class="fixed inset-0 z-[100] bg-ink-950/40" v-show="findAddress">
        <div class="mx-auto flex h-full max-w-2xl flex-col overflow-auto bg-white">
            <header class="sticky top-0 flex h-16 shrink-0 items-center justify-between border-b border-ink-200 bg-white px-4">
                <span class="text-lg font-extrabold text-ink-950">주소 검색</span>
                <button type="button" v-on:click="findAddress=false" aria-label="닫기"
                        class="flex h-11 w-11 items-center justify-center rounded-full text-ink-900 active:bg-ink-100">
                    <x-ui.icon name="x-circle" class="h-6 w-6" />
                </button>
            </header>
            <div ref="search_address_element" class="w-full p-4"></div>
        </div>
    </div>

    @include('request._confirm-modal')
</div>
