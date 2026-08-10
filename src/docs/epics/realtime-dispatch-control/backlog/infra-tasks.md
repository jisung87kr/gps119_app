# OPS — 인프라/운영 태스크 백로그 (실시간 위치·지령 관제)

> Lane **OPS-** 의 실행 단위 분해. 1차 담당: **kang-mansu (DevOps)**.
> 상위 설계는 [04-realtime-architecture](../04-realtime-architecture.md), [09-roadmap](../09-roadmap.md), [ADR-0001](../../adr/0001-realtime-transport-laravel-reverb.md), [00-master-plan](./00-master-plan.md)를 따른다.
> **설계를 다시 열지 않는다.** 인프라 미결은 마지막 [OPEN] 절에 모은다.
>
> 마스터플랜 OPS lane(OPS-0.1 / OPS-0.2 / OPS-4.1 / OPS-4.2)을 실행 가능한 OPS-01~OPS-13으로 분해했다.
> 규모: **S** ≈ 1주 미만 · **M** ≈ 1~2주 · **L** ≈ 2주+.
> 전제: 이 저장소는 Docker 기반이며 **모든 artisan/composer/npm은 app 컨테이너(`gps119_app-app-1`) 안에서 실행**한다.
> 현재 베이스 이미지는 `php:8.2-apache` 단일 프로세스(`apache2-foreground`)다. supervisor·redis·reverb는 **아직 없으므로 신규 도입** 대상이다.

## 매핑 (마스터플랜 OPS lane → 세부 OPS 태스크)

| 마스터플랜 lane | 세부 태스크 | Phase |
|------------------|------------|-------|
| OPS-0.1 (Reverb·도커 WS·Apache 프록시) | OPS-01, OPS-02, OPS-03 | 0 |
| OPS-0.2 (큐 워커·supervisor·데몬 감시) | OPS-04, OPS-05, OPS-06 | 0 |
| (Phase 0 구성·시크릿) | OPS-07 | 0 |
| (Phase 0 배포 절차) | ~~OPS-08~~ ✅ | 0 |
| OPS-4.1 (Redis 스케일·보존정책) | OPS-09, OPS-10, OPS-11 | 4 |
| OPS-4.2 (하이브리드·부하/배터리 관측) | OPS-12 | 4 |
| (카카오 쿼터 관측 — R7) | OPS-13 | 2 |

---

## Phase 0 — 실시간 기반 (M0)

### OPS-01 — Reverb 도커 서비스 추가 (데몬 + 포트 노출)
- **설명**: `php artisan reverb:start` 데몬을 docker-compose에 **별도 서비스**로 추가한다. app(Apache) 컨테이너와 동일 이미지·동일 `src/` 바인드마운트를 쓰되 커맨드만 reverb로 오버라이드한다. 단일 프로세스 컨테이너 원칙(Apache는 Apache, Reverb는 Reverb)을 지켜 재기동·스케일을 독립시킨다. ADR-0001의 "단일 인스턴스로 시작" 전제를 유지한다.
- **명령·설정 스니펫**:
  ```bash
  # app 컨테이너 안에서 패키지 설치
  docker exec gps119_app-app-1 composer require laravel/reverb
  docker exec gps119_app-app-1 php artisan reverb:install   # config/reverb.php, broadcasting.php, .env 키 생성
  ```
  ```yaml
  # docker-compose.yml 에 services 로 추가
  reverb:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    ports:
      - "9055:8080"            # 호스트 9055 → 컨테이너 8080 (WS)
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_DATABASE=laravel
      - DB_USERNAME=laravel
      - DB_PASSWORD=password
    volumes:
      - ./src:/var/www/html
    networks:
      - gps-network
  ```
  > entrypoint.sh는 "app/ 있으면 Laravel 설치 스킵" → 기존 체크아웃을 건드리지 않으므로 reverb 서비스에 그대로 재사용 안전. `CMD`만 compose `command`로 덮어쓴다.
