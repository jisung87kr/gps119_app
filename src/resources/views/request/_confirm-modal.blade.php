{{-- FE-3.1 주소확인 모달 + 접수완료 (반드시 #app 안에서 include — Vue 마운트 루트 내부) --}}

{{-- "이 위치가 맞습니까?" 모달 --}}
<div v-if="confirmOpen" class="fixed inset-0 z-[120] flex items-end sm:items-center justify-center bg-black/40" @click.self="closeConfirm">
    <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-bold text-slate-900">이 위치가 맞습니까?</h3>
            <button @click="closeConfirm" class="text-slate-400 hover:text-slate-600" aria-label="닫기">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- 지도 미리보기(정적 지도) --}}
        <div id="confirm-map" class="w-full h-40 rounded-xl bg-slate-100 mb-3 overflow-hidden"></div>

        {{-- 주소 텍스트(핵심: 주소 표시) --}}
        <p class="text-lg font-bold text-slate-900 break-keep">📍 @{{ address || '주소를 확인하지 못했습니다 (좌표로 신고됩니다)' }}</p>
        <p class="text-xs text-slate-400 mt-1">위도 @{{ Number(lat).toFixed(5) }}, 경도 @{{ Number(long).toFixed(5) }}</p>

        <div class="flex gap-2 mt-3">
            <button @click="execDaumPostcode" class="flex-1 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">주소 다시 검색</button>
            <button @click="correctOnMap" class="flex-1 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">지도에서 보정</button>
        </div>

        <p v-if="submitError" class="text-sm text-rose-600 mt-3">@{{ submitError }}</p>

        <div class="flex gap-2 mt-4">
            <button @click="closeConfirm" class="flex-1 py-3 text-base font-medium rounded-xl border border-slate-300 text-slate-700">취소</button>
            <button @click="confirmSubmit" :disabled="submitting"
                    class="flex-1 py-3 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                @{{ submitting ? '전송 중…' : (typeLabel(requestType) + ' 신고') }}
            </button>
        </div>
    </div>
</div>

{{-- 접수 완료 --}}
<div v-if="successOpen" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 px-4">
    <div class="bg-white w-full max-w-sm rounded-2xl p-6 text-center">
        <div class="mx-auto h-14 w-14 bg-emerald-100 rounded-full flex items-center justify-center mb-4">
            <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-lg font-bold text-slate-900">신고가 접수되었습니다</h3>
        <p class="text-sm text-slate-500 mt-1">구조대가 위치를 확인하고 있습니다.</p>
        <div class="mt-5 space-y-2">
            <a v-if="createdRequestId" :href="`/requests/${createdRequestId}`"
               class="block w-full py-3 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700">내 신고 상태 보기</a>
            <a :href="'tel:'+emergencyTel" class="block w-full py-2.5 text-sm font-medium rounded-xl border border-slate-300 text-slate-700">상황실 전화</a>
        </div>
    </div>
</div>
