<?php

namespace App\Services\Push;

/**
 * 푸시 1건의 내용. 전송 규격(FCM/웹푸시)에 독립적인 값 객체.
 *
 * 🔴 **연락처를 여기 담지 않는다 (ADR-0004).** 푸시 페이로드는 잠금화면에 뜨고,
 * 전송 사업자(Google·브라우저 벤더) 서버를 거친다. 신고자 전화번호는 인가된
 * 채널(control / 개인 dispatch / requester)로만 나간다. 푸시는 «무슨 일이 났으니
 * 앱을 열어라»까지만 말하고, 상세는 앱이 열려서 인가된 경로로 받아온다.
 */
final class PushMessage
{
    /**
     * @param  string  $title  알림 제목
     * @param  string  $body  알림 본문. 연락처·상세 주소를 넣지 않는다.
     * @param  string|null  $url  탭했을 때 열 앱/웹 경로 (딥링크)
     * @param  array<string, scalar>  $data  앱이 읽을 부가 데이터. 여기에도 연락처 금지.
     * @param  string|null  $tag  같은 tag 의 알림은 기기에서 «대체»된다(중복 알림 방지)
     * @param  int|null  $badge  앱 아이콘 뱃지 «숫자». null 이면 뱃지를 건드리지 않는다.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly array $data = [],
        public readonly ?string $tag = null,
        public readonly ?int $badge = null,
    ) {}

    /**
     * 뱃지 숫자만 바꾼 사본.
     *
     * 🔑 **뱃지는 «메시지 내용»이 아니라 «받는 사람의 상태»다.** 그래서 리스너가 만드는
     *    PushMessage 에는 들어 있지 않고, 발송 직전에 수신자별로 찍힌다
     *    (`PushService`). 같은 메시지가 여러 사람에게 나가도 숫자는 각자 다르다.
     *
     *    이 구조의 이점: **어떤 푸시가 나가든 뱃지가 함께 보정된다.** 뱃지 전용 발송이
     *    필요 없고, 볼 것이 없어진 사람에게는 0 이 가서 «저절로 지워진다».
     */
    public function withBadge(?int $badge): self
    {
        return new self($this->title, $this->body, $this->url, $this->data, $this->tag, $badge);
    }

    /**
     * 웹푸시 payload (서비스워커 push 이벤트에서 그대로 읽는다).
     *
     * @return array<string, mixed>
     */
    public function toWebPayload(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'data' => $this->data,
        ];
    }

    /**
     * FCM HTTP v1 message 본문의 notification/data 부분.
     *
     * data 값은 FCM 규격상 «문자열만» 허용된다 — 숫자를 그대로 넣으면
     * 400 이 떨어지므로 여기서 한 번에 캐스팅한다.
     *
     * @return array<string, mixed>
     */
    public function toFcmPayload(): array
    {
        $data = array_map(static fn ($v) => (string) $v, $this->data);

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        $payload = [
            'notification' => [
                'title' => $this->title,
                'body' => $this->body,
            ],
            'data' => $data,
        ];

        // 🔑 여기가 빠져 있어서 **앱 푸시에서는 tag 가 통째로 무시됐다.**
        //    toWebPayload() 에만 실려 있었으므로 웹은 «대체»되고 앱은 쌓였다 —
        //    NotifyRescuers 는 tag:'request-{id}', PushDispatchAssigned 는
        //    tag:'dispatch-{id}' 로 «최신 것만 남는다»를 전제하고 쓴다.
        //
        //    쌓이면 안드로이드가 3개 이상을 «그룹»으로 묶는데, 그룹 요약 줄의
        //    인텐트에는 메시지 extras 가 없다. 그걸 누르면 앱만 열리고 딥링크가
        //    사라진다 — 지저분함이 아니라 대원이 배정 화면 대신 대시보드에 떨어지는 문제다.
        //
        //    ⚠️ `push:test` 는 일부러 매번 «다른» tag 를 쓴다(반복 검증용). 그래서
        //       그 명령으로는 이 결함이 드러나지 않는다 — 아래 테스트로 고정한다.
        //
        //    안드로이드: 같은 tag 면 대체. iOS: apns-collapse-id 가 같은 역할을 한다.
        //    ⚠️ apns 는 «하위 키»로 대입한다. `$payload['apns'] = [...]` 로 통째 대입하면
        //       아래 sound/interruption-level·badge 블록이 이 줄보다 먼저 실행될 때
        //       조용히 덮어써진다 — 순서에 의존하는 구조를 남기지 않는다.
        if ($this->tag !== null) {
            $payload['android'] = ['notification' => ['tag' => $this->tag]];
            $payload['apns']['headers']['apns-collapse-id'] = $this->tag;
        }

        // 🔴 **iOS 가 «잠금화면에서 조용»했던 이유** (실기기 QA 2026-08-31).
        //    안드로이드는 채널을 IMPORTANCE_HIGH 로 만들어 heads-up 을 확보해 뒀는데
        //    (셸의 MainActivity), iOS 에서 그 역할을 하는 건 채널이 아니라 «이 페이로드»다.
        //    둘 다 비어 있어서 양 플랫폼이 다 된 것처럼 보였지만 iOS 는 반쪽이었다:
        //
        //      sound              없으면 무음으로 배달된다. 구조 지령이 소리 없이 온다.
        //      interruption-level 기본값 active 는 집중 모드(수면·방해금지)를 못 뚫고
        //                         알림 요약에 묶일 수 있다. time-sensitive 는 둘 다 넘는다.
        //
        //    🔴 앱에 `com.apple.developer.usernotifications.time-sensitive` entitlement 가
        //       없으면 iOS 는 이 값을 «조용히 무시»한다 — 오류가 나지 않으므로 「보냈는데
        //       왜 그대로지」로 돌아온다. 셸의 App.entitlements 와 한 쌍이고 따로 배포하면 안 된다.
        //
        //    critical(무음 스위치·집중 모드까지 무시)은 애플의 별도 승인이 필요해 쓰지 않는다.
        $payload['apns']['payload']['aps']['sound'] = 'default';
        $payload['apns']['payload']['aps']['interruption-level'] = 'time-sensitive';

        // 🔑 iOS 앱 아이콘 뱃지는 **APNs 페이로드의 `aps.badge` 로만** 정해진다.
        //    Capacitor 의 `presentationOptions: ['badge', …]` 는 「뱃지 갱신을 허용한다」는
        //    뜻이지 숫자를 만들어 주지 않는다 — 이걸 안 실어서 뱃지가 «아예» 안 떴다
        //    (실기기 QA 2026-08-09).
        //
        //    `0` 도 유효한 값이다(뱃지를 지운다). 그래서 null 검사여야 하고,
        //    `if ($this->badge)` 로 쓰면 «지우기»가 통째로 사라진다.
        //
        //    안드로이드는 여기 대응물이 없다 — 런처가 알림 «개수»로 점·숫자를 알아서
        //    붙인다. 그래서 이 값은 iOS 전용이다.
        if ($this->badge !== null) {
            $payload['apns']['payload']['aps']['badge'] = $this->badge;
        }

        return $payload;
    }
}