- **완료 검증**:
  ```bash
  docker compose up -d reverb
  docker compose logs reverb | grep -i "Starting server"      # 기동 로그
  docker exec gps119_app-app-1 php artisan reverb:restart      # 정상 재기동 확인
  # 호스트에서 WS 핸드셰이크(앱키는 .env REVERB_APP_KEY)
  curl -i "http://localhost:9055/app/<REVERB_APP_KEY>?protocol=7&client=js&version=8.0.0" | head -5
  ```
  201/426 또는 WS 업그레이드 응답이 돌아오면 OK.
- **의존**: 없음 (크리티컬 패스 머리). 후행: OPS-02, OPS-03, OPS-07, BE-0.1.
- **규모**: M
- **Phase**: 0 (마스터플랜 OPS-0.1)

### OPS-02 — Apache WebSocket 프록시 (`docker/apache/apache.conf`)
- **설명**: 클라이언트가 단일 오리진(9050/HTTPS)으로 붙고 `/app`·`/apps`(Reverb WS 경로)를 reverb 서비스로 역프록시하도록 Apache를 구성한다. 별도 포트(9055) 직노출은 개발용으로만 두고, 운영 경로는 Apache 종단 + WS 업그레이드 프록시로 통일한다. `mod_proxy`/`mod_proxy_wstunnel` 활성화가 선행이다.
- **명령·설정 스니펫**:
  ```dockerfile
  # docker/php/Dockerfile — rewrite 옆에 추가
  RUN a2enmod proxy proxy_http proxy_wstunnel rewrite headers
  ```
  ```apache
  # docker/apache/apache.conf — <VirtualHost *:80> 안에 추가
  ProxyPreserveHost On
  # Reverb WS 업그레이드 (pusher 프로토콜 경로)
  RewriteEngine On
  RewriteCond %{HTTP:Upgrade} =websocket [NC]
  RewriteRule /app/(.*) ws://reverb:8080/app/$1 [P,L]
  ProxyPass        /app  ws://reverb:8080/app
  ProxyPassReverse /app  ws://reverb:8080/app
  ProxyPass        /apps http://reverb:8080/apps   # Reverb HTTP API (서버측 broadcast publish)
  ProxyPassReverse /apps http://reverb:8080/apps
  ```
  > 클라이언트 Echo는 `wsHost=<앱도메인>`, `wsPort=443`(TLS)·`forceTLS=true`로 두고 Apache가 내부 reverb:8080으로 종단(OPS-07의 `.env`와 정합).
- **완료 검증**:
  ```bash
  docker exec gps119_app-app-1 apachectl -M | grep -E "proxy_wstunnel|proxy_http"   # 모듈 로드 확인
  # 브라우저 콘솔에서 Echo 연결 시 9055가 아닌 앱 도메인으로 WS connected 확인 (FE-0.1 PoC와 합동)
  ```
  관리자 화면에서 신고 토스트가 별도 포트 없이 뜨면 OK.
- **의존**: OPS-01. 합동 검증: FE-0.1(Echo PoC).
- **규모**: M
- **Phase**: 0 (마스터플랜 OPS-0.1)

### OPS-03 — TLS 종단 (`9051`) 정리 + WS over TLS
- **설명**: compose에 이미 `9051:443`이 매핑돼 있으나 apache.conf에는 `*:80` VHost만 있다. `*:443` VHost(인증서·`SSLEngine on`)를 추가하고 OPS-02의 WS 프록시 블록을 443 VHost에도 동일 적용해 `wss://` 를 종단한다. 개발은 self-signed, 운영은 실인증서(반입 경로는 OPEN). HTTP→HTTPS 리다이렉트 정책 포함.
- **명령·설정 스니펫**:
  ```dockerfile
  RUN a2enmod ssl
  ```
  ```apache
  <VirtualHost *:443>
      DocumentRoot /var/www/html/public
      SSLEngine on
      SSLCertificateFile    /etc/ssl/certs/gps119.crt
      SSLCertificateKeyFile /etc/ssl/private/gps119.key
      # OPS-02의 ProxyPass /app, /apps 블록 동일 반복 (wss 종단)
  </VirtualHost>
  ```
  ```yaml
  # compose: 인증서 마운트
  volumes:
    - ./docker/apache/certs:/etc/ssl/gps119:ro
  ```
