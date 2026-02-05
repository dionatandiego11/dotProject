# 📋 Plano de Integração do Sistema

## 🎯 Objetivo

Integrar as três áreas do sistema (Home, Dashboards de Perfil, Admin) em uma experiência única e coesa, conectando todas às APIs do backend.

---

## 🔍 Diagnóstico Atual

### Problemas Identificados

| # | Problema | Impacto |
|---|----------|---------|
| 1 | **3 layouts diferentes** sem integração | Usuário se perde na navegação |
| 2 | Dashboard Prefeitura (`/dashboard/*`) isolado | Não acessível pelo menu principal |
| 3 | APIs não conectadas aos componentes | Dados estáticos ou em mock |
| 4 | Navegação inconsistente entre áreas | Baixa usabilidade |
| 5 | Admin sem acesso fácil à Home/Dashboards | Fluxo quebrado |

### Áreas do Sistema

```
┌─────────────────────────────────────────────────────────────┐
│  HOME (/)                    │  DASHBOARD PERFIL (/dashboard/*) │
│  ├─ Layout principal         │  ├─ Layout próprio (isolado)     │
│  ├─ Dashboard tradicional    │  ├─ Dashboards por perfil        │
│  ├─ Projetos                 │  │   (Prefeito, Secretário...)    │
│  ├─ Tarefas                  │  └─ SEM navegação para Home      │
│  ├─ Kanban                   │                                  │
│  └─ Link para Admin          │  ADMIN (/admin/*)                │
│                              │  ├─ Layout próprio (AdminLayout) │
│                              │  ├─ Níveis, Unidades, Usuários   │
│                              │  └─ Botão "Voltar" (somente)     │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Arquitetura Proposta

### 1. Estrutura de Navegação Unificada

```
┌─────────────────────────────────────────────────────────────┐
│  LAYOUT PRINCIPAL (aplicado a todas as rotas autenticadas)  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  SIDEBAR                                             │   │
│  │  ├─ 🏠 Home (Dashboard tradicional)                  │   │
│  │  ├─ 📊 Dashboards (menu dropdown)                    │   │
│  │  │   ├─ Visão Geral (/dashboard)                     │   │
│  │  │   ├─ Prefeito (/dashboard/prefeito)               │   │
│  │  │   ├─ Secretário (/dashboard/secretario)           │   │
│  │  │   ├─ Coordenador (/dashboard/coordenador)         │   │
│  │  │   ├─ Técnico (/dashboard/tecnico)                 │   │
│  │  │   └─ Controlador (/dashboard/controlador)         │   │
│  │  ├─ 📁 Projetos                                      │   │
│  │  ├─ ✅ Tarefas                                       │   │
│  │  ├─ 📋 Kanban                                        │   │
│  │  └─ ⚙️ Administração                                 │   │
│  │       └─ (abre área admin com mesmo layout)          │   │
│  └──────────────────────────────────────────────────────┘   │
│                         │                                   │
│  ┌──────────────────────┴──────────────────────────────┐   │
│  │  CONTEÚDO (Outlet do React Router)                  │   │
│  │  - Home, Dashboards, Projetos, Tarefas, Kanban      │   │
│  │  - Admin (com submenu próprio)                      │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 2. Fluxo de Navegação

```
                    ┌─────────────┐
                    │   /login    │
                    └──────┬──────┘
                           │
                           ▼
              ┌────────────────────────┐
              │     / (Home/Dashboard) │◄──────────────────┐
              │  ┌──────────────────┐  │                   │
              │  │ Layout Principal │  │                   │
              │  │ - Sidebar        │  │                   │
              │  │ - Notificações   │  │                   │
              │  │ - User Menu      │  │                   │
              │  └──────────────────┘  │                   │
              └───────┬────────────────┘                   │
                      │                                    │
        ┌─────────────┼─────────────┐                      │
        │             │             │                      │
        ▼             ▼             ▼                      │
   ┌─────────┐  ┌──────────┐  ┌─────────┐                  │
   │/projects│  │/dashboard│  │ /admin  │                  │
   │/tasks   │  │   /*     │  │   /*    │                  │
   │/kanban  │  │          │  │         │                  │
   └─────────┘  └──────────┘  └─────────┘                  │
                                     │                      │
                                     └──────────────────────┘
                                           (volta para home)
```

---

## 📦 Estrutura de Componentes

### Novos Componentes Necessários

