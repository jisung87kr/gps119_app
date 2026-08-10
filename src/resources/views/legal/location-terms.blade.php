{{--
    위치기반서비스 이용약관.

    🔴 이 문서는 초안이고 법률 검토를 받지 않았다. 공란(<법무 확인 필요>)을 채우기 전에는 시행할 수 없다.

    🔴 특히 «위치기반서비스사업 신고번호»는 비워 둘 수 없는 항목이다.
       신고 없이 서비스를 제공하는 것 자체가 위법이므로, 신고 완료 전에는 이 약관을 게시해도
       요건을 갖춘 것이 아니다. (모바일 에픽 N0 블로커 — DEPLOY.md §5)

    제8조의 «이용·제공사실 확인자료»는 현재 시스템에 «자동 기록 기능이 없다».
    약관에만 적고 구현하지 않으면 약관과 실제가 어긋난다 — 법무 확인과 함께 구현 여부를 정해야 한다.
--}}
<x-layouts.app title="GPS119 - 위치기반서비스 이용약관" heading="위치기반서비스 이용약관" :back="url()->previous()">
    <article class="space-y-7 pb-10 text-[15px] leading-relaxed text-ink-700">

        <p class="rounded-2xl bg-ink-50 p-4 text-sm text-ink-600">
            이 약관은 세이브미(이하 &lsquo;회사&rsquo;)가 제공하는 GPS119 위치기반서비스의 이용 조건을 정합니다.
            GPS119 는 <strong class="text-ink-900">위급 상황에서 구조를 요청하고 구조대가 그 위치를 찾아가도록</strong>
            돕는 서비스이며, 국가 119 신고를 대체하지 않습니다.
        </p>

        <div class="rounded-2xl border-2 border-danger-200 bg-danger-50 p-4 text-sm">
            <p class="font-extrabold text-danger-700">긴급 상황에서는 먼저 119에 신고하세요.</p>
            <p class="mt-1 text-danger-700">
                GPS119 는 행사 주최 측이 운영하는 <strong>보조 수단</strong>입니다. 국가 구급 체계를 대신하지 않습니다.
            </p>
        </div>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제1조 (목적)</h2>
            <p>
                이 약관은 회사가 제공하는 위치기반서비스에 대하여 회사와 개인위치정보주체 간의
                권리·의무 및 책임사항을 규정함을 목적으로 합니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제2조 (서비스의 내용)</h2>
            <p>회사는 아래의 위치기반서비스를 제공합니다.</p>
            <ul class="list-disc space-y-1 pl-5">
                <li><strong class="text-ink-900">구조요청 위치 전송</strong> — 이용자가 구조를 요청할 때의 위치를 구조대원·관제요원에게 전달</li>
                <li><strong class="text-ink-900">실시간 위치 공유</strong> — 행사 참여 중 이용자가 공유를 켠 동안 위치를 관제 화면에 표시</li>
                <li><strong class="text-ink-900">출동 안내</strong> — 배정된 구조대원에게 요청 위치까지의 경로 제공</li>
                <li><strong class="text-ink-900">행사 기록</strong> — 행사 종료 후 요청·출동·이동 기록의 정리</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제3조 (이용요금)</h2>
            <p>
                회사가 제공하는 위치기반서비스는 무료입니다. 다만 서비스 이용에 따르는
                이동통신 데이터 요금은 이용자가 가입한 통신사의 정책에 따릅니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제4조 (개인위치정보의 이용·제공)</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>회사는 개인위치정보를 이용하여 서비스를 제공하고자 하는 경우 미리 이 약관에 명시한 후 동의를 받습니다.</li>
                <li>
                    회사는 개인위치정보를 <strong class="text-ink-900">구조 목적</strong>으로만 이용하며,
                    구조요청이 접수된 경우 해당 행사의 관제요원 및 배정된 구조대원에게 제공합니다.
                </li>
                <li>
                    제3자에게 제공하는 경우, 제공받는 자와 제공 목적을 개인위치정보주체에게
                    <strong class="text-ink-900">즉시 통보</strong>합니다.
                </li>
                <li>개인위치정보주체는 제3자 제공에 대한 동의를 유보하거나 철회할 수 있습니다.</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제5조 (개인위치정보주체의 권리)</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>동의의 전부 또는 일부를 <strong class="text-ink-900">언제든지 철회</strong>할 수 있습니다. 서비스 화면에서 위치 공유를 끄면 즉시 수집이 중단됩니다.</li>
                <li>개인위치정보의 수집·이용·제공의 <strong class="text-ink-900">일시적인 중지</strong>를 요구할 수 있습니다.</li>
                <li>
                    아래 자료에 대한 <strong class="text-ink-900">열람 또는 고지</strong>를 요구할 수 있으며,
                    오류가 있으면 정정을 요구할 수 있습니다.
                    <ul class="mt-1 list-[circle] space-y-1 pl-5">
                        <li>본인에 대한 위치정보 수집·이용·제공사실 확인자료</li>
                        <li>본인의 개인위치정보가 법령에 따라 제3자에게 제공된 이유 및 내용</li>
                    </ul>
                </li>
                <li>동의를 철회한 경우 회사는 수집한 개인위치정보 및 확인자료를 <strong class="text-ink-900">지체 없이 파기</strong>합니다. 다만 법령이 보존을 정한 경우는 그에 따릅니다.</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제6조 (법정대리인의 권리)</h2>
            <p>
                회사는 14세 미만 아동의 개인위치정보를 이용하거나 제3자에게 제공하려는 경우
                아동과 그 법정대리인의 동의를 받습니다. 법정대리인은 제5조의 권리를 모두 행사할 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제7조 (8세 이하 아동 등의 보호의무자의 권리)</h2>
            <p>
                8세 이하의 아동, 피성년후견인, 장애인복지법상 정신적 장애를 가진 장애인에 해당하는 사람의
                생명·신체 보호를 위하여 보호의무자가 동의하는 경우, 본인의 동의가 있는 것으로 봅니다.
                보호의무자는 서면으로 동의 및 철회를 할 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제8조 (이용·제공사실 확인자료의 보유)</h2>
            <p>
                회사는 「위치정보의 보호 및 이용 등에 관한 법률」에 따라 위치정보 이용·제공사실 확인자료를
                자동으로 기록·보존하며, 법령이 정한 기간 동안 보관합니다.
            </p>
            <p class="text-sm text-ink-500">
                보관 기간: <span class="rounded bg-warning-50 px-1 font-bold text-warning-700">&lt;법무 확인 필요&gt;</span>
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제9조 (서비스의 변경·중지)</h2>
            <p>
                회사는 설비 점검, 통신 장애, 천재지변 등의 사유로 서비스를 일시 중지할 수 있으며,
                이 경우 사전에 공지합니다. 다만 긴급한 사유가 있는 경우 사후에 공지할 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제10조 (손해배상 및 면책)</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>회사의 고의 또는 과실로 이용자에게 손해가 발생한 경우 관련 법령에 따라 배상합니다.</li>
                <li>
                    <strong class="text-ink-900">
                        단말기의 GPS 성능, 통신 상태, 실내·지하 등 전파 환경에 따라 위치가 부정확할 수 있습니다.
                    </strong>
                    이는 서비스의 기술적 한계이며, 회사는 위치의 절대적 정확성을 보장하지 않습니다.
                </li>
                <li>이용자가 위치 공유를 끈 상태이거나 단말기 전원이 꺼진 경우 위치를 확인할 수 없습니다.</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제11조 (분쟁의 조정)</h2>
            <p>
                위치정보와 관련한 분쟁은 방송통신위원회에 재정을 신청하거나,
                개인정보 분쟁조정위원회에 조정을 신청할 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">제12조 (사업자 정보 및 위치정보관리책임자)</h2>
            <div class="rounded-2xl border border-ink-100 p-4 text-sm">
                <p>상호: 세이브미</p>
                <p class="mt-1">사업자등록번호: 852-08-02915</p>
                <p class="mt-1">주소: <span class="rounded bg-warning-50 px-1 font-bold text-warning-700">&lt;법무 확인 필요&gt;</span></p>
                <p class="mt-1">대표자: <span class="rounded bg-warning-50 px-1 font-bold text-warning-700">&lt;법무 확인 필요&gt;</span></p>
                <p class="mt-1">
                    위치기반서비스사업 신고번호:
                    <span class="rounded bg-danger-50 px-1 font-bold text-danger-700">&lt;신고 완료 후 기재 — 필수&gt;</span>
                </p>
                <p class="mt-1">위치정보관리책임자: <span class="rounded bg-warning-50 px-1 font-bold text-warning-700">&lt;법무 확인 필요&gt;</span></p>
                <p class="mt-1">연락처: <span class="rounded bg-warning-50 px-1 font-bold text-warning-700">&lt;법무 확인 필요&gt;</span></p>
            </div>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">부칙</h2>
            <p class="text-sm font-bold text-ink-900">
                시행일: <span class="rounded bg-warning-50 px-1 text-warning-700">&lt;법무 확인 필요&gt;</span>
            </p>
        </section>

        <div class="border-t border-ink-100 pt-5">
            <a href="{{ route('legal.privacy') }}"
               class="font-bold text-brand-600 underline underline-offset-2">개인정보처리방침 보기</a>
        </div>
    </article>
</x-layouts.app>
