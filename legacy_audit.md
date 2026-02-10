# Legacy Audit - `classes/` and `lib/`

Date: 2026-02-10

## Scope

- `classes/*.php`
- `lib/*`

## Method

Static reference audit using repository-wide search with these exclusions:

- `.git/`
- `vendor/`
- `node_modules/`
- `frontend/`
- `docs/`
- `.agent/`
- `tests/`
- `ChangeLog`

Rules used:

1. `runtime_refs > 0`: treated as active or potentially active.
2. `runtime_refs = 0`: treated as candidate for quarantine (not direct delete).
3. Dynamic loads and classmap autoload are treated as risk factors (manual review required).

## Findings - `classes/`

### Clearly referenced in runtime paths

- `classes/csscolor.class.php`
  Evidence: `api.php`, `scripts/cleanup_organograma.php`, `scripts/import_organograma.php`
- `classes/event_queue.class.php`
  Evidence: `includes/session.php`, `queuescanner.php`
- `classes/query.class.php`
  Evidence: `includes/session.php`, `queuescanner.php`
- `classes/permissions.class.php`
  Evidence: `classes/ui.class.php`, `db/upgrade_latest.php`, `db/upgrade_permissions.php`
- `classes/ui.class.php`
  Evidence: `includes/session.php`, `queuescanner.php`
- `classes/authenticator.class.php`
  Evidence: required from `classes/ui.class.php`
- `classes/contacts.class.php`
  Evidence: class `CContact` instantiated in `classes/authenticator.class.php`
- `classes/dp.class.php`
  Evidence: required by `classes/contacts.class.php` via `$AppUI->getSystemClass('dp')`
- `classes/date.class.php`
  Evidence: `CDate` used in `includes/db_connect.php`

### Candidates for quarantine (no runtime refs found in current code paths)

- `classes/CustomFields.class.php`
- `classes/customfieldsparser.class.php`
- `classes/tree.class.php`
- `classes/libmail.class.php`

Recommendation: quarantine first, do not hard-delete in first pass.

## Findings - `lib/` (by package)

### Active / referenced

- `lib/adodb` (used by `classes/query.class.php`, `classes/permissions.class.php`)
- `lib/htmlpurifier-standalone` (used by `includes/main_functions.php`)
- `lib/phpgacl` (used by `classes/permissions.class.php`)
- `lib/overlib` (referenced in `classes/ui.class.php`)
- `lib/fonts` (referenced by `lib/jpgraph/src/jpg-config.inc.php`)
- `lib/smarty` (referenced by `lib/phpgacl/admin/gacl_admin.inc.php`)
- `lib/PEAR` (no direct `lib/PEAR` path refs, but required dynamically by `classes/date.class.php` via `$AppUI->getLibraryClass('PEAR/Date')`)

### Candidates for quarantine (no runtime refs outside package)

- `lib/calendar`
- `lib/jpgraph`
- `lib/quilljs`
- `lib/ezpdf` (no runtime evidence; only `.gitignore`/history traces)

## Safe Removal Plan

1. Move candidates to quarantine folder (example: `_legacy_quarantine/2026-02-10/`).
2. Run backend tests:
   - `docker exec dotproject-phpfpm-1 php vendor/bin/phpunit`
3. Run route smoke checks (`/api.php/v1/...`) to ensure no `404`.
4. Validate key frontend flows (`/`, `/projects`, `/kanban`, `/admin/unidades`).
5. Keep quarantine for one release cycle before permanent deletion.

## Status for this cycle

- Refatoração de controllers (Admin/Dashboard): done.
- Frontend services split + shared user state service/hook: done.
- Legacy audit and candidates map: done in this document.
- Deletion: not executed (intentionally pending quarantine cycle).
