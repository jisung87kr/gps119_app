# 운영 배포 런북

대상: **AWS Lightsail 서울, 단일 인스턴스 + docker compose.**
관련 태스크: OPS-08(배포 절차), OI-A(운영 TLS 발급 경로).

> 상시 스테이징 서버는 두지 않는다. 배포 전 검증은 로컬 도커(운영과 동일 구성)로 하고,
> 배포 리허설·부하테스트(OPS-10)는 **운영 스냅샷에서 임시 인스턴스를 복제해 쓰고 지운다.**

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

### 1-3. 환경파일 2개

```bash
cp .env.deploy.example .env.deploy          # 도메인, MySQL 비밀번호
cp src/.env.production.example src/.env     # Laravel 전체
```

⚠️ `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` 는 **양쪽에 같은 값**을 넣는다. 어긋나면
컨테이너는 정상적으로 뜨는데 앱만 DB 접속에 실패한다.

### 1-4. TLS 인증서 — **compose 기동보다 먼저**

443 vhost가 인증서 파일을 참조하므로, 인증서가 없으면 Apache가 아예 안 뜬다.
그런데 갱신용 webroot는 그 Apache가 서빙한다. 그래서 **최초 발급만 standalone**으로 한다.

```bash
sudo certbot certonly --standalone -d gps119.example.com \
     -m admin@example.com --agree-tos --non-interactive

# 이후 갱신은 webroot 로 전환 (Apache 를 멈추지 않아도 되게)
sudo certbot certonly --webroot -w ~/gps119_app/src/public -d gps119.example.com \
     --cert-name gps119.example.com \
     --deploy-hook "cd ~/gps119_app && docker compose --env-file .env.deploy -f docker-compose.prod.yml exec -T app apachectl graceful"
```

certbot의 systemd 타이머가 갱신을 돌린다. 확인: `sudo certbot renew --dry-run`

### 1-5. 첫 기동 · 초기화

```bash
cd ~/gps119_app
docker compose --env-file .env.deploy -f docker-compose.prod.yml up -d --build

alias dc='docker compose --env-file .env.deploy -f docker-compose.prod.yml'
dc exec app php artisan key:generate
dc exec app composer install --no-dev --optimize-autoloader
dc exec app npm ci && dc exec app npm run build
dc exec app php artisan migrate --force
dc exec app php artisan db:seed --class=RolePermissionSeeder   # admin@admin.com 생성 → 비밀번호 즉시 변경
dc exec app php artisan push:vapid-keys                        # 출력값을 src/.env 에 기록
dc exec app php artisan optimize
```

FCM 서비스 계정 JSON은 git에 없다. 직접 올린다:

```bash
scp fcm-service-account.json ubuntu@<IP>:~/gps119_app/src/storage/app/
```

### 1-6. 첫 확인

```bash
curl -fsS https://gps119.example.com/up          # 200
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
- **배포 리허설·롤백 리허설 미실시.** OPS-08 완료 조건이며, 첫 행사 전에 임시 인스턴스로 1회 돌려야 한다