- **완료 검증**:
  ```bash
  curl -kI https://localhost:9051/up        # 200
  # 브라우저에서 wss:// connected, mixed-content 경고 없음
  ```
- **의존**: OPS-02. (운영 인증서 발급/반입은 OPEN OI-A.)
- **규모**: S
- **Phase**: 0 (마스터플랜 OPS-0.1)

### OPS-04 — 큐 워커 상시화 (`queue:work`, QUEUE_CONNECTION=database)
- **설명**: 위치 ping 적재(SPEC-04 `recordPing`)와 브로드캐스트·`NotifyRescuers` 디스코드 부수효과를 비동기화하려면 `queue:work`가 상시 떠 있어야 한다. 현재 `QUEUE_CONNECTION=database` 전제이므로 `jobs`/`failed_jobs` 마이그레이션을 확인하고 워커 컨테이너(또는 supervisor 프로그램, OPS-05)를 둔다. 무한 메모리 누수 방지를 위해 `--max-time`/`--max-jobs` 재기동 옵션을 건다.
- **명령·설정 스니펫**:
  ```bash
  docker exec gps119_app-app-1 php artisan queue:table        # 없으면 생성
  docker exec gps119_app-app-1 php artisan migrate
  # 상시 가동 (supervisor가 감싸는 게 최종형 — OPS-05)
  php artisan queue:work database --queue=default --sleep=1 --tries=3 --max-time=3600
  ```
  > 코드 영향: `NotifyRescuers`의 `ShouldQueue` 주석 해제(09 "기존 코드 영향" / BE lane)는 BE 작업이나, **워커가 없으면 잡이 적체**되므로 OPS-04가 그 선행 인프라다.
- **완료 검증**:
  ```bash
  docker exec gps119_app-app-1 php artisan queue:work --once   # 1건 처리 확인
  docker exec gps119_app-app-1 php artisan queue:failed        # 실패 큐 비어있음
  # 신고 1건 생성 → jobs 테이블에 적재됐다 처리되어 비는지, 디스코드 도착 확인
  ```
- **의존**: OPS-01(브로드캐스트 드라이버 reverb 전환 후 의미). 후행: M2 전체(ping 큐 적재).
- **규모**: S
- **Phase**: 0 (마스터플랜 OPS-0.2)

### OPS-05 — Supervisor 도입 (Reverb·큐 워커 프로세스 관리·자동 재기동)
- **설명**: Reverb 데몬과 큐 워커는 죽으면 실시간이 끊긴다(R5 단일 장애점). 컨테이너 내 supervisor로 두 프로세스를 관리하고 `autorestart=true`로 크래시 시 자동 부활시킨다. 두 가지 선택지: (a) app 이미지에 supervisor 설치 후 reverb·worker 프로그램 등록, (b) compose `restart: unless-stopped` + 단일프로세스 컨테이너. 본 백로그는 **워커는 supervisor 프로그램, reverb 데몬은 별도 컨테이너+`restart` 정책** 하이브리드를 기본으로 한다(프로세스별 독립 재기동).
- **명령·설정 스니펫**:
  ```dockerfile
  RUN apt-get update && apt-get install -y supervisor
  COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/gps119.conf
  ```
  ```ini
  # docker/supervisor/supervisord.conf
  [program:queue-worker]
  command=php /var/www/html/artisan queue:work database --sleep=1 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  numprocs=2
  stopwaitsecs=30
  stdout_logfile=/var/www/html/storage/logs/worker.log
  ```
  ```yaml
  # reverb 서비스(OPS-01)에는 데몬 자동 복구
  reverb:
    restart: unless-stopped
  ```
- **완료 검증**:
  ```bash
  docker exec gps119_app-app-1 supervisorctl status            # queue-worker RUNNING
  docker exec gps119_app-app-1 pkill -f queue:work             # 강제 종료
  docker exec gps119_app-app-1 supervisorctl status            # 수 초 내 재기동 확인
  docker kill -s SIGKILL <reverb cid> && docker ps             # restart 정책으로 재기동
  ```
