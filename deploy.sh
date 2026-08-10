#!/usr/bin/env bash
#
# GPS119 운영 배포 스크립트 (OPS-08). **서버 위에서** 실행한다 — 노트북에서 원격으로 쏘는 게 아니다.
#
#   ./deploy.sh                  origin/main 으로 배포
#   ./deploy.sh v1.2.0           특정 태그·커밋으로 배포
#   ./deploy.sh --yes            확인 프롬프트 없이 (cron·CI 용)
#   ./deploy.sh rollback         직전 릴리스로 되돌림 (코드 + DB 복원, 파괴적)
#
# 🔑 왜 «코드 pull» 로 안 끝나는가
#    큐 워커는 로드한 클래스를 메모리에 들고 있어서, 리스너를 고쳐도 재기동 전까지 옛 코드로 돈다.
#    Reverb 데몬도 마찬가지다. 그래서 마이그레이션·캐시·데몬 재기동이 한 절차로 묶여야 한다.
#
# 실패하면 «점검 모드를 켠 채로» 멈춘다. 깨진 코드로 서비스를 여는 것보다 낫다.

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

COMPOSE_FILE="docker-compose.prod.yml"
ENV_DEPLOY=".env.deploy"
LARAVEL_ENV="src/.env"
BACKUP_DIR="$ROOT/backups"
STATE_DIR="$ROOT/.deploy"
STATE_FILE="$STATE_DIR/last-release"

ASSUME_YES=0
MODE="deploy"
TARGET_REF=""

# ---------------------------------------------------------------- 출력 헬퍼
c_red=$'\033[31m'; c_grn=$'\033[32m'; c_ylw=$'\033[33m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
log()  { printf '%s==>%s %s\n' "$c_grn" "$c_off" "$*"; }
warn() { printf '%s[!]%s %s\n' "$c_ylw" "$c_off" "$*"; }
die()  { printf '%s[x]%s %s\n' "$c_red" "$c_off" "$*" >&2; exit 1; }

dc() { docker compose --env-file "$ENV_DEPLOY" -f "$COMPOSE_FILE" "$@"; }
art() { dc exec -T app php artisan "$@"; }

confirm() {
    [ "$ASSUME_YES" = 1 ] && return 0
    local answer
    read -r -p "$1 [y/N] " answer
    [[ "$answer" =~ ^[Yy]$ ]] || die "중단했다."
}

env_get() { # env_get FILE KEY  — 값에 = 이 들어가도 안전하게
    sed -n "s/^$2=//p" "$1" | head -1 | tr -d '"' | tr -d "'"
}

# ---------------------------------------------------------------- 인자 파싱
for arg in "$@"; do
    case "$arg" in
        --yes|-y)  ASSUME_YES=1 ;;
        rollback)  MODE="rollback" ;;
        -*)        die "모르는 옵션: $arg" ;;
        *)         TARGET_REF="$arg" ;;
    esac
done

# ---------------------------------------------------------------- 사전 점검
[ -f "$COMPOSE_FILE" ] || die "$COMPOSE_FILE 이 없다. 저장소 루트에서 실행할 것."
[ -f "$ENV_DEPLOY" ]   || die "$ENV_DEPLOY 이 없다. .env.deploy.example 을 복사해 채울 것."
[ -f "$LARAVEL_ENV" ]  || die "$LARAVEL_ENV 이 없다. src/.env.production.example 을 복사해 채울 것."
command -v docker >/dev/null || die "docker 가 없다."
mkdir -p "$BACKUP_DIR" "$STATE_DIR"

APP_ENV_VAL="$(env_get "$LARAVEL_ENV" APP_ENV)"
APP_URL_VAL="$(env_get "$LARAVEL_ENV" APP_URL)"
APP_DEBUG_VAL="$(env_get "$LARAVEL_ENV" APP_DEBUG)"
DB_NAME="$(env_get "$ENV_DEPLOY" DB_DATABASE)"
APP_DOMAIN_VAL="$(env_get "$ENV_DEPLOY" APP_DOMAIN)"

# 🔑 「로컬인 줄 알고 실서버를 건드렸다」를 막는 지점. 무엇을 향해 쏘는지 «먼저» 보여준다.
cat <<EOF

  ${c_dim}--- 배포 대상 ---------------------------------${c_off}
   모드      : $MODE
   APP_ENV   : $APP_ENV_VAL
   APP_URL   : $APP_URL_VAL
   도메인    : $APP_DOMAIN_VAL
   DB        : $DB_NAME  (컨테이너 db, 호스트 포트 비공개)
   대상 ref  : ${TARGET_REF:-origin/main}
  ${c_dim}-----------------------------------------------${c_off}

EOF

[ "$APP_DEBUG_VAL" = "false" ] || warn "APP_DEBUG 가 false 가 아니다 ($APP_DEBUG_VAL) — 스택트레이스가 노출된다."
[ "$APP_ENV_VAL" = "production" ] || warn "APP_ENV 가 production 이 아니다 ($APP_ENV_VAL)."

# 실패 시 점검 모드를 켠 채로 멈춘다는 걸 알린다
on_error() {
    printf '\n%s[x] 배포가 중간에 실패했다.%s\n' "$c_red" "$c_off" >&2
    printf '    점검 모드는 «켜진 채»로 둔다. 깨진 코드를 여는 것보다 낫다.\n' >&2
    printf '    복구:  ./deploy.sh rollback      (직전 릴리스로)\n' >&2
    printf '    강제 오픈: docker compose --env-file %s -f %s exec -T app php artisan up\n' \
        "$ENV_DEPLOY" "$COMPOSE_FILE" >&2
}
trap on_error ERR

