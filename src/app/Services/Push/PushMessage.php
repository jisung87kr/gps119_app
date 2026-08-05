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
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly array $data = [],
        public readonly ?string $tag = null,
    ) {}

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

        return [
            'notification' => [
                'title' => $this->title,
                'body' => $this->body,
            ],
            'data' => $data,
        ];
    }
}