- **의존**: OPS-01, OPS-04.
- **규모**: M
- **Phase**: 0 (마스터플랜 OPS-0.2)

### OPS-06 — 헬스체크·데몬 감시·로그 (`/up` 외 Reverb/워커 감시)
- **설명**: 기존 Laravel `/up` 헬스체크는 Apache만 본다. Reverb 데몬·큐 워커의 생존을 별도로 감시해야 한다. (1) compose `healthcheck`로 각 서비스 liveness, (2) `php artisan pail` 기반 실시간 로그 관측 절차, (3) 데몬 다운/큐 적체 시 알림(디스코드 웹훅 재사용 — `DISCORD_WEBHOOK_URL` 이미 존재). 폴백(폴링)이 실제로 도는지까지가 R5 대응의 완결.
- **명령·설정 스니펫**:
  ```yaml
  reverb:
    healthcheck:
      test: ["CMD", "sh", "-c", "curl -sf http://localhost:8080/up || exit 1"]
      interval: 15s
      timeout: 3s
      retries: 3
  ```
  ```bash
  docker exec gps119_app-app-1 php artisan pail --filter=Reverb     # 실시간 로그
  # 워커 적체 감시(간이): jobs 테이블 적체 건수 임계 초과 시 디스코드 알림 스크립트(cron, OPS-11과 동일 스케줄러 사용)
  docker exec gps119_app-app-1 php artisan tinker --execute='echo DB::table("jobs")->count();'
  ```
- **완료 검증**:
  ```bash
  docker compose ps          # reverb (healthy)
  # reverb 강제 종료 후: 헬스체크 unhealthy 전환 + 디스코드 알림 도착 + 클라이언트 폴링 폴백 동작 관측
  ```
- **의존**: OPS-01, OPS-05. R5(Reverb 단일 장애점)·R2(큐 적체) 트리거 관측 수단.
- **규모**: M
- **Phase**: 0 (마스터플랜 OPS-0.2)

### OPS-07 — `.env` / 구성: BROADCAST·REVERB·Echo (개발/운영 분리)
- **설명**: `BROADCAST_CONNECTION`을 `log`(현재)에서 `reverb`로 전환하고 `REVERB_*` 키, 프론트 Vite Echo 환경변수(`VITE_REVERB_*`)를 정의한다. 개발(self-signed·localhost·평문 WS 허용)과 운영(앱 도메인·wss·실인증서)을 분리한다. 시크릿(앱키/시크릿)은 코드/문서에 평문 노출 금지(가드레일) — `.env`만, `.env.example`엔 빈 키.
- **명령·설정 스니펫**:
  ```dotenv
  # src/.env (운영)
  BROADCAST_CONNECTION=reverb
  QUEUE_CONNECTION=database
  REVERB_APP_ID=...
  REVERB_APP_KEY=...
  REVERB_APP_SECRET=...
  REVERB_HOST="api.gps119.example"   # 서버측 publish 대상(내부: reverb)
  REVERB_PORT=443
  REVERB_SCHEME=https
  # 프론트(Vite) — Echo 클라이언트
  VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
  VITE_REVERB_HOST="${REVERB_HOST}"
  VITE_REVERB_PORT=443
  VITE_REVERB_SCHEME=https
  ```
  개발용 `.env`: `REVERB_HOST=localhost`, `REVERB_PORT=9055`, `REVERB_SCHEME=http`.
- **완료 검증**:
  ```bash
  docker exec gps119_app-app-1 php artisan config:clear
  docker exec gps119_app-app-1 php artisan tinker --execute='echo config("broadcasting.default");'  # reverb
  git grep -nE "REVERB_APP_SECRET=.+" -- ':!*.example'   # 시크릿 평문 커밋 없음(빈 결과여야)
  ```
- **의존**: OPS-01. BE-0.1(`withBroadcasting`)·FE-0.1(Echo)의 구성 선행조건.
- **규모**: S
- **Phase**: 0

