{{--
    개인정보처리방침.

    🔴 «코드에서 확인한 실제 수집 항목»으로 작성했고, 법률 검토는 «아직 받지 않았다».
       실제 처리와 어긋난 방침은 그 자체로 위반이므로, 수집 항목·위탁처가 바뀌면 여기도 같이 고친다.

    ⚠️ 보유기간(구조요청 3년 / 위치 이력 6개월 / 확인자료 6개월 / 접속기록 3개월)은 «관례값»이고
       법률 검토를 받지 않았다. 바뀌면 config/location.php 의 retention_days 도 같이 바꿔야 한다.

    ✅ 2026-08-12: 위치 이력 자동파기가 «실제로 돈다» (`location:purge`, 매일 04:40).
       config('location.retention_days') = 180 이고 여기 적힌 6개월과 같은 값이다.
       LocationRetentionTest 가 둘이 어긋나면 실패한다 — 방침과 시스템이 갈라지지 않게 하는 장치다.
       ⚠️ 단, 서버에 `schedule:run` 크론이 걸려 있어야 실제로 돈다(DEPLOY.md §4.2).

    근거가 되는 코드:
      users / requests / location_pings / event_participants / device_tokens / sessions 마이그레이션
      config/services.php (네이버), config/push.php (FCM·VAPID), ADR-0004(연락처 채널 범위)
--}}
<x-layouts.app title="GPS119 - 개인정보처리방침" heading="개인정보처리방침" :back="url()->previous()">
    <article class="space-y-7 pb-10 text-[15px] leading-relaxed text-ink-700">

        <p class="rounded-2xl bg-ink-50 p-4 text-sm text-ink-600">
            세이브미(이하 &lsquo;회사&rsquo;)는 GPS119 서비스를 제공하면서 이용자의 개인정보를 소중히 다룹니다.
            이 방침은 회사가 <strong class="text-ink-900">어떤 정보를, 왜, 얼마나</strong> 처리하는지를 설명합니다.
        </p>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">1. 수집하는 개인정보 항목</h2>
            <p>회사는 아래 항목을 수집합니다.</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-ink-200 text-left text-ink-500">
                            <th class="py-2 pr-3 font-bold">구분</th>
                            <th class="py-2 pr-3 font-bold">항목</th>
                            <th class="py-2 font-bold">수집 시점</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">회원</td>
                            <td class="py-2 pr-3">이름, 휴대전화번호, 비밀번호, 이메일(관리자 계정)</td>
                            <td class="py-2">회원가입 시</td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">간편 로그인</td>
                            <td class="py-2 pr-3">네이버가 제공하는 회원 식별자, 이름, 이메일</td>
                            <td class="py-2">네이버 로그인 이용 시</td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">구조요청</td>
                            <td class="py-2 pr-3">요청 시점의 위치(위도·경도), 주소, 상황 유형, 연락 받을 전화번호, 상황 설명</td>
                            <td class="py-2">구조요청 작성 시</td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">행사 참여</td>
                            <td class="py-2 pr-3">참여 역할, 위치 공유 여부, 최근 위치, 최종 접속 시각</td>
                            <td class="py-2">행사 참여 시</td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">실시간 위치</td>
                            <td class="py-2 pr-3">위도·경도, 위치 정확도, 이동 방향, 이동 속도, 측정 시각</td>
                            <td class="py-2">이용자가 <strong class="text-ink-900">위치 공유를 켠 동안</strong></td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">알림</td>
                            <td class="py-2 pr-3">기기 알림 토큰, 플랫폼(iOS/Android/웹), 앱 버전</td>
                            <td class="py-2">알림 수신에 동의한 경우</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-3 font-bold text-ink-900">자동 생성</td>
                            <td class="py-2 pr-3">접속 IP 주소, 브라우저·기기 정보(User-Agent), 접속 기록</td>
                            <td class="py-2">서비스 이용 시</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-ink-500">
                위치 공유는 이용자가 직접 켜고 끌 수 있으며, <strong class="text-ink-900">꺼 두면 위치가 기록되지 않습니다.</strong>
                다만 구조요청을 보내는 시점의 위치는 요청 자체의 핵심 정보이므로 함께 저장됩니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">2. 이용 목적</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>구조요청 접수 및 구조대원·관제요원에게 전달</li>
                <li>구조대원이 현장을 찾아가기 위한 경로 안내</li>
                <li>행사 진행 중 참여자 위치의 실시간 관제 및 출동 지령</li>
                <li>구조요청 상태 변경 및 출동 알림 발송</li>
                <li>행사 종료 후 기록 정리 및 통계</li>
                <li>본인 확인, 부정 이용 방지, 서비스 운영·개선</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">3. 제3자 제공</h2>
            <p>
                회사는 이용자의 개인정보를 외부에 판매하거나 마케팅 목적으로 제공하지 않습니다.
                다만 <strong class="text-ink-900">구조라는 서비스의 성격상</strong> 아래의 경우 정보가 표시됩니다.
            </p>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    구조요청이 접수되면 해당 행사의 <strong class="text-ink-900">관제요원과 배정된 구조대원</strong>에게
                    요청자의 위치와 연락처가 표시됩니다. 구조대원이 직접 전화를 걸어야 하기 때문입니다.
                </li>
                <li>법령에 따라 수사기관 등이 적법한 절차로 요구하는 경우</li>
                <li>급박한 생명·신체의 위험으로부터 보호하기 위하여 필요한 경우</li>
            </ul>
            <p class="text-sm text-ink-500">
                푸시 알림 문구에는 전화번호를 넣지 않습니다. 잠금화면에 노출되고 외부 발송 서버를 거치기 때문입니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">4. 처리 위탁 및 국외 이전</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-ink-200 text-left text-ink-500">
                            <th class="py-2 pr-3 font-bold">수탁자</th>
                            <th class="py-2 pr-3 font-bold">업무</th>
                            <th class="py-2 font-bold">보관 국가</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">Amazon Web Services</td>
                            <td class="py-2 pr-3">서버 운영·데이터 보관</td>
                            <td class="py-2">대한민국(서울 리전)</td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">Google LLC (Firebase)</td>
                            <td class="py-2 pr-3">앱 푸시 알림 발송</td>
                            <td class="py-2"><strong class="text-ink-900">미국</strong></td>
                        </tr>
                        <tr class="border-b border-ink-100">
                            <td class="py-2 pr-3 font-bold text-ink-900">카카오</td>
                            <td class="py-2 pr-3">지도 표시 및 주소 변환</td>
                            <td class="py-2">대한민국</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-3 font-bold text-ink-900">네이버</td>
                            <td class="py-2 pr-3">간편 로그인 인증</td>
                            <td class="py-2">대한민국</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-ink-500">
                앱 푸시 발송을 위해 기기 알림 토큰과 알림 문구가 Google 서버(미국)로 전송됩니다.
                이 과정에서 <strong class="text-ink-900">전화번호는 전송되지 않습니다.</strong>
                이용자는 알림 수신을 거부할 수 있으며, 거부하더라도 구조요청 기능은 그대로 이용할 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">5. 보유 및 파기</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>회원 정보: 회원 탈퇴 시까지. 탈퇴 시 지체 없이 파기합니다.</li>
                <li>구조요청 기록(위치·연락처 포함): <strong class="text-ink-900">3년</strong>. 사고 경위 확인과 분쟁 대응에 필요한 기간입니다.</li>
                <li>실시간 위치 이력: <strong class="text-ink-900">6개월</strong>. 행사 종료 후에는 기록 정리 목적으로만 보관합니다.</li>
                <li>위치정보 이용·제공사실 확인자료: <strong class="text-ink-900">6개월</strong> (「위치정보의 보호 및 이용 등에 관한 법률」에 따른 최소 보관기간)</li>
                <li>접속 기록(IP·접속 시각): <strong class="text-ink-900">3개월</strong></li>
                <li>법령이 별도 보존을 정한 경우 그 기간</li>
            </ul>
            <p>보유기간이 지난 정보는 지체 없이 파기하며, 전자적 파일은 복구할 수 없는 방법으로 삭제합니다.</p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">6. 이용자의 권리</h2>
            <p>
                이용자는 언제든지 자신의 개인정보를 열람·정정·삭제하거나 처리 정지를 요구할 수 있습니다.
                서비스 내 <strong class="text-ink-900">마이페이지</strong>에서 정보 수정과 회원 탈퇴가 가능하며,
                위치 공유는 화면에서 즉시 끌 수 있습니다. 그 밖의 요청은 아래 연락처로 접수해 주세요.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">7. 안전성 확보 조치</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>모든 통신 구간 암호화(HTTPS)</li>
                <li>비밀번호는 복호화가 불가능한 방식으로 저장</li>
                <li>데이터베이스를 외부에 직접 노출하지 않음</li>
                <li>업무상 필요한 최소 인원으로 접근 권한 제한</li>
                <li>정기적인 데이터 백업</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">8. 개인정보 보호책임자</h2>
            <div class="rounded-2xl border border-ink-100 p-4 text-sm">
                <p>업체명: 세이브미 (사업자등록번호 852-08-02915)</p>
                <p class="mt-1">개인정보 보호책임자: 대표자</p>
                <p class="mt-1">연락처: <a href="mailto:gwamb119@gmail.com" class="text-brand-600 underline underline-offset-2">gwamb119@gmail.com</a></p>
            </div>
            <p class="text-sm text-ink-500">
                개인정보 처리와 관련한 문의·불만·피해구제는 위 연락처로 접수해 주시면
                지체 없이 답변해 드리겠습니다.
            </p>
            <p class="text-sm text-ink-500">
                개인정보 침해에 대한 신고·상담은 개인정보침해신고센터(privacy.kisa.or.kr, 국번없이 118),
                대검찰청 사이버수사과(1301), 경찰청 사이버수사국(182)에 문의하실 수 있습니다.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-extrabold text-ink-950">9. 방침의 변경</h2>
            <p>
                이 방침이 변경되는 경우 시행일 전에 서비스 화면을 통해 공지합니다.
                이용자에게 불리한 변경은 최소 30일 전에 알립니다.
            </p>
            <p class="pt-2 text-sm font-bold text-ink-900">시행일: 2026년 8월 10일</p>
        </section>

        <div class="border-t border-ink-100 pt-5">
            <a href="{{ route('legal.location-terms') }}"
               class="font-bold text-brand-600 underline underline-offset-2">위치기반서비스 이용약관 보기</a>
        </div>
    </article>
</x-layouts.app>
