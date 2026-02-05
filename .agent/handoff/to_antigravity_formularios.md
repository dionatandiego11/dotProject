# 📝 Handoff para Antigravity - Formulários Admin

**Data**: 02/02/2026 09:20  
**De**: Kimi  
**Para**: Antigravity  
**Prioridade**: Média (após correções dos dashboards)

---

## 🎯 Tarefas Atribuídas

### 📋 Contexto

Os dashboards estão quase todos funcionando (4/5). Após o ChatGPT corrigir o último dashboard (Secretário), precisamos implementar os formulários da área administrativa.

---

### 1. 🟡 FORMULÁRIO DE NÍVEIS HIERÁRQUICOS

**Path**: `/admin/niveis/novo`  
**Prioridade**: Alta

**Campos do formulário**:
- `nome` (string, obrigatório) - Nome do nível
- `ordem` (int, obrigatório) - Ordem hierárquica (1=Prefeitura, 2=Secretaria, etc)
- `descricao` (string, opcional) - Descrição do nível

**Exemplo de níveis**:
1. Prefeitura
2. Secretaria
3. Coordenação
4. Equipe/Setor

**Backend**:
- Verificar se já existe endpoint na API
- Se não existir, criar em `api_routes_admin.php`

---

### 2. 🟡 FORMULÁRIO DE UNIDADES ORGANIZACIONAIS

**Path**: `/admin/unidades/nova`  
**Prioridade**: Alta

**Campos do formulário**:
- `nome` (string, obrigatório) - Nome da unidade
- `sigla` (string, opcional) - Sigla da unidade
- `nivel_id` (int, obrigatório) - ID do nível hierárquico
- `unidade_pai_id` (int, opcional) - ID da unidade pai (para hierarquia)
- `responsavel_nome` (string, opcional) - Nome do responsável
- `responsavel_email` (string, opcional) - Email do responsável

**Funcionalidades**:
- Seletor de unidade pai em árvore (dropdown hierárquico)
- Validação de email
- Máscara para campos

---

### 3. 🟢 TIMELINE VISUAL DE PROJETOS

**Path**: Integrar na página de detalhe do projeto  
**Prioridade**: Média

**Requisitos**:
- Componente horizontal com etapas do projeto
- Cores por status:
  - 🟢 Verde - Etapa concluída
  - 🟡 Amarelo - Etapa em andamento
  - 🔴 Vermelho - Etapa atrasada
  - ⚪ Cinza - Etapa futura
- Navegação clicável entre etapas
- Responsivo (funcionar em mobile)

**Exemplo visual**:
```
[Concluído]----[Em Andamento]----[Futuro]----[Futuro]
   🟢              🟡              ⚪          ⚪
```

---

## 📁 Estrutura de Arquivos Sugerida

```
frontend/src/
├── pages/admin/
│   ├── Niveis/
│   │   ├── NivelList.jsx       # Listagem de níveis
│   │   ├── NivelForm.jsx       # Formulário de níveis
│   │   └── NivelRoutes.jsx     # Rotas
│   └── Unidades/
│       ├── UnidadeList.jsx     # Listagem de unidades
│       ├── UnidadeForm.jsx     # Formulário de unidades
│       └── UnidadeRoutes.jsx   # Rotas
└── components/
    └── Timeline/
        ├── Timeline.jsx        # Componente timeline
        ├── TimelineItem.jsx    # Item individual
        └── Timeline.module.css # Estilos
```

---

## 🔗 APIs Necessárias

Verificar se existem endpoints para:

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/v1/admin/niveis` | GET | Listar níveis |
| `/api/v1/admin/niveis` | POST | Criar nível |
| `/api/v1/admin/unidades` | GET | Listar unidades |
| `/api/v1/admin/unidades` | POST | Criar unidade |
| `/api/v1/admin/unidades/arvore` | GET | Unidades em formato de árvore |

Se não existirem, avisar no chat para criarmos.

---

## 📋 Checklist

- [ ] Formulário de níveis criado
- [ ] Formulário de unidades criado
- [ ] Seletor de unidade pai em árvore funcionando
- [ ] Timeline visual criada
- [ ] Integração com backend testada
- [ ] Responsividade verificada
- [ ] Documentação atualizada

---

## ⚠️ Dependências

**Aguardar antes de começar**:
- ✅ Correção do dashboard Secretário (ChatGPT)
- ✅ Todos os dashboards funcionando (5/5)

**Pode começar em paralelo**:
- Estrutura de pastas
- Componentes visuais (sem integração)
- Prototipagem da timeline

---

## 💬 Dúvidas?

Mencione no `.agent/chat_agent.md` com `[AJUDA]` ou `@Antigravity`.

Vamos fazer isso! 💪
