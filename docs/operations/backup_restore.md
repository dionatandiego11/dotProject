# Backup and Restore Runbook

This runbook defines a minimal and repeatable backup/restore process for the local Docker stack.

## Scope

- Database service: `mariadb` from `docker-compose.yml`
- Default database: `dotproject` (from `.env`)
- Backup format: compressed SQL dump (`.sql.gz`) with SHA-256 checksum

## RPO / RTO (initial target)

- `RPO`: up to 24 hours (at least one backup per day)
- `RTO`: up to 2 hours (restore + smoke validation)

These targets are initial operational baselines and should be tightened after production telemetry.

## Scripts

- `scripts/ops/backup_db.sh`
- `scripts/ops/restore_db.sh`
- `scripts/ops/verify_backup_restore.sh`

## Prerequisites

1. Docker stack running (`mariadb` healthy):
```bash
docker compose ps
```
2. `.env` present with `DB_USER`, `DB_PASSWORD`, `DB_NAME`.

## 1) Create backup

```bash
bash scripts/ops/backup_db.sh
```

Options:

```bash
bash scripts/ops/backup_db.sh --output-dir ./backups/manual --keep-days 15
```

Output:

- `backups/db_<db_name>_<timestamp>.sql.gz`
- `backups/db_<db_name>_<timestamp>.sql.gz.sha256`

## 2) Verify restore (non-destructive)

This command restores into a temporary database, runs smoke checks, and drops the temporary database.

```bash
bash scripts/ops/verify_backup_restore.sh
```

You can also verify a specific dump:

```bash
bash scripts/ops/verify_backup_restore.sh ./backups/db_dotproject_YYYYMMDDTHHMMSSZ.sql.gz
```

## 3) Restore database (destructive)

Restore overwrites the target database.

```bash
bash scripts/ops/restore_db.sh --file ./backups/db_dotproject_YYYYMMDDTHHMMSSZ.sql.gz
```

Non-interactive mode:

```bash
bash scripts/ops/restore_db.sh --file ./backups/db_dotproject_YYYYMMDDTHHMMSSZ.sql.gz --yes
```

By default, restore creates a safety backup in `backups/pre-restore/` before importing.

## Automation example (Linux cron)

Daily backup at 02:30:

```bash
30 2 * * * cd /path/to/dotProject && bash scripts/ops/backup_db.sh >> /var/log/dotproject-backup.log 2>&1
```

Weekly restore verification at 03:00 Sunday:

```bash
0 3 * * 0 cd /path/to/dotProject && bash scripts/ops/verify_backup_restore.sh >> /var/log/dotproject-restore-verify.log 2>&1
```

## Incident checklist

1. Confirm latest valid backup (`.sql.gz` + checksum).
2. Run non-destructive verification (`verify_backup_restore.sh`) on selected backup.
3. Notify stakeholders and open incident timeline.
4. Execute restore in maintenance window.
5. Run post-restore smoke tests:
   - login
   - dashboard load
   - project list
   - kanban board
6. Register evidence (timestamps, operator, backup file, validation result).
