# Guia de Progresso e Plano de Acao

Data de referencia: 2026-02-09
Branch alvo: `devel`

## Objetivo
Consolidar o que foi estabilizado no sistema e definir o proximo plano de execucao com entregas pequenas, verificaveis e com baixo risco de regressao.

## Progresso consolidado

### Estado atual validado
- API, regras de unidade e fluxo de kanban estabilizados em ambiente WSL + Docker.
- Suite de testes passando no container `phpfpm`.
- Resultado da ultima execucao: `OK (276 tests, 610 assertions)`.

### Checkpoint 2026-02-09
- Kanban padronizado para contrato canonico: `unidade_id` como campo oficial e `company_id` como compatibilidade.
- Validacoes de escopo no Kanban passaram a expor erros em ambos os campos (`unidade_id` e `company_id`).
- Cobertura de integracao reforcada para garantir payload canonico em criacao e leitura de boards.
- Matriz de contrato documentada em `docs/api/unidade_company_contract.md`.

### Checkpoint 2026-02-09 (cache/invalidacao)
- Padronizacao de cache keys de repositorio com namespace normalizado (substituindo `\` por `.`) para garantir matching correto em wildcard.
- `NotificationRepository` migrado para chave canonica (`cacheKey(...)`) e invalidacao via `clearCache()` em operacoes de escrita.
- Escritas em `ProjectRepository`, `UserRepository`, `TaskRepository` e `KanbanTaskRepository` passaram a invalidar cache de repositorio completo para evitar stale em `findBy/count`.
- Cobertura de integracao ampliada com testes de stale cache para notificacoes e atualizacao de percentual de projeto.
- Contrato de cache documentado em `docs/api/repository_cache_contract.md`.

### Checkpoint 2026-02-09 (migracao incremental de legado)
- Nova migracao `db/migrations/20260209_harden_notifications_integrity.sql` para normalizar nulls legados em notificacoes e endurecer colunas criticas (`notification_is_read`, `notification_is_sent`, `notification_channel`, `notification_created_at`).
- Novos indices compostos aplicados para padroes de consulta reais do repositÃ³rio (`countUnread`, `findPending`, `getStats`).
- Script de verificacao `db/migrations/20260209_verify_notifications_integrity.sql` adicionado e validado com resultado esperado (todos checks `0/1` corretos).

### Checkpoint 2026-02-09 (kanban projeto com schema legado)
- Corrigida regressao de Kanban vazio por projeto causada por consulta incompativel com schema legado (`task_assigned_to`/nome em `dotp_users`).
- `KanbanService::getTasksByColumn()` passou a usar fallback por deteccao de coluna e join com `dotp_contacts` para nome de responsavel.
- `KanbanController` passou a resolver unidade do projeto (`project_company`) ao listar/criar boards com `project_id`, evitando mismatch com `user_company`.
- Frontend (`frontend/src/pages/Kanban.jsx`) passou a enviar `unidade_id` do projeto ao criar board.
- Cobertura de integracao ampliada para garantir que board de projeto retorna tarefas no schema legado.

### Checkpoint 2026-02-09 (kanban -> dashboard analytics)
- Fluxo de drag-and-drop no Kanban passou a sincronizar `status` e `percent_complete` da tarefa em toda mudanca de coluna no frontend.
- `KanbanService` passou a invalidar tambem o cache com prefixo `analytics:` (dashboard/produtividade/trend), eliminando stale no card de concluidas.
- Cobertura de integracao ampliada no `KanbanFlowIntegrationTest` com cenario especifico de cache aquecido para garantir reflexo imediato da tarefa concluida no dashboard.

### Checkpoint 2026-02-09 (modernizacao de atribuicao de tarefas)
- Nova migracao `db/migrations/20260209_add_task_assigned_to.sql` para alinhar schema legado de tarefas com servicos/repositorios modernos.
- Script de verificacao `db/migrations/20260209_verify_task_assigned_to.sql` adicionado e validado com resultado esperado.
- `TaskController` atualizado para aceitar `assigned_to/assigned_to_id`, manter fallback para `owner_id` e operar com compatibilidade quando a coluna ainda nao existe.
- `TaskRepository` endurecido para fallback automatico de assignee em bases nao migradas (`task_owner`) e uso canÃ´nico de `task_assigned_to` quando disponivel.
- Cobertura de integracao ampliada para validar retorno de `assigned_to` com fallback para `owner`.

### Checkpoint 2026-02-09 (integridade de progresso de tarefas)
- Nova migracao `db/migrations/20260209_harden_tasks_progress_integrity.sql` para normalizar legado em `task_status`/`task_percent_complete` e adicionar indices compostos de consulta.
- Script de verificacao `db/migrations/20260209_verify_tasks_progress_integrity.sql` adicionado e validado com resultado esperado (todos checks de consistencia em `0` e indices em `1`).
- `TaskController` passou a sincronizar automaticamente `percent_complete` quando o `status` e alterado sem percentual explicito (ex.: `status=3 -> percent=100`, `status=0 -> percent=0`).
- Cobertura de integracao ampliada para criacao/edicao de tarefa garantindo coerencia de status/progresso sem depender do frontend.

### Checkpoint 2026-02-09 (integridade de progresso de projetos)
- Nova migracao `db/migrations/20260209_harden_projects_progress_integrity.sql` para normalizar legado em `project_status`/`project_percent_complete` e adicionar indices compostos de listagem/analytics.
- Script de verificacao `db/migrations/20260209_verify_projects_progress_integrity.sql` adicionado e validado com resultado esperado (todos checks de consistencia em `0` e indices em `1`).
- `ProjectController` passou a sincronizar automaticamente percentual com status canonico em criacao/edicao (`status=5 -> percent=100`, `status=0 -> percent=0`), e a aceitar `percent_complete` em `update`.
- Validacao de `ProjectController::update` foi corrigida para modo parcial (evitando regressao de exigir campos de criacao em updates parciais).
- Cobertura de integracao ampliada para criacao/edicao de projeto garantindo coerencia de status/progresso no backend.

### Checkpoint 2026-02-09 (escopo de unidade + fluxo de tarefas)
- Corrigido erro `Validation failed` no drag-and-drop do Kanban: update parcial de tarefa (`status`/`percent_complete`) agora valida com fallback dos dados atuais (`task_name` e `task_project`), sem exigir payload de criacao.
- Frontend de projetos (`frontend/src/pages/Projects.jsx`) passou a buscar unidades com `escopo=1`.
- Backend de unidades (`AdminController::listUnidades`) passou a aplicar filtro por escopo quando `?escopo=1`.
- Backend de projetos (`ProjectController`) passou a bloquear create/update fora da `unidades_escopo` do usuario autenticado.
- Sincronizacao estrutural de vinculos:
  - `AdminController` sincroniza `dotp_users.user_company` apos criar/atualizar/desativar vinculo e apos definir principal;
  - `UserService::createUser` passou a retornar `data.id` persistido, evitando quebra no fluxo `createUsuario -> createVinculo`.
- Novas migracoes pequenas:
  - `db/migrations/20260209_backfill_user_company_from_vinculo_principal.sql`
  - `db/migrations/20260209_verify_user_company_from_vinculo_principal.sql`
  - verificado com `divergencias = 0`.
- Cobertura de testes ampliada:
  - `CriticalFlowsIntegrationTest`: update parcial de tarefa sem regressao de validacao;
  - `AdminUserDeletionIntegrationTest`: garantia de retorno de `data.id` no create de usuario.

### Checkpoint 2026-02-10 (macrostatus automatico e exclusao segura de projeto)
- `ProjectProgressSyncService` passou a concluir automaticamente o projeto (`project_status=5`) quando todas as tarefas estiverem em estado terminal (`Done/Cancelado/Arquivado`) e a reabrir para `Em progresso` quando houver novas tarefas nao concluidas.
- Sincronizacao de progresso passou a tratar tarefas terminais como `100%` no calculo de `project_percent_complete`, evitando projetos travados em `Em progresso` com tarefas canceladas/arquivadas.
- Transicoes automaticas de macrostatus agora registram auditoria em `dotp_project_status_history` com origem (`kanban_move`, `task_controller` etc.).
- Fluxo de exclusao de projeto endurecido:
  - bloqueia exclusao apenas se existir tarefa ativa;
  - permite exclusao quando restarem apenas tarefas terminais;
  - executa purge transacional de artefatos legados de tarefas (`task_log`, `task_contacts`, `task_departments`, `user_tasks`, `task_dependencies`) antes de remover o projeto.
- Cobertura de integracao ampliada:
  - `KanbanFlowIntegrationTest`: garante `project_status=5` no fim de `Backlog -> To Do -> In Progress -> Done`.
  - `CriticalFlowsIntegrationTest`: garante sincronizacao correta com tarefas `Cancelado/Arquivado` e valida regras de exclusao de projeto com/sem tarefas ativas.

### Status de iteracoes (2026-02-09)
- Iteracao 1 (`unidade` x `company`): concluida no escopo P0 critico.
- Iteracao 2 (cache/invalidacao): concluida no escopo P0 critico.
- Iteracao 3 (migracoes pequenas): em andamento, com pacotes de `notifications`, `task_assigned_to`, `tasks/projects progress` e `user_company backfill` entregues e validados.

### Entregas recentes (mais relevantes)
- `6f142d02` restauracao do kanban por projeto em schema legado + alinhamento de unidade no board.
- `7575970e` hardening de integridade em notifications com migration verificavel.
- `972d3cbd` padronizacao de cache keys de repositorio e invalidacao de escrita.
- `bc679ce8` contrato canonico de unidade no kanban + documentacao de mapeamento.
- `530c9756` sincronizacao de `companies` com `unidades` + script de verificacao.
- `802120d3` backfill de `dotp_users.user_company` e normalizacao de `board_company`.
- `90324614` resolucao de unidade do kanban via vinculo antes da criacao do board.
- `2b3397c1` integridade de `dotp_users.user_company` com FK nullable.
- `5b3d3960` compatibilidade de schema no `UsuarioUnidadeRepository`.
- `af22b3e9` compatibilidade de schema no `KpiCalculationService`.
- `085a1514` hardening em autorizacao (roles legadas + escopo por vinculos).
- `0b13ba72` hardening do `ProjetoController` + normalizacao de estados legados no `StateFactory`.

### Melhoria estrutural importante do ciclo atual
- `StateFactory` agora resolve nomes de estado em `snake_case`, `kebab-case` e espaco para `PascalCase`.
- Estados legados faltantes foram adicionados para evitar erro 500 por classe inexistente.
- `ProjetoController` corrigido para fluxo REST consistente e validacao de autenticacao/permissao.

## Situacao de risco (atual)

### Risco baixo
- Fluxos criticos com cobertura de integracao: projetos, kanban, unidades, autorizacao, KPI.
- Alinhamento entre legado e moderno melhorado por compatibilidade de schema.

### Risco medio
- Base ainda possui heranca de legado com nomenclaturas historicas (`company` vs `unidade`).
- Ainda ha pontos com cache sem estrategia unica de invalidacao.
- Nem todo fluxo legado esta coberto por testes de integracao end-to-end.

## Plano de acao (proximas iteracoes)

### Iteracao 1 - Governanca de dados `unidade` x `company` (P0)
1. Definir contrato oficial por endpoint (request/response/persistencia).
2. Criar matriz de mapeamento obrigatoria em `docs/` para evitar dupla interpretacao.
3. Adicionar testes de contrato para endpoints mais usados.
4. Bloquear novos usos ambiguos com validacao central.

Criterio de pronto:
1. Endpoints criticos documentados e testados.
2. Nenhum endpoint critico retornando campo ambiguo sem mapeamento explicito.

### Iteracao 2 - Padrao de cache e invalidacao (P0)
1. Inventariar keys de cache por repositorio/service.
2. Padronizar formato de key e TTL por dominio.
3. Criar pontos de invalidacao por evento de escrita.
4. Adicionar testes de integracao para cenarios de stale data.

Criterio de pronto:
1. Keys e invalidacao documentadas.
2. Cenarios principais com assert de cache hit/miss corretos.

### Iteracao 3 - Reducao de divida legado com migracoes pequenas (P1)
1. Selecionar 1 migracao por vez com rollback claro.
2. Priorizar colunas/tabelas com maior incidencia em bugs recentes.
3. Executar script de verificacao apos cada migracao.
4. Validar com `phpunit` completo antes de merge.

Criterio de pronto:
1. Migracao aplicada e verificada.
2. Sem regressao na suite.

## Backlog priorizado

### P0
- Contrato canonico de `unidade` x `company`.
- Padronizacao de cache/invalidacao.
- Cobertura de integracao para endpoints com maior volume.

### P1
- Pacotes de migracao incrementais com script de verificacao por pacote.
- Refino de observabilidade para reduzir tempo de diagnostico.

### P2
- Limpeza adicional de legado nao utilizado.
- Consolidacao de documentacao tecnica dispersa.

## Ritual de execucao recomendado
1. Implementar 1 bloco pequeno por vez.
2. Rodar testes focados e depois suite completa.
3. Commit atomico com mensagem objetiva.
4. `git push origin devel` ao fim de cada bloco validado.

## Proximo dia (retomada objetiva)
1. Validar fluxo manual com usuario de unidade nova:
   - criar usuario + vinculo principal;
   - confirmar dashboard sem classificacao indevida em "Sem Secretaria";
   - criar projeto e confirmar select limitado ao escopo.
2. Validar Kanban fim-a-fim no mesmo cenario:
   - criar tarefa com datas;
   - mover `Backlog -> To Do -> In Progress -> Done`;
   - confirmar persistencia de datas e reflexo no Dashboard.
3. Se o fluxo estiver estavel, fechar pacote com commit unico e registrar hash neste guia.

## Comandos de verificacao (WSL)
```bash
wsl docker-compose ps
wsl docker-compose exec -T phpfpm vendor/bin/phpunit
```

## Referencias
- `.agent/roadmap.md`
- `.agent/sprint_board.md`
- `.agent/forum_agentes.md`
