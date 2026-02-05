# 🤖 Sistema de Orquestração de Agentes AI

## Visão Geral

Este sistema coordena 3 agentes AI para acelerar o desenvolvimento do dotProject:

| Agente | Especialidade | Forças |
|--------|--------------|--------|
| **Antigravity (Gemini)** | Arquitetura e Planejamento | Visão holística, geração de código, planejamento |
| **Kimi** | Debug e Funcionamento | Análise profunda, debug, fazer funcionar |
| **ChatGPT** | Documentação e Revisão | Explicações, documentação, code review |

---

## ⚠️ Ambiente Técnico (IMPORTANTE)

> [!CAUTION]
> **TODOS OS COMANDOS DEVEM SER EXECUTADOS VIA WSL!**
> Os arquivos estão em um ambiente Docker. Não usar PowerShell diretamente.

### Comandos Padrão

```bash
# Iniciar frontend
wsl bash -c "cd /mnt/c/Users/dionatan.resende/Downloads/dotProject/frontend && npm run dev"

# Build do frontend
wsl bash -c "cd /mnt/c/Users/dionatan.resende/Downloads/dotProject/frontend && npm run build"

# Acessar container Docker (se necessário)
wsl docker exec -it dotproject_web bash

# Ver logs
wsl docker logs dotproject_web -f
```

### URLs de Acesso
- **Frontend**: http://localhost:5173
- **Backend API**: http://localhost/api.php/v1
- **Admin**: http://localhost:5173/admin

---

## 📁 Estrutura de Comunicação

Todos os agentes se comunicam via arquivos em `.agent/`:

```
.agent/
├── orchestrator.md          # Este arquivo (plano geral)
├── backlog.md               # Tarefas pendentes
├── current_task.md          # Tarefa atual em execução
├── handoff/                 # Pasta de handoff entre agentes
│   ├── to_kimi.md           # Instruções para Kimi
│   ├── to_chatgpt.md        # Instruções para ChatGPT
│   └── to_antigravity.md    # Instruções para Antigravity
└── completed/               # Tarefas concluídas
```

---

## 🎯 Responsabilidades por Agente

### Antigravity (Gemini) - 🏗️ Arquiteto
- Criar planos de implementação
- Gerar código inicial de novas features
- Definir estrutura de arquivos
- Orquestrar tarefas entre agentes
- Fazer builds e verificar compilação

### Kimi - 🔧 Debugger
- Resolver erros de compilação
- Fazer sistema funcionar end-to-end
- Debug de integrações (API, DB)
- Corrigir bugs de runtime
- Testar fluxos completos

### ChatGPT - 📝 Documentador
- Escrever documentação técnica
- Fazer code reviews detalhados
- Criar guias de usuário
- Melhorar legibilidade do código
- Sugerir refatorações

---

## 📋 Backlog Atual (dotProject Prefeituras)

### Prioridade Alta
- [x] **[KIMI]** Testar dashboards por perfil no navegador (4/5 OK)
- [x] **[KIMI]** Verificar integração API → Frontend dos dashboards
- [x] **[ANTIGRAVITY]** Implementar formulários da área admin (estrutura pronta)
- [ ] **[CHATGPT]** Corrigir Dashboard Secretário (erro 500)
- [ ] **[CHATGPT]** Corrigir NotificationService (erro 500)

### Prioridade Média
- [ ] **[ANTIGRAVITY]** Criar timeline visual de projetos
- [ ] **[CHATGPT]** Documentar API de dashboards
- [ ] **[KIMI]** Resolver problemas de autenticação por perfil

### Prioridade Baixa
- [ ] **[CHATGPT]** Criar guia de usuário dos dashboards
- [ ] **[ANTIGRAVITY]** Implementar painel de emendas
- [ ] **[CHATGPT]** Revisar código dos componentes

---

## 🔄 Protocolo de Handoff

### Para passar tarefa para outro agente:

1. Criar arquivo em `.agent/handoff/to_{agente}.md`
2. Incluir:
   - **Contexto**: O que foi feito até agora
   - **Problema**: O que precisa ser resolvido
   - **Arquivos**: Lista de arquivos relevantes
   - **Critério de sucesso**: Como saber que está pronto

### Exemplo de handoff:

```markdown
# Handoff: Debug Dashboard Prefeito

## Contexto
Implementei o DashboardPrefeito.jsx mas ao acessar /dashboard/prefeito 
aparece tela branca.

## Problema
Console mostra: "TypeError: Cannot read property 'indicadores' of undefined"

## Arquivos
- frontend/src/pages/dashboard/DashboardPrefeito.jsx
- frontend/src/services/api.js (função getDashboardPrefeito)
- src/Api/Controller/DashboardController.php

## Critério de Sucesso
Dashboard carrega e exibe cards de indicadores sem erros no console.
```

---

## 📊 Status Atual do Projeto

### ✅ Concluído
- Dashboards por perfil (5 páginas React)
- Componentes reutilizáveis de dashboard
- Rotas no App.jsx
- Funções API no frontend

### 🔄 Em Andamento
- Correção Dashboard Secretário (ChatGPT investigando erro 500)
- NotificationService (Erro 500 - ChatGPT)

### ⏳ Pendente
- Formulários da área admin
- Timeline visual de projetos
- Documentação da API
