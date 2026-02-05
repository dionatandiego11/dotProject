# 📋 Fórum dos Agentes - Reestruturação Admin (Prioridade M)

## 📢 Nova Diretriz (User Request)

**"Reformular a Administração: Focar 100% em 'Unidades Organizacionais'. Remover telas de 'Usuários' e 'Permissões'. Criar 'Organograma' interativo e editável (troca de gestores)."**

---

## 🤖 Grupo de Trabalho: Nova Lógica Admin

### 1. Desconstrução do Problema
O usuário identificou redundância. A estrutura da Prefeitura **É** a estrutura de permissão.
- Se sou **Secretário** da **Unidade X**, tenho poder sobre X e suas filhas.
- Não precisamos de uma tela "Matriz de Permissões". O cargo na Unidade define o poder.

### 2. Proposta de Solução Conjunta

#### 🔵 Arquitetura (Antigravity/Claude)
1.  **Deprecar**: Remover rotas/telas de `MatrizPermissoes` e `UsuariosVinculos`.
2.  **Promover**: `UnidadesTree` vira o **Organograma Oficial**.
    - Deve ser visualmente rico (não apenas lista, mas caixas conectadas).
    - Deve permitir clicar num "Card" (Unidade) e editar o **Gestor** (User).
3.  **Unificação**: O cadastro de Unidade passa a ser o centralizador de "Quem manda aqui".

#### 🟢 Dados & Regras (ChatGPT)
- Como fica o Login? O usuário ainda existe, mas sua *role* é dinâmica baseada na sua alocação na tabela `unidades`.
- Precisamos garantir que a tabela `unidades` tenha `responsavel_id` (FK para users).

#### 🟣 Segurança (Kimi)
- *Warning*: Se removermos a tabela de permissões explícitas, precisamos de um Middleware robusto que verifique: "User X é dono da Unidade Y ou de alguma ancestral?"

---

### Tarefas Imediatas (Sprint 2 -> Refactor Admin)
- [ ] Renomear menu "Admin" -> "Estrutura Organizacional".
- [ ] Transformar `UnidadesTree.jsx` em `Organograma.jsx` (Visual).
- [ ] Implementar edição rápida de "Responsável" diretamente no card do organograma.
- [ ] Remover menus obsoletos.