### OPS-08 — 배포 절차 (마이그레이션 + reverb/worker 재시작 포함)
- **설명**: 실시간 도입 후 배포는 단순 코드 pull로 끝나지 않는다. 마이그레이션, 설정 캐시, 그리고 **reverb 데몬·큐 워커 재시작**(코드 변경 반영)이 한 절차로 묶여야 한다. 큐 워커는 코드를 메모리에 적재하므로 배포마다 `queue:restart` 필수. 백업·롤백 계획 없는 프로덕션 변경 금지(가드레일) → 배포 전 DB 백업·마이그레이션 롤백 경로 명시.
- **명령·설정 스니펫**:
  ```bash
  # deploy.sh (app 컨테이너 기준)
  docker exec gps119_app-app-1 php artisan down --render=errors::503
  docker exec gps119_app-db-1 sh -c 'mysqldump -ularavel -ppassword laravel' > backup-$(date +%F-%H%M).sql   # 롤백 대비
  docker exec gps119_app-app-1 composer install --no-dev --optimize-autoloader
  docker exec gps119_app-app-1 php artisan migrate --force
  docker exec gps119_app-app-1 npm ci && docker exec gps119_app-app-1 npm run build
  docker exec gps119_app-app-1 php artisan config:cache route:cache
  docker exec gps119_app-app-1 php artisan queue:restart       # 워커가 새 코드로 재기동
  docker exec gps119_app-app-1 php artisan reverb:restart      # 데몬 재기동
  docker exec gps119_app-app-1 php artisan up
  ```
  > CI는 별도 도입 전이라 본 태스크는 **수동/스크립트 배포 절차 표준화**까지. 자동 CI 파이프라인 도입은 OPEN(OI-B).
- **진행 (2026-08-10)**: 위 스니펫이 실제 스크립트로 구현됐다 → 저장소 루트 **`deploy.sh`**, 구성은 `docker-compose.prod.yml`·`docker/apache/apache-prod.conf`, 절차 문서는 **`DEPLOY.md`**. 배포 대상은 **ADR-0006**(AWS 서울 단일 VM).
  스니펫 대비 달라진 점: ① 덤프에 `--single-transaction`(서비스 중 잠금 회피) ② 배포마다 **이전 커밋 SHA + 덤프 경로를 기록**해 `./deploy.sh rollback` 한 명령으로 되돌림 ③ 실패 시 **점검 모드를 켠 채 정지**(깨진 코드로 여는 것 방지) ④ 실행 전 `APP_ENV`/`APP_URL`/DB 를 먼저 출력 — 「로컬인 줄 알고 실서버」 방지.
- **✅ 완료 (2026-08-10)** — 리허설 2회 + 실제 운영 배포까지 끝났다. **https://gps119.co.kr 운영 중** (인벤토리는 `DEPLOY.md` §0).
  - **로컬 리허설**: 격리 프로젝트(`-p`)에 저장소를 복제해 `deploy.sh` 를 무수정 실행 — 배포·롤백(코드 복귀 + DB 복원, 마커 행 소멸 확인)·**실패 경로**(빌드가 깨지는 릴리스를 일부러 배포 → 점검 모드 유지 + 503 → 안내대로 롤백해 복구) 전부 통과.
  - **실기 배포**: 실발급 TLS, 실도메인 DNS, 4GB 빌드, **재부팅 복귀**(다운타임 약 30초, 컨테이너 4개 자동 기동, 데이터 보존) 통과. Apache `${APP_DOMAIN}` 전개도 확인됨(두 vhost + 인증서 경로).
  - **여기서 잡은 결함**: ① 🔴 **롤백이 전혀 동작하지 않았다** — `DUMP_PATH="$(backup_db)"` 가 `log` 의 stdout 까지 캡처해 상태파일의 `PREV_DUMP` 가 ANSI 섞인 로그 문장이 됐고, `source` 가 `DB: command not found` 로 죽었다. 즉 **롤백이 필요한 순간에만** 실패하는 상태였다. `log`/`warn` 을 stderr 로 보내고, 상태파일을 `source` 대신 파싱하며, `PREV_SHA`/`PREV_DUMP` 를 선검증하도록 고쳤다. ② 🔴 ACME `<Directory>` 의 `Options None` 이 mod_rewrite 를 막아(AH00670) **Let's Encrypt 갱신 경로가 403** 이었다 — 방어가 방어 대상을 막던 경우. ③ `VITE_REVERB_APP_KEY` 가 빈 값이라 **실시간 전멸**(증상은 「계속 연결중」뿐). ④ 런북 1-5 의 `key:generate`/`composer install` 순서, ⑤ `db:seed --force` 누락(비대화형에서 조용히 취소 → 관리자 계정 미생성).
  - ⚠️ **남은 것**: `deploy.sh` 를 «운영기에서» 돌려본 적은 없다(최초 설치는 수동 §1 경로). 다음 배포가 첫 실행이다.
