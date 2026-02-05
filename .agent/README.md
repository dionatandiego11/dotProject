# 📋 Guia de Orquestração de Agentes AI

> **Projeto**: dotProject Prefeituras  
> **Versão**: 1.0  
> **Última Atualização**: 30/01/2026

---

## 🎯 Objetivo

Este guia padroniza a comunicação entre agentes AI (Claude/Antigravity, Kimi, ChatGPT) para garantir colaboração eficiente no desenvolvimento do sistema.

---

## 📁 Estrutura de Arquivos

```
.agent/
├── README.md                    # Este guia
├── forum_agentes.md             # Fórum central (estado atual)
├── backlog.md                   # Lista de tarefas
├── templates/
│   ├── handoff.md               # Template para handoffs
│   ├── diagnosis.md             # Template para diagnósticos
│   └── decision.md              # Template para decisões
├── handoff/
│   ├── to_antigravity.md        # Mensagens para Claude
│   ├── to_kimi.md               # Mensagens para Kimi
│   └── to_chatgpt.md            # Mensagens para ChatGPT
└── meetings/
    └── YYYY-MM-DD_<topico>.md   # Registros de diagnósticos
```

---

## 🤖 Papéis dos Agentes

| Agente | Emoji | Especialidade | Responsabilidades |
|--------|-------|---------------|-------------------|
| **Claude/Antigravity** | 🔵 | Arquitetura & Implementação | Código, estrutura, implementação |
| **Kimi** | 🟢 | Debug & Funcionamento | Testes, debug, verificação |
| **ChatGPT** | 🟡 | Documentação & Revisão | Docs, code review, orquestração |

---

## 📝 Instruções por Agente

### Ao iniciar trabalho, cada agente deve:

1. **Ler** o `forum_agentes.md` para entender o contexto atual
2. **Verificar** se há mensagens em `handoff/to_<seu_nome>.md`
3. **Atualizar** sua seção no fórum com status "🔄 Em análise"
4. **Executar** as tarefas atribuídas
5. **Registrar** resultados na sua seção
6. **Atualizar** o `backlog.md` marcando tarefas como concluídas
7. **Criar handoff** se precisar de outro agente

---

## 📄 Templates

### Template de Handoff (para `handoff/to_*.md`)

```markdown
# 📝 Handoff para [NOME_AGENTE]

**Data**: DD/MM/YYYY HH:MM  
**De**: [AGENTE_ORIGEM]  
**Prioridade**: Alta | Média | Baixa

---

## Contexto
[Breve descrição do que foi feito até agora]

## Tarefa Solicitada
[O que precisa ser feito]

## Arquivos Relevantes
- `caminho/arquivo1.ext` - descrição
- `caminho/arquivo2.ext` - descrição

## Critérios de Sucesso
- [ ] Critério 1
- [ ] Critério 2

## Observações
[Notas adicionais, cuidados, dependências]
```

### Template de Diagnóstico (para `meetings/`)

```markdown
# 🔍 Diagnóstico: [TÍTULO]

**Data**: DD/MM/YYYY  
**Agente Responsável**: [NOME]  
**Status**: 🔄 Em Andamento | ✅ Concluído | ❌ Bloqueado

---

## Problema Identificado
[Descrição clara do problema]

## Análise
[Investigação realizada]

## Causa Raiz
[O que está causando o problema]

## Solução Proposta
[Como resolver]

## Impacto
- **Risco**: Alto | Médio | Baixo
- **Esforço**: Alto | Médio | Baixo
- **Arquivos afetados**: 

## Próximos Passos
1. Passo 1
2. Passo 2
```

### Template de Decisão (para discussões importantes)

```markdown
# ⚖️ Decisão: [TÍTULO]

**Data**: DD/MM/YYYY  
**Status**: 📋 Proposta | 🗳️ Em Votação | ✅ Aprovada | ❌ Rejeitada

---

## Contexto
[Por que essa decisão é necessária]

## Opções

### Opção A: [Nome]
- **Descrição**: 
- **Prós**: 
- **Contras**: 
- **Esforço**: 

### Opção B: [Nome]
- **Descrição**: 
- **Prós**: 
- **Contras**: 
- **Esforço**: 

## Recomendação
[Qual opção é recomendada e por quê]

## Votos
| Agente | Voto | Justificativa |
|--------|------|---------------|
| Claude | | |
| Kimi | | |
| ChatGPT | | |

## Decisão Final
[Resultado após votação/discussão]
```

---

## 🔄 Fluxo de Trabalho Padrão

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Usuário   │────▶│   Agente    │────▶│   Handoff   │
│  (Invoca)   │     │  (Executa)  │     │  (Próximo)  │
└─────────────┘     └─────────────┘     └─────────────┘
                           │
                           ▼
                    ┌─────────────┐
                    │   Atualiza  │
                    │   Fórum +   │
                    │   Backlog   │
                    └─────────────┘
```

---

## ✅ Checklist de Qualidade

Antes de finalizar, cada agente deve verificar:

- [ ] Atualizou sua seção no `forum_agentes.md`
- [ ] Registrou ação no histórico do fórum
- [ ] Marcou tarefas concluídas no `backlog.md`
- [ ] Criou handoff se necessário para próximo agente
- [ ] Código/documentação está testado e funcionando
- [ ] Não há arquivos com encoding quebrado

---

## 🔗 Fluxo de Vinculos e Tarefas (Resumo)

1) Usuario -> Unidade (vinculos)
   - Cadastro/edicao em `admin/usuarios` cria vinculo na unidade.
   - Um usuario pode ter multiplos vinculos na mesma unidade.
2) Projeto -> Unidade
   - Projeto usa `project_company` como unidade responsavel.
3) Kanban/Tarefas -> Usuarios vinculados
   - Kanban lista usuarios vinculados a unidade do projeto.
   - Responsavel da unidade tambem entra como fallback/extra.

## 🔐 Regras de Visibilidade (Hierarquia)

- Prefeito: ve tudo (unidade raiz).
- Secretario: ve sua secretaria + unidades subordinadas.
- Chefe: ve apenas sua unidade.
- Tecnico/Analista: ve apenas sua unidade (dashboard simples).

## ✅ Teste rapido (CLI)

Verifica se um projeto possui unidade e vinculos ativos:

```
docker compose exec -T mariadb mysql -udotproject -pdotproject123 dotproject \
  -e "SELECT project_id, project_name, project_company FROM dotp_projects WHERE project_id=1;"

docker compose exec -T mariadb mysql -udotproject -pdotproject123 dotproject \
  -e "SELECT vinculo_user_id, vinculo_unidade_id, vinculo_status FROM dotp_usuario_unidades WHERE vinculo_unidade_id=40;"
```

Substitua `1` e `40` pelos IDs reais.

---

## 🚨 Convenções Importantes

1. **Datas**: Usar formato `DD/MM/YYYY` ou `YYYY-MM-DD`
2. **Status**: Usar emojis padrão (🔄 ✅ ❌ ⚠️)
3. **Arquivos**: Usar UTF-8 sem BOM, line endings LF
4. **Commits**: Formato `[AGENTE] Descrição da mudança`
5. **IDs de tarefa**: Usar prefixo do agente (ANT-, KIM-, GPT-)

---

## 📞 Escalação

Se um agente encontrar um bloqueio que não pode resolver:

1. Documentar no diagnóstico com status ❌ Bloqueado
2. Criar handoff com prioridade Alta
3. Adicionar na seção "Tópicos em Aberto" do fórum
4. Notificar usuário se urgente
