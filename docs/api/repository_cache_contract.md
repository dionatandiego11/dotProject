# Repository Cache Contract

## Objetivo
Padronizar estrategia de chave e invalidacao de cache em repositorios baseados em `BaseRepository`.

## Regra canonica
1. Toda chave de repositorio deve ser gerada por `BaseRepository::cacheKey(...)`.
2. Toda operacao de escrita (`save`, `delete`, `update*`, `mark*`, `move*`, `reorder*`) deve invalidar com `clearCache()`.
3. Chaves legacy fora do namespace do repositorio devem ser evitadas.

## Formato de chave
`<classe_repositorio>:<tabela>:<sufixo>`

Exemplos:
1. `DotProject.Repository.ProjectRepository:dotp_projects:find:123`
2. `DotProject.Repository.NotificationRepository:dotp_notifications:countUnread:1`
3. `DotProject.Repository.TaskRepository:dotp_tasks:findBy:<hash>`

## Compatibilidade e legado
1. Invalidacoes adicionais por dominio (ex.: `kanban:column:*`) podem coexistir.
2. Mesmo com invalidacao adicional, `clearCache()` continua obrigatorio para garantir consistencia de `find`, `findBy`, `findAll` e `count`.

## Escopo aplicado no ciclo atual
1. `NotificationRepository`
2. `ProjectRepository`
3. `UserRepository`
4. `TaskRepository`
5. `KanbanTaskRepository`
