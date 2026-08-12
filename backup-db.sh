#!/usr/bin/env bash
#
# 일일 DB 백업 — 덤프를 «공개키로 암호화»해서 남긴다.
#
# 왜 암호화하는가
#   지금까지 덤프는 서버 디스크에 평문 gzip 으로 쌓였다. 이 DB 에는 이름·전화번호와
#   위치 이력(location_pings)이 들어 있다. 덤프 파일 하나가 새면 그게 통째로 샌다.
#
# 🔑 «비대칭»인 이유 — 서버에는 **공개키만** 둔다. 대칭키(암호)를 서버에 두면 서버가
#    털렸을 때 과거 백업까지 전부 읽힌다. 공개키만 있으면 서버는 «쓰기»만 할 수 있고,
#    복호화는 개인키를 가진 곳(운영자 노트북 ~/.gps119-keys/)에서만 된다.
#    즉 이 스크립트는 «덤프 파일이 새는 경우»와 «서버가 털리는 경우»를 둘 다 덮는다.
#
# ⚠️ 개인키를 잃으면 백업을 영원히 복구할 수 없다. 개인키는 «서버가 아닌 곳»에
#    최소 두 벌 보관한다. 그리고 복구 훈련을 한 번도 안 해본 백업은 백업이 아니다 —
#    아래 「복구」 절차를 분기마다 실제로 돌려볼 것.
#
# 사용법 (서버):
#   ./backup-db.sh
#
# 필요한 환경변수 (.env.deploy 또는 셸):
#   BACKUP_GPG_RECIPIENT   암호화 대상 공개키의 식별자(이메일 또는 키 ID). 필수.
#   BACKUP_KEEP_DAYS       보관일수. 기본 14.
#   BACKUP_OFFSITE_CMD     (선택) 오프사이트 반출 명령. 파일 경로가 $1 로 넘어간다.
#                          예) 'aws s3 cp "$1" s3://gps119-backup/'
#
set -euo pipefail

cd "$(dirname "$0")"

ENV_DEPLOY="${ENV_DEPLOY:-.env.deploy}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"

# 🔑 로그는 stderr 로. stdout 은 «파일 경로»를 돌려주는 통로다 —
#    deploy.sh 에서 로그가 stdout 을 오염시켜 롤백이 통째로 깨진 전례가 있다.
log()  { printf '==> %s\n' "$*" >&2; }
die()  { printf '[x] %s\n' "$*" >&2; exit 1; }

[ -f "$ENV_DEPLOY" ] || die "$ENV_DEPLOY 가 없다. 서버의 저장소 루트에서 실행할 것."
[ -n "${BACKUP_GPG_RECIPIENT:-}" ] || BACKUP_GPG_RECIPIENT="$(sed -n 's/^BACKUP_GPG_RECIPIENT=//p' "$ENV_DEPLOY" | head -1 | tr -d '"'"'"'')"
[ -n "${BACKUP_GPG_RECIPIENT:-}" ] || die "BACKUP_GPG_RECIPIENT 가 없다. 공개키를 먼저 등록할 것(DEPLOY.md §4)."

command -v gpg >/dev/null || die "gpg 가 없다. sudo apt-get install -y gnupg"
gpg --list-keys "$BACKUP_GPG_RECIPIENT" >/dev/null 2>&1 \
    || die "공개키를 찾을 수 없다: $BACKUP_GPG_RECIPIENT (gpg --import 로 등록)"

mkdir -p "$BACKUP_DIR"
stamp="$(date -u +%Y%m%d-%H%M%SZ)"
plain="$BACKUP_DIR/db-$stamp.sql.gz"
out="$plain.gpg"

log "DB 덤프 → $out"

# --single-transaction: InnoDB 를 «잠그지 않고» 일관된 스냅샷을 뜬다(서비스 중 안전).
docker compose --env-file "$ENV_DEPLOY" -f "$COMPOSE_FILE" exec -T db sh -c \
    'exec mysqldump --single-transaction --quick --routines --no-tablespaces \
         -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$plain"

# 🔴 «조용히 빈 덤프»가 이 크론의 알려진 실패 모드다. ubuntu 가 docker 그룹에 없으면
#    명령이 실패해도 파일은 만들어지고, 아무도 모르다가 복구가 필요한 날에 안다.
#    gzip 헤더만 있는 파일도 수십 바이트는 되므로 «비어 있지 않다»로는 부족하다.
[ -s "$plain" ] || { rm -f "$plain"; die "덤프가 비었다. docker 권한(usermod -aG docker ubuntu 후 재로그인)을 확인할 것."; }
[ "$(wc -c < "$plain")" -ge 1024 ] || { rm -f "$plain"; die "덤프가 비정상적으로 작다($(wc -c < "$plain") 바이트). 중단한다."; }
gzip -t "$plain" 2>/dev/null || { rm -f "$plain"; die "덤프 gzip 이 깨졌다. 중단한다."; }

gpg --batch --yes --trust-model always \
    --recipient "$BACKUP_GPG_RECIPIENT" \
    --output "$out" --encrypt "$plain" \
    || { rm -f "$plain" "$out"; die "암호화 실패. 평문을 남기지 않고 중단한다."; }

# 평문은 «반드시» 지운다. 남겨두면 암호화한 의미가 없다.
rm -f "$plain"
chmod 600 "$out"

log "완료: $out ($(du -h "$out" | cut -f1))"

if [ -n "${BACKUP_OFFSITE_CMD:-}" ]; then
    log "오프사이트 반출"
    # 실패해도 백업 자체는 남았으므로 크론을 죽이지 않는다. 다만 조용히 넘기지도 않는다.
    bash -c "$BACKUP_OFFSITE_CMD" _ "$out" || log "[!] 오프사이트 반출 실패 — 로컬 백업은 남아 있다."
else
    log "[!] BACKUP_OFFSITE_CMD 미설정 — 백업이 이 서버에만 있다. 디스크가 죽으면 같이 죽는다."
fi

# 보관기간 초과분 정리. 암호문만 지운다(평문은 애초에 남기지 않는다).
find "$BACKUP_DIR" -name 'db-*.sql.gz.gpg' -mtime "+$KEEP_DAYS" -delete 2>/dev/null || true

echo "$out"
