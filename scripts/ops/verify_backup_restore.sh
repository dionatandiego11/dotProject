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
DB_ADMIN_USER="${DB_ADMIN_USER:-root}"
DB_ADMIN_PASSWORD="${DB_ADMIN_PASSWORD:-}"
BACKUP_FILE="${1:-}"
GENERATED_BACKUP=0
TEMP_DB=""

usage() {
  cat <<'EOF'
Usage: verify_backup_restore.sh [backup.sql|backup.sql.gz]

If no backup file is provided, the script creates a fresh backup first.
The restore is performed into a temporary database and then dropped.
EOF
}

compose() {
  ${COMPOSE_CMD} "$@"
}

detect_root_password() {
  compose exec -T "${DB_SERVICE}" sh -lc 'printf "%s" "${MYSQL_ROOT_PASSWORD:-}"'
}

cleanup() {
  if [[ -n "${TEMP_DB}" ]]; then
    compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
      "mariadb -u \"${DB_ADMIN_USER}\" -e \"DROP DATABASE IF EXISTS \\\`${TEMP_DB}\\\`;\"" >/dev/null 2>&1 || true
  fi
}

if [[ "${BACKUP_FILE}" == "-h" || "${BACKUP_FILE}" == "--help" ]]; then
  usage
  exit 0
fi

trap cleanup EXIT

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  set -a
  source "${ENV_FILE}"
  set +a
fi

DB_USER="${DB_USER:-dotproject}"
DB_PASSWORD="${DB_PASSWORD:-dotproject123}"
DB_NAME="${DB_NAME:-dotproject}"

if [[ -z "${DB_ADMIN_PASSWORD}" && "${DB_ADMIN_USER}" == "root" ]]; then
  DB_ADMIN_PASSWORD="$(detect_root_password)"
fi

if [[ -z "${DB_ADMIN_PASSWORD}" ]]; then
  DB_ADMIN_USER="${DB_USER}"
  DB_ADMIN_PASSWORD="${DB_PASSWORD}"
fi

if [[ -z "${BACKUP_FILE}" ]]; then
  BACKUP_FILE="$("${SCRIPT_DIR}/backup_db.sh" --output-dir "${REPO_ROOT}/backups/verify" --print-path)"
  GENERATED_BACKUP=1
fi

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "Backup file not found: ${BACKUP_FILE}" >&2
  exit 1
fi

TEMP_DB="${DB_NAME}_restore_verify_$(date +%s)"

compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
  "mariadb -u \"${DB_ADMIN_USER}\" -e \"CREATE DATABASE \\\`${TEMP_DB}\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""

if [[ "${BACKUP_FILE}" == *.gz ]]; then
  gzip -dc "${BACKUP_FILE}" | compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
    "mariadb -u \"${DB_ADMIN_USER}\" \"${TEMP_DB}\""
else
  cat "${BACKUP_FILE}" | compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
    "mariadb -u \"${DB_ADMIN_USER}\" \"${TEMP_DB}\""
fi

table_count="$(compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
  "mariadb -N -u \"${DB_ADMIN_USER}\" -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TEMP_DB}';\"")"

required_count="$(compose exec -T -e MYSQL_PWD="${DB_ADMIN_PASSWORD}" "${DB_SERVICE}" sh -lc \
  "mariadb -N -u \"${DB_ADMIN_USER}\" -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TEMP_DB}' AND table_name IN ('dotp_users','dotp_projects','dotp_tasks');\"")"

if [[ "${table_count}" -le 0 ]]; then
  echo "Verification failed: restored database has no tables." >&2
  exit 1
fi

if [[ "${required_count}" -ne 3 ]]; then
  echo "Verification failed: required tables not found in restored database." >&2
  exit 1
fi

echo "Backup/restore verification passed."
echo "Source backup: ${BACKUP_FILE}"
echo "Temporary database restored and validated: ${TEMP_DB}"

if [[ "${GENERATED_BACKUP}" -eq 1 ]]; then
  echo "Backup was generated automatically for this verification run."
fi