```
frontend/src/
├── components/
│   └── navigation/
│       ├── Sidebar.jsx              # Sidebar unificado
│       ├── DashboardMenu.jsx        # Menu dropdown dashboards
│       ├── UserMenu.jsx             # Menu do usuário
│       └── Breadcrumbs.jsx          # Migalhas de pão
├── contexts/
│   └── NavigationContext.jsx        # Estado da navegação
├── hooks/
│   ├── useUserProfile.js            # Hook para perfil do usuário
│   ├── useNavigation.js             # Hook para navegação
│   └── usePermissions.js            # Hook para permissões
└── layouts/
    ├── MainLayout.jsx               # Layout principal unificado
    ├── AdminLayout.jsx              # Integrado ao MainLayout
    └── DashboardLayout.jsx          # Integrado ao MainLayout
```

---

## 🔌 Integração API ↔ Frontend

### 1. APIs Existentes (Backend)

| Endpoint | Descrição | Status |
|----------|-----------|--------|
| `GET /api/v1/auth/me` | Dados do usuário logado | ✅ Funcionando |
| `GET /api/v1/dashboard` | Dashboard auto-detectado | ✅ Funcionando |
| `GET /api/v1/dashboard/prefeito` | Dashboard Prefeito | ✅ Funcionando |
| `GET /api/v1/dashboard/secretario` | Dashboard Secretário | ✅ Funcionando |
| `GET /api/v1/dashboard/coordenador` | Dashboard Coordenador | ✅ Funcionando |
| `GET /api/v1/dashboard/tecnico` | Dashboard Técnico | ✅ Funcionando |
| `GET /api/v1/dashboard/controlador` | Dashboard Controlador | ✅ Funcionando |
| `GET /api/v1/projects` | Lista de projetos | ✅ Funcionando |
| `GET /api/v1/tasks` | Lista de tarefas | ✅ Funcionando |
| `GET /api/v1/admin/niveis` | Níveis hierárquicos | ❌ Não implementado |
| `GET /api/v1/admin/unidades` | Unidades organizacionais | ❌ Não implementado |

### 2. Hooks de Integração

```javascript
// hooks/useDashboard.js
export function useDashboard(perfil = null) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    const fetchData = async () => {
      const endpoint = perfil 
        ? `/dashboard/${perfil}` 
        : '/dashboard';
      const response = await api.get(endpoint);
      setData(response.data);
      setLoading(false);
    };
    fetchData();
  }, [perfil]);
  
  return { data, loading };
}

// hooks/useUserProfile.js
export function useUserProfile() {
  const [profile, setProfile] = useState(null);
  
  useEffect(() => {
    getCurrentUser().then(data => {
      setProfile(data);
    });
  }, []);
  
  return profile;
}
```

---

## 🛠️ Plano de Implementação

### Fase 1: Unificação do Layout (2-3 dias)

**Tarefas:**
- [ ] Criar `MainLayout.jsx` com sidebar unificado
- [ ] Adicionar menu dropdown "Dashboards" na sidebar
- [ ] Integrar `AdminLayout` como rota filha do `MainLayout`
- [ ] Criar componente `DashboardMenu` com todos os perfis
- [ ] Atualizar `App.jsx` para usar `MainLayout` em todas as rotas

**Arquivos a modificar:**
```
frontend/src/layouts/MainLayout.jsx (novo)
frontend/src/App.jsx
frontend/src/components/Layout.jsx (refatorar)
frontend/src/pages/admin/AdminLayout.jsx (integrar)
```

### Fase 2: Integração das APIs (2-3 dias)

**Tarefas:**
- [ ] Criar hooks de dados (`useDashboard`, `useProjects`, `useTasks`)
- [ ] Atualizar `Dashboard.jsx` (Home) para usar API real
- [ ] Atualizar todos os dashboards de perfil para usar dados da API
- [ ] Implementar loading states e error handling
- [ ] Adicionar cache de dados (React Query ou SWR)

**Arquivos a modificar:**
```
frontend/src/hooks/useDashboard.js (novo)
frontend/src/hooks/useProjects.js (novo)
frontend/src/hooks/useTasks.js (novo)
frontend/src/pages/Dashboard.jsx
frontend/src/pages/dashboard/DashboardPrefeito.jsx
frontend/src/pages/dashboard/DashboardSecretario.jsx
... (todos os dashboards)
```

### Fase 3: Admin APIs (3-4 dias)

**Tarefas:**
- [ ] Criar endpoints API para Admin:
  - `GET /api/v1/admin/niveis`
  - `POST /api/v1/admin/niveis`
  - `PUT /api/v1/admin/niveis/:id`
  - `DELETE /api/v1/admin/niveis/:id`
  - (mesmo para unidades, usuários, permissões)
- [ ] Criar hooks no frontend para admin
- [ ] Integrar formulários com APIs

**Arquivos a criar/modificar:**
```
api_routes_admin.php (novo/expandir)
src/Api/Controller/AdminController.php (novo)
frontend/src/hooks/useAdmin.js (novo)
frontend/src/pages/admin/NiveisList.jsx
frontend/src/pages/admin/UnidadesTree.jsx
```

