# 운영 배포 런북

대상: **AWS Lightsail 서울, 단일 인스턴스 + docker compose.**
관련 태스크: OPS-08(배포 절차), OI-A(운영 TLS 발급 경로).

> 상시 스테이징 서버는 두지 않는다. 배포 전 검증은 로컬 도커(운영과 동일 구성)로 하고,
> 배포 리허설·부하테스트(OPS-10)는 **운영 스냅샷에서 임시 인스턴스를 복제해 쓰고 지운다.**

> 🔴 **로컬에서 운영 compose 를 띄울 때는 반드시 `-p` 로 프로젝트를 분리한다.**
> `docker-compose.prod.yml` 의 `name: gps119_app` 은 개발용 compose 의 기본 프로젝트명
> (디렉터리명 = `gps119_app`)과 **같다.** 그대로 띄우면 같은 프로젝트로 취급돼서
> 개발용 컨테이너를 운영 정의로 갈아엎고, 볼륨 `mysql_data` 도 공유한다 — 즉 운영 설정
> 컨테이너가 **개발 DB** 에 붙는다(`.env.deploy` 의 `MYSQL_*` 는 볼륨이 이미 있으면 무시된다).
> 이 상태에서 `down -v` 를 치면 개발 DB 가 날아간다.
> ```bash
> docker compose -p gps119_rehearsal --env-file .env.deploy -f docker-compose.prod.yml <명령>
> ```
> 서버에는 compose 가 하나뿐이라 문제가 없다. **로컬에서만 걸리는 함정이다.**

파일 구성:

