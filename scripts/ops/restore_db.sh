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
BACKUP_FILE=""
AUTO_CONFIRM=0
SKIP_PRE_BACKUP=0

usage() {
  cat <<'EOF'
Usage: restore_db.sh [options] --file <backup.sql|backup.sql.gz>

Options:
  -f, --file <path>         Backup file to restore (.sql or .sql.gz)
  -y, --yes                 Skip confirmation prompt
      --skip-pre-backup     Do not create a safety backup before restore
  -h, --help                Show this help
EOF
}

compose() {
  ${COMPOSE_CMD} "$@"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -f|--file)
      BACKUP_FILE="$2"
      shift 2
      ;;
    -y|--yes)
      AUTO_CONFIRM=1
      shift
      ;;
    --skip-pre-backup)
      SKIP_PRE_BACKUP=1
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

if [[ -z "${BACKUP_FILE}" ]]; then
  echo "Missing --file argument." >&2
  usage
  exit 1
fi

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "Backup file not found: ${BACKUP_FILE}" >&2
  exit 1
fi

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  set -a
  source "${ENV_FILE}"
  set +a
fi

DB_USER="${DB_USER:-dotproject}"
DB_PASSWORD="${DB_PASSWORD:-dotproject123}"
DB_NAME="${DB_NAME:-dotproject}"

if [[ "${AUTO_CONFIRM}" -ne 1 ]]; then
  echo "This operation will overwrite database '${DB_NAME}'."
  read -r -p "Continue? [y/N] " answer
  if [[ ! "${answer}" =~ ^[Yy]$ ]]; then
    echo "Restore cancelled."
    exit 1
  fi
fi

if [[ "${SKIP_PRE_BACKUP}" -ne 1 ]]; then
  echo "Creating safety backup before restore..."
  "${SCRIPT_DIR}/backup_db.sh" --output-dir "${REPO_ROOT}/backups/pre-restore" >/dev/null
fi

if [[ "${BACKUP_FILE}" == *.gz ]]; then
  gzip -dc "${BACKUP_FILE}" | compose exec -T -e MYSQL_PWD="${DB_PASSWORD}" "${DB_SERVICE}" sh -lc \
    "mariadb -u \"${DB_USER}\" \"${DB_NAME}\""
else
  cat "${BACKUP_FILE}" | compose exec -T -e MYSQL_PWD="${DB_PASSWORD}" "${DB_SERVICE}" sh -lc \
    "mariadb -u \"${DB_USER}\" \"${DB_NAME}\""
fi

echo "Restore completed from: ${BACKUP_FILE}"