backup_db() {
    local stamp out
    stamp="$(date -u +%Y%m%d-%H%M%SZ)"
    out="$BACKUP_DIR/db-$stamp.sql.gz"
    log "DB 백업 → $out"
    # --single-transaction: InnoDB 를 «잠그지 않고» 일관된 스냅샷을 뜬다(서비스 중 안전).
    dc exec -T db sh -c \
        'exec mysqldump --single-transaction --quick --routines --no-tablespaces \
             -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
        | gzip > "$out"
    [ -s "$out" ] || die "백업 파일이 비었다. 중단한다."
    echo "$out"
}

wait_healthy() {
    local url="${APP_URL_VAL%/}/up" i
    log "헬스체크: $url"
    for i in $(seq 1 30); do
        if curl -fsS --max-time 5 "$url" >/dev/null 2>&1; then
            log "정상 응답 확인 (${i}회차)"
            return 0
        fi
        sleep 2
    done
    warn "헬스체크 실패 — /up 이 30회(60초) 동안 응답하지 않았다."
    warn "로그: docker compose --env-file $ENV_DEPLOY -f $COMPOSE_FILE logs --tail=100 app"
    return 1
}

build_and_migrate() {
    log "의존성 설치 (composer, 운영용)"
    dc exec -T app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

    log "프론트엔드 빌드 (vite)"
    dc exec -T app npm ci
    dc exec -T app npm run build

    log "마이그레이션"
    art migrate --force

    log "캐시 재생성"
    art optimize:clear
    art optimize

    # 🔑 워커·데몬은 «반드시» 재기동한다. queue:restart 는 현재 잡을 끝내고 죽으라는 신호이고,
    #    컨테이너 재시작까지 겹쳐야 새 코드 적재가 확실해진다.
    log "큐 워커 · Reverb 재기동"
    art queue:restart || true
    dc restart queue reverb
}

# ---------------------------------------------------------------- rollback
if [ "$MODE" = "rollback" ]; then
    [ -f "$STATE_FILE" ] || die "$STATE_FILE 이 없다 — 되돌릴 릴리스 기록이 없다."
    # shellcheck disable=SC1090
    source "$STATE_FILE"   # PREV_SHA, PREV_DUMP
    cat <<EOF
  ${c_ylw}롤백은 파괴적이다.${c_off}
   코드   : $(git rev-parse --short HEAD) → ${PREV_SHA:0:7}
   DB     : $PREV_DUMP 로 «덮어쓴다» (배포 이후 들어온 구조요청·위치이력은 사라진다)
EOF
    confirm "정말 롤백하나?"
    read -r -p "확인을 위해 DB 이름을 입력: " typed
    [ "$typed" = "$DB_NAME" ] || die "DB 이름이 다르다. 중단했다."

    log "점검 모드 진입"
    art down --retry=60 || true

    log "롤백 직전 상태를 한 번 더 백업"
    backup_db >/dev/null

    log "코드 되돌리기 → $PREV_SHA"
    git checkout --detach "$PREV_SHA"

    log "DB 복원 ← $PREV_DUMP"
    gunzip -c "$PREV_DUMP" | dc exec -T db sh -c \
        'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

    build_and_migrate
    art up
    wait_healthy
    log "롤백 완료."
    exit 0
fi

# ---------------------------------------------------------------- deploy
confirm "위 대상으로 배포한다. 계속하나?"

CURRENT_SHA="$(git rev-parse HEAD)"

log "원격 갱신"
git fetch --prune --tags

REF="${TARGET_REF:-origin/main}"
git rev-parse --verify "$REF^{commit}" >/dev/null 2>&1 || die "ref 를 찾을 수 없다: $REF"
NEW_SHA="$(git rev-parse "$REF^{commit}")"

if [ "$CURRENT_SHA" = "$NEW_SHA" ]; then
    warn "이미 $REF (${NEW_SHA:0:7}) 와 같은 커밋이다. 재배포를 진행한다(캐시·데몬 갱신 목적)."
fi

log "변경 요약  ${CURRENT_SHA:0:7} → ${NEW_SHA:0:7}"
git --no-pager log --oneline "$CURRENT_SHA..$NEW_SHA" | head -20 || true

DUMP_PATH="$(backup_db)"

# 롤백 지점 기록 — «코드를 바꾸기 전에» 쓴다.
cat > "$STATE_FILE" <<EOF
PREV_SHA=$CURRENT_SHA
PREV_DUMP=$DUMP_PATH
DEPLOYED_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
EOF

log "점검 모드 진입"
art down --retry=60 || true

log "코드 갱신 → ${NEW_SHA:0:7}"
git checkout --detach "$NEW_SHA"

build_and_migrate

log "점검 모드 해제"
art up

wait_healthy

# 오래된 백업 정리 (14일). 4GB 인스턴스라 덤프가 쌓이면 디스크가 먼저 죽는다.
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +14 -delete 2>/dev/null || true

trap - ERR
log "배포 완료: ${NEW_SHA:0:7}"
printf '   되돌리려면: ./deploy.sh rollback\n'