| 파일 | 역할 |
|---|---|
| `docker-compose.prod.yml` | 운영 컨테이너 정의 (개발용 `docker-compose.yml` 과 별개) |
| `docker/apache/apache-prod.conf` | 운영 vhost (80→443 리다이렉트 + Let's Encrypt) |
| `.env.deploy` | compose 전용 값 (도메인, MySQL 초기화) — `.env.deploy.example` 복사 |
| `src/.env` | Laravel 값 — `src/.env.production.example` 복사 |
| `deploy.sh` | 배포·롤백 |

---

## 1. 최초 1회 설정

### 1-1. 인스턴스 · 네트워크

- Lightsail **서울 리전**, Ubuntu 22.04 LTS, **2vCPU / 4GB** 시작
  (첫 행사가 수백 명 규모면 4vCPU/8GB — 위치 ping 부하는 OPS-10 전까지 실측값이 없다)
- 고정 IP 할당 → 도메인 A 레코드 연결
- 방화벽: **22 / 80 / 443만** 개방. MySQL(3306)·Reverb(8080)는 **열지 않는다**
  (운영 compose가 애초에 호스트 포트를 노출하지 않는다)

### 1-2. 도커 · 코드

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-v2 git certbot
sudo usermod -aG docker $USER && newgrp docker

git clone <repo> ~/gps119_app && cd ~/gps119_app
```

**swap 2GB 를 먼저 잡는다.** `deploy.sh` 가 컨테이너 안에서 `npm run build`(Vite + Tailwind 4)
를 돌리는데, 4GB 인스턴스에서는 여기서 OOM 으로 죽을 수 있다. 죽으면 배포가 **점검 모드를
켜 둔 채로** 멈추므로(의도된 동작) 서비스가 내려간 상태에서 원인을 찾게 된다.

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab   # 재부팅 후에도 유지
sudo sysctl -w vm.swappiness=10                              # 디스크 스왑은 최후수단으로만
```

### 1-3. 환경파일 2개

```bash
cp .env.deploy.example .env.deploy          # 도메인, MySQL 비밀번호
cp src/.env.production.example src/.env     # Laravel 전체
```

⚠️ `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` 는 **양쪽에 같은 값**을 넣는다. 어긋나면
컨테이너는 정상적으로 뜨는데 앱만 DB 접속에 실패한다.

⚠️ **`REVERB_APP_ID/KEY/SECRET` 을 여기서 채운다** — 아래 1-5 의 `npm run build` «전»이어야 한다.
`VITE_REVERB_APP_KEY` 는 빌드 산출물에 상수로 박히므로, 비운 채 빌드하면 나중에 `.env` 만
고쳐도 반영되지 않는다(재빌드 필요). 증상은 「화면은 뜨는데 계속 연결중」뿐이라 원인을
Apache·방화벽에서 찾게 된다. VAPID 공개키는 meta 태그로 나가므로 나중에 채워도 된다.

### 1-4. TLS 인증서 — **compose 기동보다 먼저**

443 vhost가 인증서 파일을 참조하므로, 인증서가 없으면 Apache가 아예 안 뜬다.
그런데 갱신용 webroot는 그 Apache가 서빙한다. 그래서 **최초 발급만 standalone**으로 한다.

⚠️ **A 레코드가 이 인스턴스를 가리키는지 «먼저» 확인한다.** Let's Encrypt 는 도메인을
DNS 로 찾아와 검증하므로, 레코드가 옛 IP를 가리키면 standalone 발급이 실패한다.
`gps119.co.kr` 은 가비아에 등록돼 있고 **낡은 A 레코드(`125.179.162.5`, 응답 없음)** 가
남아 있다 — 갈아끼운 뒤 전파를 확인하고 나서 certbot 을 돌린다.

```bash
dig +short gps119.co.kr    # 새 고정 IP 가 나와야 한다. 아니면 아래는 반드시 실패한다
```

```bash
# -m 은 «실제로 수신되는» 주소여야 한다. 만료 임박 경고가 여기로만 온다.
sudo certbot certonly --standalone -d gps119.co.kr \
     -m admin@gps119.co.kr --agree-tos --non-interactive

# 이후 갱신은 webroot 로 전환 (Apache 를 멈추지 않아도 되게)
sudo certbot certonly --webroot -w ~/gps119_app/src/public -d gps119.co.kr \
     --cert-name gps119.co.kr \
     --deploy-hook "cd ~/gps119_app && docker compose --env-file .env.deploy -f docker-compose.prod.yml exec -T app apachectl graceful"
```

certbot의 systemd 타이머가 갱신을 돌린다. 확인: `sudo certbot renew --dry-run`

### 1-5. 첫 기동 · 초기화

```bash
cd ~/gps119_app
docker compose --env-file .env.deploy -f docker-compose.prod.yml up -d --build

alias dc='docker compose --env-file .env.deploy -f docker-compose.prod.yml'

# ⚠️ composer 가 «맨 먼저»다. vendor/ 가 없으면 artisan 자체가 뜨지 않아
#    key:generate 부터 죽는다(리허설에서 실제로 걸렸다).
dc exec app composer install --no-dev --optimize-autoloader
dc exec app php artisan key:generate --force
dc exec app npm ci && dc exec app npm run build
dc exec app php artisan migrate --force
# ⚠️ --force 없이는 production 에서 확인 프롬프트가 뜨고, 비대화형이면 «Command cancelled»
#    로 조용히 취소된다 — 관리자 계정이 안 생긴 채로 다음 단계로 넘어간다.
dc exec app php artisan db:seed --class=RolePermissionSeeder --force   # admin@admin.com → 비밀번호 즉시 변경
dc exec app php artisan push:vapid-keys                        # 출력값을 src/.env 에 기록
dc exec app php artisan optimize
```

FCM 서비스 계정 JSON은 git에 없다. 직접 올린다:

```bash
scp fcm-service-account.json ubuntu@<IP>:~/gps119_app/src/storage/app/
```

### 1-6. 첫 확인

```bash
curl -fsS https://gps119.co.kr/up          # 200
dc exec app apachectl -M | grep -E 'proxy_wstunnel|ssl'
dc logs --tail=50 reverb                          # 데몬 기동 확인
```

브라우저에서 로그인 → 구조요청 생성 → `/control` 지도에 실시간 반영까지 한 번 돌려본다.

---

## 2. 이후 배포

```bash
cd ~/gps119_app && ./deploy.sh              # origin/main
./deploy.sh v1.2.0                          # 특정 태그
```

스크립트가 하는 일: 대상 표시·확인 → **DB 백업** → 롤백 지점 기록 → 점검 모드 →
코드 체크아웃 → composer/npm 빌드 → 마이그레이션 → 캐시 재생성 →
**큐 워커·Reverb 재기동** → 점검 해제 → `/up` 헬스체크.

- 서버 저장소는 **detached HEAD** 상태로 유지된다(의도된 것 — 배포된 커밋이 명시적으로 고정된다)
- 실패하면 **점검 모드를 켠 채로 멈춘다.** 깨진 코드를 여는 것보다 낫다

## 3. 롤백

```bash
./deploy.sh rollback
```

직전 릴리스의 커밋 + 그 배포 직전에 뜬 덤프로 되돌린다.
**파괴적이다** — 배포 이후 들어온 구조요청·위치이력은 사라진다. DB 이름을 직접 입력해야 진행된다.

## 4. 백업

배포마다 자동으로 뜨지만, 그것만으로는 「배포하지 않은 날」이 비어 있다. 일일 백업을 건다:

```bash
crontab -e
# 매일 04:10 KST
10 4 * * * cd ~/gps119_app && docker compose --env-file .env.deploy -f docker-compose.prod.yml exec -T db sh -c 'exec mysqldump --single-transaction --quick -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | gzip > backups/db-cron-$(date -u +\%Y\%m\%d).sql.gz 2>> backups/cron.log
```

인스턴스 스냅샷(Lightsail 자동 스냅샷)도 함께 켠다 — 덤프는 DB만 복구하지 서버는 복구하지 못한다.
여유가 되면 덤프를 S3로 밀어두는 게 좋다. **같은 디스크에만 있는 백업은 디스크가 죽으면 같이 죽는다.**

---

## 5. 코드 밖에서 해야 하는 것 (배포 전 체크)

- [ ] **카카오 개발자 콘솔** — 플랫폼에 운영 도메인 등록. 안 하면 지도가 통째로 안 뜬다
- [ ] **네이버/카카오 로그인** — Redirect URI 를 운영 도메인으로 등록 + `src/.env` 반영
- [ ] **APNs 운영 인증키** / FCM 프로젝트 설정
- [ ] **앱 셸 재빌드** — `~/Dev/gps119_app_mobile` 의 `server.url` 을 운영 도메인으로 바꾸고
      재빌드 → 스토어 재제출. 원격 URL 방식이라 도메인이 바뀌면 앱도 다시 나가야 한다
- [ ] **위치정보사업 신고** (N0 블로커) · 개인정보처리방침 · 위치기반서비스 이용약관
- [ ] `admin@admin.com` 초기 비밀번호 변경

## 6. 아직 안 한 것 (의도적으로 남긴 것)

- **CI 파이프라인 없음** (OI-B). 지금은 서버에서 `./deploy.sh` 를 직접 돌리는 수동 절차다
- **스케줄러 서비스 꺼져 있음.** `location_pings` 자동파기(OPS-11)는 보존기간(Q2/OI-7)이
  법무에서 확정되기 전까지 켜지 않는다 — 파괴적 작업이다
- **Redis 없음.** 큐·캐시 모두 database 드라이버. 단일 Reverb 전제(ADR-0001)를 유지하며,
  전환 임계는 OPS-10 부하테스트 결과로 정한다
- **관리형 DB 아님.** 컨테이너 MySQL + 덤프 백업. 유료 고객이 붙으면 RDS 분리를 검토한다
- **배포·롤백 리허설은 «로컬에서» 1회 마쳤다(2026-08-10).** 격리 프로젝트에 저장소를 복제해
  `deploy.sh` 를 무수정으로 실행 — 배포(백업→점검→체크아웃→빌드→마이그레이션→데몬 재기동→
  헬스체크)와 롤백(코드 복귀 + DB 복원, 마커 행이 사라지는 것까지)이 모두 통과했다.
  여기서 잡은 결함 3건은 이 문서와 `deploy.sh` 에 반영돼 있다(롤백 상태파일 파손, 위 1-5 순서 2건).
  **실패 경로도 확인했다** — Vite 빌드가 깨지는 릴리스를 일부러 배포해, 스크립트가 중단되고
  점검 모드가 «켜진 채»로 남아 사용자에게 503 이 나가는 것(깨진 코드가 열리지 않는 것),
  그리고 안내대로 `./deploy.sh rollback` 을 치면 직전 릴리스로 복구되고 점검 모드가 풀리는 것까지.
  **아직 안 한 것: 실제 인스턴스에서의 리허설.** Let's Encrypt 실발급, 실도메인 DNS,
  4GB 메모리에서의 빌드, 재부팅 복귀는 로컬로 대체되지 않는다 — 첫 행사 전에 임시 인스턴스로 1회 돌린다
