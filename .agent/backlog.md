# 📋 Backlog de Tarefas - dotProject Prefeituras

## Legenda
- `[ANTIGRAVITY]` - Tarefa para Gemini/Antigravity
- `[KIMI]` - Tarefa para Kimi (debug/funcionamento)
- `[CHATGPT]` - Tarefa para ChatGPT (docs/review)
- `[ ]` Pendente | `[/]` Em andamento | `[x]` Concluído

---

## Sprint Atual

### 🔴 Crítico (Fazer Funcionar)

- [x] **[KIMI]** Corrigir PermissionService (cast int)
  - ✅ Aplicado em 02/02/2026
  - ✅ Corrigido erro de tipo string vs int
  
- [x] **[KIMI]** Corrigir Dashboard Coordenador
  - ✅ Funcionando após correção do PermissionService
  
- [x] **[KIMI]** Corrigir Dashboard Técnico
  - ✅ Funcionando após correção do PermissionService

- [/] **[CHATGPT]** Corrigir Dashboard Secretário
  - 🔄 ChatGPT investigando (03/02)
  - Erro 500 com resposta vazia
  - Try-catch adicionado mas erro não capturado
  - Possível causa: middleware ou banco de dados
  - Handoff: `.agent/handoff/to_chatgpt_correcoes.md`
  - **Atualização**: Aguardando logs detalhados do ChatGPT

- [ ] **[CHATGPT]** Corrigir NotificationService
  - Método `getUnreadCount()` não existe
  - Logger::getInstance() pode ter problema
  - Handoff: `.agent/handoff/to_chatgpt_correcoes.md`

---

### 🟡 Importante (Novas Features)

- [ ] **[ANTIGRAVITY]** Formulário de cadastro de níveis hierárquicos
  - Path: `/admin/niveis/novo`
  - Campos: nome, ordem, descrição
  - Handoff: `.agent/handoff/to_antigravity_formularios.md`

- [ ] **[ANTIGRAVITY]** Formulário de cadastro de unidades
  - Path: `/admin/unidades/nova`
  - Campos: nome, sigla, nível, unidade pai
  - Seletor de unidade pai em árvore
  - Handoff: `.agent/handoff/to_antigravity_formularios.md`

- [x] **[ANTIGRAVITY]** Timeline visual de projetos
  - ✅ Implementado em `components/Timeline.jsx`
  - ✅ Integrado em `pages/ProjectDetails.jsx`
  - ✅ Rota `/projects/:id` criada
  - ✅ Lista de projetos redireciona para detalhes

---

### 🟢 Melhoria (Qualidade)

- [ ] **[CHATGPT]** Documentar correções realizadas
  - Criar `.agent/docs/CORRECOES.md`
  - Documentar fixes de PermissionService
  - Documentar fixes de dashboards
  - Handoff: `.agent/handoff/to_chatgpt_correcoes.md`

- [ ] **[CHATGPT]** Code review dos componentes
  - Revisar DashboardPrefeito.jsx
  - Sugerir melhorias de performance
  - Verificar acessibilidade

- [ ] **[CHATGPT]** Revisar acessibilidade dos dashboards
  - Contraste de cores
  - Labels ARIA
  - Navegação por teclado

---

## Próximas Sprints

### Sprint 2 - Gestão de Projetos
- [ ] Tela de listagem de programas
- [ ] Tela de detalhes do programa
- [ ] Painel de emendas parlamentares
- [ ] Painel de convênios

### Sprint 3 - Relatórios
- [ ] Relatório de execução do PPA
- [ ] Relatório de emendas
- [ ] Exportação para PDF
- [ ] Dashboard de transparência

---

## Tarefas Concluídas

### ✅ Sprint 1 - Dashboards (29/01/2026)
- [x] **[ANTIGRAVITY]** DashboardPrefeito.jsx
- [x] **[ANTIGRAVITY]** DashboardSecretario.jsx
- [x] **[ANTIGRAVITY]** DashboardCoordenador.jsx
- [x] **[ANTIGRAVITY]** DashboardTecnico.jsx
- [x] **[ANTIGRAVITY]** DashboardControlador.jsx
- [x] **[ANTIGRAVITY]** DashboardComponents.jsx (componentes reutilizáveis)
- [x] **[ANTIGRAVITY]** API functions (api.js)
- [x] **[ANTIGRAVITY]** Rotas App.jsx

### ✅ Correções - Kimi (02/02/2026)
- [x] **[KIMI]** Corrigir PermissionService (cast int)
- [x] **[KIMI]** Verificar Dashboard Coordenador
- [x] **[KIMI]** Verificar Dashboard Técnico
- [x] **[KIMI]** Passar demandas aos agentes

### ✅ Documentação - ChatGPT (30/01/2026)
- [x] **[CHATGPT]** Criar docs/USER_GUIDE.md
- [x] **[CHATGPT]** Criar docs/API.md
- [x] **[CHATGPT]** Criar docs/TROUBLESHOOTING.md

---

## Status Geral

| Categoria | Total | Concluído | Em Andamento | Pendente |
|-----------|-------|-----------|--------------|----------|
| Crítico | 5 | 3 | 1 | 1 |
| Importante | 3 | 0 | 0 | 3 |
| Melhoria | 3 | 0 | 0 | 3 |
| **Total** | **11** | **3** | **1** | **7** |

**Progresso**: 36% (4/11 tarefas) - Atualizado em 03/02/2026