### Fase 4: Navegação e UX (1-2 dias)

**Tarefas:**
- [ ] Adicionar breadcrumbs em todas as páginas
- [ ] Criar página "Seletor de Dashboard" inteligente
- [ ] Adicionar atalhos rápidos baseados no perfil do usuário
- [ ] Implementar transições suaves entre páginas
- [ ] Adicionar indicadores de carregamento

### Fase 5: Testes e Validação (2 dias)

**Tarefas:**
- [ ] Testar fluxo completo de navegação
- [ ] Testar integração das APIs
- [ ] Testar responsividade
- [ ] Testar com diferentes perfis de usuário
- [ ] Documentar o fluxo de integração

---

## 📋 Checklist de Integração

### Navegação
- [ ] Home (`/`) acessível de qualquer lugar
- [ ] Dashboards (`/dashboard/*`) acessíveis do menu
- [ ] Projetos (`/projects`) acessíveis
- [ ] Tarefas (`/tasks`) acessíveis
- [ ] Kanban (`/kanban`) acessível
- [ ] Admin (`/admin/*`) acessível e integrado

### APIs
- [ ] `/api/v1/dashboard` → Home Dashboard
- [ ] `/api/v1/dashboard/prefeito` → Dashboard Prefeito
- [ ] `/api/v1/dashboard/secretario` → Dashboard Secretário
- [ ] `/api/v1/dashboard/coordenador` → Dashboard Coordenador
- [ ] `/api/v1/dashboard/tecnico` → Dashboard Técnico
- [ ] `/api/v1/dashboard/controlador` → Dashboard Controlador
- [ ] `/api/v1/projects` → Lista de Projetos
- [ ] `/api/v1/tasks` → Lista de Tarefas
- [ ] `/api/v1/admin/*` → APIs de Administração

### Estados
- [ ] Loading states em todas as páginas
- [ ] Error handling com mensagens amigáveis
- [ ] Empty states quando não há dados
- [ ] Refresh automático de dados

---

## 🎨 Mockup da Interface Final

```
┌────────────────────────────────────────────────────────────────┐
│  🏛️ dotProject Prefeituras           🔔 👤 Admin ▼    [Sair]   │
├──────────┬─────────────────────────────────────────────────────┤
│          │                                                     │
│  🏠 Home │   📊 Dashboards                    [Atualizar]      │
│          │   ┌─────────────────────────────────────────────┐   │
│  📁 Projs│   │  🏛️ Prefeito  📋 Secretário  👤 Coordenador │   │
│          │   │  👷 Técnico    🔍 Controlador               │   │
│  ✅ Tasks│   └─────────────────────────────────────────────┘   │
│          │                                                     │
│  📋 Kanb │   ┌──────────────┐ ┌──────────────┐               │
│          │   │ Projetos     │ │ Tarefas      │               │
│  ────────│   │ ┌──────────┐ │ │ ┌──────────┐ │               │
│          │   │ │          │ │ │ │          │ │               │
│  📊 Dash │   │ │  Gráfico │ │ │ │  Lista   │ │               │
│  ├─ Perf │   │ │          │ │ │ │          │ │               │
│  ├─ Secr │   │ └──────────┘ │ │ └──────────┘ │               │
│  ├─ Coor │   └──────────────┘ └──────────────┘               │
│  ├─ Téc  │                                                     │
│  └─ Cont │   ┌─────────────────────────────────────────────┐   │
│          │   │ Alertas                                      │   │
│  ⚙️ Admin│   │ • Projeto X atrasado [Ver]                   │   │
│          │   │ • Tarefa Y pendente  [Ver]                   │   │
│          │   └─────────────────────────────────────────────┘   │
│          │                                                     │
└──────────┴─────────────────────────────────────────────────────┘
```

---

## 🚀 Próximos Passos Imediatos

1. **Aprovar este plano** com a equipe
2. **Dividir tarefas** entre os agentes:
   - **Kimi**: Fase 1 (Unificação do Layout)
   - **ChatGPT**: Fase 3 (APIs de Admin)
   - **Antigravity**: Fase 2 (Integração das APIs)
3. **Criar branch** para desenvolvimento
4. **Iniciar implementação** da Fase 1

---

## 📝 Notas

- **Prioridade**: Alta - Sistema atual está fragmentado
- **Complexidade**: Média-Alta - Requer mudanças em múltiplos componentes
- **Dependências**: APIs de dashboard já estão funcionando
- **Riscos**: Quebras de navegação durante a transição

---

*Documento criado em: 02/02/2026*
*Autor: Kimi (Agente de Debug e Integração)*
