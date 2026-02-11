# dotProject Prefeituras - Estado Atual

Sistema de gestao publica municipal com API REST (PHP 8.2) e frontend React 18.
Foco: estrutura organizacional, projetos, tarefas e dashboards por perfil.

## Estado atual (resumo)
- Estrutura organizacional funcional (niveis, unidades, organograma).
- Usuarios com login/senha e vinculo a unidade.
- Projetos e tarefas integrados por unidade.
- Kanban integrado com tarefas e responsaveis por unidade.
- Controle de visibilidade por hierarquia (prefeito, secretario, chefe, tecnico).

## Regras de visibilidade (hierarquia)
- Prefeito (responsavel da raiz): ve tudo.
- Secretario (responsavel da secretaria): ve secretaria + subordinadas.
- Chefe (responsavel da unidade): ve apenas sua unidade.
- Tecnico/Analista: ve apenas sua unidade (dashboard simples).

## Funcionalidades principais
- Estrutura: cadastro de unidades, responsaveis e organograma.
- Usuarios: cadastro completo com login/senha, vinculo e papel na unidade.
- Projetos: CRUD, unidade responsavel obrigatoria.
- Tarefas: CRUD, Kanban, responsavel por unidade.
- Dashboards: por perfil, com dados filtrados por escopo.

## URLs (ambiente local)
- Frontend: http://localhost:5173
- API: http://localhost:8088/api/v1
- phpMyAdmin: http://localhost:8081 (se habilitado)

## Quick Start (Windows + WSL)
1) Copie `.env.example` para `.env`
2) Suba os containers:
   - `wsl docker-compose up -d`
3) (Opcional) Frontend:
   - `wsl bash -c "cd /mnt/c/Users/dionatan.resende/Downloads/dotProject/frontend && npm run dev"`

## Estrutura (alto nivel)
- `frontend/` React app
- `src/` API moderna (controllers, services, repositories)
- `classes/` e `includes/` legado residual (compatibilidade)
- `db/` scripts SQL e migrations
- `docs/` documentacao (API e troubleshooting)
- `.agent/` guias internos do time

## Documentacao
- `docs/API.md`
- `docs/api/dashboards.md`
- `docs/TROUBLESHOOTING.md`
- `docs/operations/backup_restore.md`

## Licenca
GPL v2.0 (ver `COPYING`)
