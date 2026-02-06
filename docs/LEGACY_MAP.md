# Legacy Map (arquivo por arquivo - agrupado)

Este mapa indica o que esta em uso direto (frontend + API) e o que permanece
como legado (PHP procedural/modulos). Onde o uso depende de acesso via UI
legada (index.php), marco como "legado ativo".

## Entrypoints
- `api.php` -> USADO (API REST atual).
- `index.php` -> LEGADO ATIVO (UI antiga; nao usada pelo React).
- `fileviewer.php` -> LEGADO ATIVO (usado por modules/files quando UI legado).

## Frontend (React) - USADO
- `frontend/src/main.jsx`
- `frontend/src/App.jsx`
- `frontend/src/pages/*` (Projects, Tasks, Kanban, Admin, Dashboard)
- `frontend/src/components/*`
- `frontend/src/services/api.js`

## API moderna (PHP) - USADO
- `src/Api/*` (Router, Controllers, Middleware)
- `src/Service/*` (PermissionService, Project/Task/User services)
- `src/Repository/*`
- `src/Entity/*`
- `src/Core/*`

## Config e bootstrap - USADO
- `base.php`
- `bootstrap.php`
- `includes/config.php`
- `includes/db_connect.php`
- `includes/db_adodb.php`
- `includes/dP_compat.php`

## Infra - USADO
- `docker-compose.yml`
- `.docker-compose/*`
- `Dockerfile`
- `.env`, `.env.example`

## Database scripts - USADO (migrations/seed/structure)
- `db/*` (migrations, schema, seeds)

## Modulos legado (PHP) - LEGADO ATIVO
Estes ficam ativos somente se a UI antiga (index.php) for usada:
- `modules/projects/*`
- `modules/tasks/*`
- `modules/calendar/*`
- `modules/files/*`
- `modules/companies/*`
- `modules/admin/*`
- `modules/system/*`
- `modules/forums/*`
- `modules/resources/*`
- `modules/risks/*`
- `modules/scope_and_schedule/*`
- `modules/smartsearch/*`
- `classes/*`
- `functions/*`
- `includes/*` auxiliares (permissions, session, main_functions)
- `lib/*` (adodb, phpgacl, htmlpurifier, etc.)

## Legado removido nesta limpeza
- `_deprecated_modules/`
- `modules/ticketsmith/`
- `install/`
- `style/modern/`, `style/modern_hybrid/`, `style/dp-grey-theme/`
- `frontend/src/AppMinimal.jsx`, `frontend/src/AppSimple.jsx`
- `frontend/src/main-minimal.jsx`, `frontend/src/main-backup.jsx`
- `frontend/public/debug.html`, `frontend/public/test.html`
- `frontend/src/services/apiTEmp.js`
- `test_*.php` (root)

## Observacoes
- Se a UI antiga nao for usada, a maior parte de `modules/*`, `classes/*` e
  `functions/*` fica "encostada" (mas removida com risco).
- A API moderna e o frontend React compoem o fluxo principal atual.
