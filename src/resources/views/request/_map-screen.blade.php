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

    // 🔴 「이 신고가 어디로 가는가」를 «항상» 보여준다.
    //
    //    2026-08-13 에 행사 참가자의 신고가 「상시 운영」으로 새고 있었는데, 아무도
    //    몰랐던 이유가 정확히 이것이다 — 화면에 목적지가 안 적혀 있었다. 링크 하나가
    //    잘못돼도 티가 안 나서, 관제 화면이 빈 걸 보고서야 알았다.
    //    이 한 줄이 그 종류의 버그를 테스트가 아니라 «화면»으로 막는다.
    //
    //    $project 는 slug 로 들어온 경우(현수막 QR)이고, 없으면 RequestService 가
    //    「지금 있는 행사」로 붙인다 — 그 판정과 같은 것을 여기서도 읽는다.
    //    🔴 구급대인 행사는 대상·선택지에서 뺀다. 어차피 그 행사에는 신고할 수 없으므로
    //       기본값으로 제시하면 화면에 들어가자마자 「신고 불가」에 걸린다.
    $requester = auth()->user();
    $reportable = $requester ? $requester->reportableEvents() : collect();
    $targetEvent = $project ?: $reportable->first();
    $switchable = $reportable->reject(fn ($p) => $targetEvent && $p->id === $targetEvent->id)->values();

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
             class="absolute inset-x-0 z-20 transition-transform duration-300 ease-out"
             {{-- 접힘 높이 = 그랩 핸들 행(h-11=44px) + 시트 border-t(1px) = 45px.
                  이 값이 어긋나면 아래 내용 한 줄이 반쯤 걸쳐 잘린다 --}}
             :class="sheetExpanded ? 'bottom-0 translate-y-0'
                              : 'bottom-[var(--safe-bottom)] translate-y-[calc(100%_-_2.8125rem)]'">

            {{-- 현재 위치 재조회 — 시트를 따라 움직인다 --}}
            <location-button :loading="loading" v-on:get-location="getLocation"></location-button>

            {{-- 높이 단위는 컨테이너(h-[100dvh])와 맞춰 dvh 로. vh 로 두면 모바일
                 주소창이 보일 때 70vh 가 실제 가용 높이를 넘어선다 --}}
            <div class="max-h-[70dvh] overflow-y-auto rounded-t-3xl border-t border-ink-200 bg-white pb-[var(--safe-bottom)] shadow-[0_-8px_24px_-8px_rgba(0,0,0,0.15)]">
                {{-- 그랩 핸들 (탭하면 시트 접기/펼치기) --}}
                <div class="flex h-11 cursor-pointer items-center justify-center" v-on:click="toggleSheet"
                     role="button" aria-label="패널 펼치기/접기">
                    <span class="h-1.5 w-12 rounded-full bg-ink-200"></span>
                </div>

                {{-- 행사 «설명»은 신고 결정과 무관해서 뺐다. 행사 이름은 헤더에 있고,
                     어디로 접수되는지는 버튼 바로 위에 있다. 시트에서 읽어야 하는 것은
                     「여기가 맞나 / 무슨 상황인가」 둘뿐이다. --}}

                <location-info :latitude="lat" :longitude="long" :address="address"
                               title="현재 발송 위치" action-label="주소 검색"
                               v-on:action="execDaumPostcode"></location-info>

                {{-- 주소 직접 검색 (Daum 우편번호) --}}
                {{-- 주소 검색은 «예외 경로»다 — GPS 가 맞으면 쓰지 않는다.
                     전체 폭 박스로 두면 화면에서 가장 큰 요소 중 하나가 되어 비중이 틀린다.
                     라벨 줄의 「주소 검색」 버튼(location-info)으로 옮겼다. --}}

                {{-- 접수 대상 안내 — 상황 버튼 «바로 위». 결정 직전에 보여야 의미가 있다.
                     처음엔 헤더 아래에 뒀는데, 지도만 밀어내고 헤더의 행사명과 중복이었다.

                     행사가 하나뿐이어도 «항상» 띄운다. 2026-08-13 에 행사 신고가
                     「상시 운영」으로 새고 있었는데 아무도 몰랐던 이유가 화면에 목적지가
                     없었기 때문이다 — 이 한 줄이 그 종류의 버그를 «화면»으로 막는다.

                     🔑 프레임워크를 쓰지 않는다(순수 <details>). 이 블록은 Vue 마운트 루트
                        (#app) 안이고 사용자 화면에는 Alpine 이 없다(관리자 셸 전용).
                        x-data/x-show 로 짰더니 목록이 «항상 펼쳐진 채»로 렌더됐다. --}}
                @php
                    $bannerText = $targetEvent
                        ? '<b class="font-bold text-ink-950">'.e($targetEvent->name).'</b> 으로 접수됩니다'
                        : '행사와 무관한 <b class="font-bold text-ink-950">일반 신고</b>로 접수됩니다';
                @endphp

                <div class="px-5 pb-2 pt-1">
                    @if ($switchable->isEmpty())
                        <p class="flex items-center gap-1.5 text-sm text-ink-500">
                            <x-ui.icon name="pin" class="h-3.5 w-3.5 shrink-0 text-ink-300" />
                            {!! $bannerText !!}
                        </p>
                    @else
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-1.5 text-sm text-ink-500 [&::-webkit-details-marker]:hidden">
                                <x-ui.icon name="pin" class="h-3.5 w-3.5 shrink-0 text-ink-300" />
                                <span class="min-w-0 flex-1 truncate">{!! $bannerText !!}</span>
                                <span class="shrink-0 font-bold text-brand-600 group-open:hidden">변경</span>
                                <span class="hidden shrink-0 font-bold text-ink-400 group-open:inline">닫기</span>
                            </summary>

                            {{-- 참가 중인 행사만. 선택지가 늘수록 급할 때 잘못 고른다. --}}
                            <div class="mt-2 overflow-hidden rounded-xl border border-ink-200">
                                @foreach ($switchable as $alt)
                                    <a href="{{ route('request.create.project', $alt->slug) }}"
                                       class="flex items-center gap-1.5 border-b border-ink-100 px-3.5 py-2.5 text-sm text-ink-700 last:border-0 active:bg-ink-50">
                                        <x-ui.icon name="pin" class="h-3.5 w-3.5 shrink-0 text-ink-300" />
                                        {{ $alt->name }} 으로 접수
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>

                {{-- 상황 버튼 2x2 — 2단계 구분은 RequestType::actionTone() 이 결정 --}}
                <div class="grid grid-cols-2 gap-2.5 px-5 pb-6 pt-1">
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
