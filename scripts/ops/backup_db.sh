#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${ENV_FILE:-${REPO_ROOT}/.env}"

COMPOSE_CMD="${COMPOSE_CMD:-docker compose}"
DB_SERVICE="${DB_SERVICE:-mariadb}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="${DB_NAME:-}"
BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/backups}"
KEEP_DAYS="${KEEP_DAYS:-30}"
PRINT_PATH_ONLY=0

usage() {
  cat <<'EOF'
Usage: backup_db.sh [options]

Options:
  -o, --output-dir <path>   Backup output directory (default: ./backups)
      --keep-days <days>    Remove .sql.gz backups older than this amount (default: 30)
      --print-path          Print only the generated backup path (for automation)
  -h, --help                Show this help
EOF
}

compose() {
  ${COMPOSE_CMD} "$@"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -o|--output-dir)
      BACKUP_DIR="$2"
      shift 2
      ;;
    --keep-days)
      KEEP_DAYS="$2"
      shift 2
      ;;
    --print-path)
      PRINT_PATH_ONLY=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  set -a
  source "${ENV_FILE}"
  set +a
fi

DB_USER="${DB_USER:-dotproject}"
DB_PASSWORD="${DB_PASSWORD:-dotproject123}"
DB_NAME="${DB_NAME:-dotproject}"

mkdir -p "${BACKUP_DIR}"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="${BACKUP_DIR}/db_${DB_NAME}_${timestamp}.sql.gz"
checksum_file="${backup_file}.sha256"

compose exec -T -e MYSQL_PWD="${DB_PASSWORD}" "${DB_SERVICE}" sh -lc \
  "mariadb-dump --single-transaction --quick --routines --events --triggers -u \"${DB_USER}\" \"${DB_NAME}\"" \
  | gzip -9 > "${backup_file}"

if command -v sha256sum >/dev/null 2>&1; then
  sha256sum "${backup_file}" > "${checksum_file}"
else
  shasum -a 256 "${backup_file}" > "${checksum_file}"
fi

find "${BACKUP_DIR}" -maxdepth 1 -type f -name "db_${DB_NAME}_*.sql.gz" -mtime +"${KEEP_DAYS}" -delete || true
find "${BACKUP_DIR}" -maxdepth 1 -type f -name "db_${DB_NAME}_*.sql.gz.sha256" -mtime +"${KEEP_DAYS}" -delete || true

if [[ "${PRINT_PATH_ONLY}" -eq 1 ]]; then
  echo "${backup_file}"
  exit 0
fi

echo "Backup completed."
echo "Backup file: ${backup_file}"
echo "Checksum: ${checksum_file}"