- **완료 검증**: 배포 리허설 1회 — `php artisan migrate:status` 최신, `queue:restart` 후 신규 잡 새 코드로 처리, `reverb:restart` 후 WS 재연결, `/up` 200. 롤백 리허설: `migrate:rollback` + 백업 복원 절차 1회 검증.
- **의존**: OPS-04, OPS-05, OPS-07.
- **규모**: M
- **Phase**: 0

---

## Phase 2 — (관측, 조기 도입)

### OPS-13 — 카카오 API 쿼터·키 모니터링 (R7)
- **설명**: 지도·내비는 카카오 JS 키 쿼터에 묶인다. 키를 용도/도메인별로 분리하고 쿼터 사용률을 관측해 임계 초과 전에 경보한다(R7 트리거). 내비 실패 시 웹 폴백 경로 점검 포함. Phase 2(관제 지도)에서 트래픽이 실측되므로 그 시점에 도입.
- **명령·설정 스니펫**:
  ```dotenv
  KAKAO_MAP_JS_KEY=...      # 도메인 제한 등록
  KAKAO_REST_KEY=...        # 좌표/주소 변환용 분리
  ```
  쿼터 사용률 임계(예: 80%) 초과 시 디스코드 경보(OPS-11 스케줄러에 점검 잡 추가).
- **완료 검증**: 키 도메인 제한 적용 확인, 의도적 임계 초과 시뮬레이션 시 경보 도달, 내비 실패 시 웹 폴백 표시.
- **의존**: M2 트래픽 발생 후. R7 소유자 kang-mansu.
- **규모**: S
- **Phase**: 2

---

## Phase 4 — 마감·운영 (M4)

### OPS-09 — Reverb + Redis 수평 확장 (다중 인스턴스 동기화)
- **설명**: ADR-0001 "초기 단일 인스턴스" 전제를 행사 동시 운영 규모(Q4)가 넘어서면 다중 Reverb 인스턴스가 같은 채널 상태를 공유해야 한다. Reverb의 Redis 스케일링 옵션을 켜고 redis 서비스를 compose에 추가, 큐도 `redis` 드라이버로 전환 검토(database 큐는 고빈도 ping에서 병목). **부하 검증 전엔 적용하지 않는다**(과도한 선제 복잡도 회피).
- **명령·설정 스니펫**:
  ```yaml
  redis:
    image: redis:7-alpine
    networks: [gps-network]
  ```
  ```dotenv
  REVERB_SCALING_ENABLED=true
  REDIS_HOST=redis
  # 고빈도 ping 시 QUEUE_CONNECTION=redis 전환 검토
  ```
  ```bash
  docker exec gps119_app-app-1 composer require predis/predis
  ```
- **완료 검증**: reverb 인스턴스 2개 기동 후 한쪽 구독자가 다른 인스턴스로 publish된 이벤트를 수신(redis pub/sub 경유), 부하테스트(OPS-10)에서 인스턴스 추가 시 큐 적체·WS 지연이 선형 완화.
- **의존**: OPS-01, OPS-04, BE-2.1(ping). Q4(동시 운영 규모)·R2(부하) 결정 후 발동.
- **규모**: L
- **Phase**: 4 (마스터플랜 OPS-4.1)

