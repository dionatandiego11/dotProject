# Guia de Progresso e Plano de Acao

Data de referencia: 2026-02-06
Branch alvo: `devel`

## Objetivo
Consolidar o que foi estabilizado no sistema e definir o proximo plano de execucao com entregas pequenas, verificaveis e com baixo risco de regressao.

## Progresso consolidado

### Estado atual validado
- API, regras de unidade e fluxo de kanban estabilizados em ambiente WSL + Docker.
- Suite de testes passando no container `phpfpm`.
- Resultado da ultima execucao: `OK (260 tests, 483 assertions)`.

### Checkpoint 2026-02-09
- Kanban padronizado para contrato canonico: `unidade_id` como campo oficial e `company_id` como compatibilidade.
- Validacoes de escopo no Kanban passaram a expor erros em ambos os campos (`unidade_id` e `company_id`).
- Cobertura de integracao reforcada para garantir payload canonico em criacao e leitura de boards.
- Matriz de contrato documentada em `docs/api/unidade_company_contract.md`.

### Entregas recentes (mais relevantes)
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

## Comandos de verificacao (WSL)
```bash
wsl docker-compose ps
wsl docker-compose exec -T phpfpm vendor/bin/phpunit
```

## Referencias
- `.agent/roadmap.md`
- `.agent/sprint_board.md`
- `.agent/forum_agentes.md`
