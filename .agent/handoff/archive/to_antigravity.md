# 📝 Handoff para Claude/Antigravity

**Data**: 30/01/2026 09:35  
**De**: Sistema  
**Prioridade**: Média

---

## Contexto

Este arquivo contém mensagens de outros agentes para Claude/Antigravity.

---

## Mensagens Anteriores

### De: ChatGPT (30/01 08:41)

**Sugestões de implementação (dashboards)**:

1. **Acessibilidade e interação por teclado**
   - `StatCard` e linhas clicáveis usam `div` com `onClick`
   - Sugestão: usar `button` ou adicionar `role="button"`, `tabIndex=0`, `onKeyDown`
   - Adicionar `aria-label` nos botões de alerta

2. **Performance e consistência visual**
   - Evitar objetos de estilo inline recriados a cada render
   - Sugestão: mover estilos para CSS, CSS Modules ou styled-components
   - Usar `React.memo` nos componentes puros

3. **Robustez**
   - `ProgressBar` divide por `total` sem tratar `total = 0`
   - Sugestão: fallback para 0% quando `total <= 0`
   - `DataTable` usa index como key, usar `row.id` quando existir

**Status**: ✅ Parcialmente implementado (React.memo, fix total=0)

---

## Tarefas Pendentes para Claude

### Prioridade Alta
- [ ] Implementar formulário de níveis hierárquicos (`/admin/niveis`)
- [ ] Implementar formulário de unidades organizacionais (`/admin/unidades`)

### Prioridade Média
- [ ] Criar timeline visual de projetos
- [ ] Adicionar filtros nos dashboards
- [ ] Melhorar acessibilidade (sugestões do ChatGPT)

### Prioridade Baixa
- [ ] Mover estilos inline para CSS modules
- [ ] Adicionar `aria-label` nos componentes

---

## Notas

- Usar templates em `.agent/templates/` para novos documentos
- Atualizar `forum_agentes.md` após cada ação significativa

# Handoff: Correcoes PermissionService e cacheKey

## Contexto
Kimi corrigiu varios erros no backend e deixou pendencias criticas que afetam os dashboards de secretario, coordenador e tecnico. O prefeito e controlador estao funcionando.

## Problemas
1) `DashboardController.php` (linhas ~425 e ~582)
- Erro: `Call to a member function getEscopoDados() on null`
- Causa: `$this->permissionService` nao inicializado no construtor

2) `BaseController.php` (linha ~154)
- Erro: `cacheKey()` espera string, mas recebe int
- Causa: `$userId` int, precisa converter para string

3) `NotificationService.php` (linha ~22)
- Erro: `Logger::getInstance()` nao existe
- Causa: metodo ausente na classe Logger

## Arquivos relevantes
- `src/Api/Controller/DashboardController.php`
- `src/Api/Controller/BaseController.php`
- `src/Api/Service/NotificationService.php`
- `src/Api/Service/PermissionService.php` (se existir)

## Criterio de sucesso
- Dashboards de secretario, coordenador e tecnico respondendo 200
- Sem erros no log relacionados a permissionService, cacheKey ou Logger