### OPS-10 — 부하/배터리 관측 + 위치 ping 부하 검증 (R2)
- **설명**: 대규모 동시 ping(R2)에서 큐 적체·WS 지연·DB INSERT 부하의 한계점을 실측한다. 가상 참가자 N명이 적응형 주기로 ping을 쏘는 부하 스크립트를 만들고, 큐 깊이·브로드캐스트 지연·DB write IOPS를 관측해 OPS-09(스케일) 발동 임계를 정한다. 동시에 클라이언트 배터리 소모(적응형 주기 5s/30s 검증)는 FE와 합동 실측.
- **명령·설정 스니펫**:
  ```bash
  # 부하 스크립트(예): 동시 ping POST 발사 → 큐 깊이 모니터링
  docker exec gps119_app-app-1 php artisan tinker --execute='echo DB::table("jobs")->count();'  # 적체 추적
  docker exec gps119_app-app-1 php artisan pail --filter=ParticipantLocation   # 브로드캐스트 지연 관측
  ```
  관측 지표: 큐 적체 건수, ping→마커반영 지연(ms), DB write/s, 워커 CPU.
- **완료 검증**: 목표 동시규모(Q4 확정값)에서 큐 적체가 임계 내 수렴, ping→관제 반영 지연 SLA(예: <2s) 충족. 미충족 시 OPS-09 트리거. 결과를 R2 트리거 임계로 문서화.
- **의존**: BE-2.1, BE-2.2(ping·이벤트 구현 후 측정 가능). Q4와 연동.
- **규모**: M
- **Phase**: 4 (마스터플랜 OPS-4.1)

### OPS-11 — `location_pings` 보존정책 자동화 (아카이브/파티셔닝/자동파기 cron)
- **설명**: `location_pings`는 append-only 고빈도 테이블(SPEC, 03 문서)이라 무한 증가한다. 보존기간(Q2/OI-7, 법무 검토 — 예: 종료 후 30일) 경과분을 **아카이브 이관 후 파기**하는 스케줄러 잡과, tracks 리포트(`/report/tracks`) 만료 정리를 자동화한다. 대용량 대비 월별 파티셔닝/배치 삭제. 개인정보 자동파기는 R3 대응의 핵심이며 **파괴적 작업이므로 보존기간 확정·드라이런 후에만 실삭제**(가드레일).
- **명령·설정 스니펫**:
  ```php
  // routes/console.php — 스케줄러 등록 (보존기간은 config로, Q2 확정 후 주입)
  Schedule::command('pings:prune --before='.now()->subDays(config('gps119.ping_retention_days')))
          ->daily()->onOneServer();
  ```
  ```bash
  # 스케줄러 러너: supervisor 프로그램 또는 cron 으로 schedule:run 1분 주기
  * * * * * docker exec gps119_app-app-1 php artisan schedule:run >> /dev/null 2>&1
  # 신규 prune 커맨드는 BE와 합동(배치 삭제+아카이브 이관), OPS는 스케줄·실행·감시 담당
  docker exec gps119_app-app-1 php artisan pings:prune --dry-run   # 드라이런 먼저
  ```
- **완료 검증**: 드라이런이 대상 건수만 보고(삭제 0), 실행 후 보존기간 초과 행만 제거·아카이브에 이관, 만료 tracks 리포트 정리, `schedule:list`에 잡 등록 확인. 종료된 행사 1건으로 end-to-end 파기 리허설.
- **의존**: BE-2.1(`location_pings` 존재). **Q2/OI-7(보존기간) 확정이 선행조건** — 미확정 시 잡은 비활성(드라이런만).
- **규모**: M
- **Phase**: 4 (마스터플랜 OPS-4.1). R3 대응.

### OPS-12 — Capacitor 하이브리드 빌드·배포 + 백그라운드/FCM 운영 (R1)
- **설명**: PWA 백그라운드 위치 한계(R1)를 네이티브로 보강하는 Capacitor 래핑의 **빌드/서명/배포·푸시 인프라** 담당. FCM 프로젝트·서버키 구성, 네이티브 geolocation 백그라운드 플러그인 권한·배터리 튜닝 관측, 스토어 빌드 파이프라인. 인앱(Reverb)→백그라운드(FCM) 전환 경계(Q7)는 PM과 합의 후. FE-4.1(PWA 마감) 이후.
- **명령·설정 스니펫**:
  ```bash
  # 빌드 산출물 래핑(앱 컨테이너 외 별도 빌드 환경 필요 — Android SDK 등)
  npm i @capacitor/core @capacitor/cli @capacitor/geolocation
  npx cap add android && npx cap sync
  ```
  ```dotenv
  FCM_SERVER_KEY=...        # 평문 노출 금지(.env)
  ```
