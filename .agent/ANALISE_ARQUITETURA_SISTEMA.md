# 🏗️ Análise da Arquitetura de Navegação

## ❌ Problema: Redundância de Dashboards

### Navegação Atual (Problemática)

```
🏠 Home (/)
   └─ Dashboard Geral (Projetos/Tarefas/Kanban)

📊 Dashboards (/dashboard/*)
   ├─ Visão Geral (REDUNDANTE - mesmo que Home)
   ├─ Prefeito
   ├─ Secretário
   ├─ Coordenador
   ├─ Técnico
   └─ Controlador

⚙️ Administração (/admin/*)
   ├─ Dashboard Admin (REDUNDANTE - resumo admin)
   ├─ Níveis
   ├─ Unidades
   ├─ Usuários
   └─ Permissões
```

**Problemas:**
1. **3 dashboards diferentes** - Usuário não sabe qual usar
2. **Visão Geral vs Home** - Funcionalidade idêntica
3. **Dashboard Admin** - Desnecessário, admin quer gerenciar, não ver dashboard
4. **Múltiplos cliques** para chegar onde precisa

---

## ✅ Solução Proposta: Arquitetura Simplificada

### Princípios:
1. **Uma entrada única** por contexto
2. **Sem duplicação** de funcionalidade
3. **Acesso direto** às operações principais

### Nova Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│  dotProject Prefeituras                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📊 VISÃO EXECUTIVA (Perfil do Usuário)                     │
│  ├─ Meu Dashboard (baseado no perfil: Prefeito/Sec/Coord)  │
│  └─ Overview da Minha Área                                  │
│                                                             │
│  📁 GESTÃO DE PROJETOS                                      │
│  ├─ Projetos (lista)                                        │
│  ├─ Tarefas                                                 │
│  └─ Kanban                                                  │
│                                                             │
│  ⚙️ ADMINISTRAÇÃO (apenas para gestores)                   │
│  ├─ Estrutura Organizacional                                │
│  │   ├─ Níveis Hierárquicos                                 │
│  │   └─ Unidades                                            │
│  ├─ Gestão de Usuários                                      │
│  │   ├─ Usuários                                            │
│  │   └─ Permissões                                          │
│  └─ Configurações do Sistema                                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔧 Mudanças Específicas

### 1. Unificar Dashboards

**ANTES:**
- `/` = Home (Dashboard)
- `/dashboard` = Visão Geral (redundante)
- `/admin` = Dashboard Admin (redundante)

**DEPOIS:**
- `/` = **Meu Dashboard** (auto-detecta perfil do usuário)
  - Se for Prefeito → mostra dashboard de prefeito
  - Se for Secretário → mostra dashboard de secretário
  - Se for Admin → mostra overview administrativo

### 2. Simplificar Menu

**ANTES (7 itens + 2 dropdowns):**
```
- Home
- Dashboards (dropdown com 6 itens)
- Projetos
- Tarefas
- Kanban
- Administração (dropdown com 5 itens)
```

**DEPOIS (5 itens + 1 dropdown condicional):**
```
- Dashboard (único, baseado no perfil)
- Projetos
- Tarefas
- Kanban
- Administração (apenas se for admin)
  ├─ Níveis
  ├─ Unidades
  ├─ Usuários
  └─ Permissões
```

### 3. Rotas Simplificadas

**ANTES:**
```
/                    → Dashboard genérico
/dashboard           → Visão Geral (redundante)
/dashboard/prefeito  → Dashboard Prefeito
/dashboard/secretario→ Dashboard Secretário
/admin               → Dashboard Admin (redundante)
/admin/niveis        → Níveis
...
```

**DEPOIS:**
```
/                    → Meu Dashboard (baseado no perfil)
/projetos            → Projetos
/tasks               → Tarefas
/kanban              → Kanban
/admin/niveis        → Níveis (sem /admin isolado)
/admin/unidades      → Unidades
/admin/usuarios      → Usuários
/admin/permissoes    → Permissões
```

---

## 📊 Comparativo

| Aspecto | Antes | Depois |
|---------|-------|--------|
| Dashboards | 3 (Home, Visão Geral, Admin) | 1 (Meu Dashboard) |
| Itens de menu | 7 + 2 dropdowns | 4-5 + 1 dropdown condicional |
| Clicks para Dashboard | 1-2 | 1 |
| Clicks para Níveis | 3 | 2 |
| Confusão do usuário | Alta | Baixa |

---

## 🎯 Implementação

### Arquivos a Modificar:

1. **frontend/src/layouts/MainLayout.jsx**
   - Remover menu "Dashboards" dropdown
   - Home vira "Dashboard" (único)
   - Admin mostra só se tiver permissão

2. **frontend/src/App.jsx**
   - Remover rota `/dashboard` redundante
   - Manter `/dashboard/*` para compatibilidade (redireciona para `/`)
   - Remover `/admin` (dashboard), manter `/admin/*` (CRUDs)

3. **frontend/src/pages/Dashboard.jsx**
   - Modificar para detectar perfil do usuário
   - Renderizar componente apropriado (Prefeito/Secretário/etc)

4. **frontend/src/pages/Home.jsx** (novo)
   - Se usuário logado → redireciona para Dashboard
   - Se não logado → redireciona para Login

---

## 💡 Lógica de Negócio

### Perfil do Usuário → Dashboard Adequado

```javascript
// Lógica de redirecionamento
const perfil = user.perfil; // 'prefeito', 'secretario', 'coordenador', etc.

switch(perfil) {
  case 'prefeito':
    return <DashboardPrefeito />;
  case 'secretario':
    return <DashboardSecretario />;
  case 'coordenador':
    return <DashboardCoordenador />;
  // ... etc
  default:
    return <DashboardGenerico />;
}
```

### Permissões → Menu Admin

```javascript
// Mostrar menu Admin apenas se for admin
const isAdmin = user.roles?.includes('admin');

{isAdmin && (
  <MenuAdmin>
    <Item>Níveis</Item>
    <Item>Unidades</Item>
    <Item>Usuários</Item>
  </MenuAdmin>
)}
```

---

## ✅ Checklist de Implementação

- [ ] Modificar MainLayout (menu simplificado)
- [ ] Modificar App.jsx (rotas simplificadas)
- [ ] Criar lógica de auto-detecção de perfil
- [ ] Remover rotas redundantes
- [ ] Testar navegação completa
- [ ] Validar UX com usuários

---

## 🚀 Benefícios

1. **Menor curva de aprendizado** - Usuário sabe onde ir
2. **Menos código** - Menos manutenção
3. **Melhor performance** - Menos rotas, menos componentes
4. **UX mais clara** - Cada função tem um lugar único

---

*Documento criado para discussão da equipe*