- **완료 검증**: 화면 꺼진 상태(백그라운드)에서 ping 지속 도달(R1 트리거 해소), FCM 푸시 도달, 배터리 소모 측정치 기준 내, 스토어 제출 가능 빌드 산출.
- **의존**: FE-4.1, FE-2.2. **Q7(푸시 전환 경계)·R1 확인 후.** 스토어 계정·서명키는 OPEN(OI-C).
- **규모**: L
- **Phase**: 4 (마스터플랜 OPS-4.2)

---

## [OPEN] — 인프라 미결 (착수 전 합의 필요)

> 마스터플랜 §6(Q1~Q7)·architecture-spec OPEN ISSUE(OI-7 등) 중 **인프라/운영에 직접 묶이는 것**만 모음. 결정 주체는 마스터플랜 RACI를 따른다.

| # | 미결 | 막는 OPS 태스크 | 결정 주체 | 비고 |
|---|------|------------------|-----------|------|
| ~~**OI-A**~~ | ~~운영 TLS 인증서 발급·반입 경로~~ | ~~OPS-03~~ | — | ✅ **해소(2026-08-10)** — Let's Encrypt. 최초 발급 standalone → 갱신 webroot(+deploy-hook). ADR-0006 / `DEPLOY.md` 1-4. 운영 도메인 확정만 남음 |
| **OI-B** | CI 파이프라인 도입 여부·시점(현재 수동 배포 스크립트) | OPS-08 자동화 | kang-mansu + PM | Q5(OpenAPI)와 별개. 지금은 표준화된 수동 절차로 출시 가능 |
| **OI-C** | 스토어 배포 계정·앱 서명키·FCM 프로젝트 소유 | OPS-12 | PM + 고객 | 하이브리드 단계(M4) 진입 전 확보 |
| **OI-D** | **공공(지자체) 영업 진입 시점** — NCP 이전 + CSAP SaaS 심사 착수 트리거 | 신규(인프라 이전) | **PM** | ADR-0006. AWS 서울의 CSAP는 「하」등급이라 개인정보를 다루는 이 앱은 범위 밖 → 공공엔 국내 CSP 필요. **트리거는 「계약 확정」이 아니라 「영업 대상에 넣는 시점」** — 심사가 6개월+ 라 공고 보고 시작하면 늦는다 |
| **Q4** | **행사 동시 운영 규모** — 단일 Reverb로 충분한가 | OPS-09, OPS-10 발동 임계 | **kang-mansu** | ADR-0001 단일 인스턴스 전제의 유효성. R2와 직결. M2에서 조기 부하검토 권장 |
| **Q2 / OI-7** | **위치이력 보존기간**(법무) | OPS-11 자동파기 잡 설계 | **PM(법무 자문)** | 미확정 시 OPS-11은 드라이런만. 개인정보 자동파기는 파괴적 작업이라 확정 전 실삭제 금지 |
| **OI-8(spec)** | 소프트삭제 행사의 pings/participants 정리 정책 | OPS-11 파기 범위 | na-minsik + PM | `projects` SoftDelete vs 자식 `cascadeOnDelete` 불일치 — 파기 잡이 어디까지 청소할지 결정 |
| **Q7** | 인앱(Reverb)→백그라운드(FCM) 푸시 전환 경계 | OPS-12 | kang-mansu + PM | 범위·일정 영향 |

> **운영상 가장 큰 두 미결**: ① **Q4(동시 운영 규모)** — 단일 Reverb 전제가 무너지면 OPS-09(Redis 스케일·L)가 크리티컬해지고 큐 드라이버(database→redis)까지 흔든다. ② **Q2/OI-7(위치이력 보존기간)** — 법무 미확정이면 OPS-11 자동파기를 켤 수 없고, 개인정보(R3) 리스크가 M2 운영 내내 누적된다.
