# 🏛️ Direcionamento do Sistema para Prefeituras
## Módulo: Projetos & Programas - Gestão da Política Pública

**Documento de Direcionamento Estratégico**  
**Versão:** 1.2  

**Data:** Janeiro 2026

> **Atualização v1.1:** Adicionada seção completa de **Hierarquia Organizacional** com dashboards específicos para Prefeito, Secretário, Coordenador, Técnico e Controlador.
>
> **Atualização v1.2:** Adicionada **Área Administrativa** para configuração dinâmica da estrutura organizacional da prefeitura, permitindo cadastro de níveis, unidades, vínculos de usuários e geração automática de permissões.

---

## 📑 SUMÁRIO

1. [Resumo Executivo](#-resumo-executivo)
2. [Modelo Mental: Hierarquia da Gestão Pública](#-modelo-mental-hierarquia-da-gestão-pública)
3. [Entidades do Sistema](#-entidades-do-sistema)
4. [Fontes de Recurso](#-fontes-de-recurso-diferencial)
5. [Linha do Tempo Visual](#-linha-do-tempo-visual-timeline)
6. [Sistema de Status Visual](#-sistema-de-status-visual-anti-burocracia)
7. [Alertas Automáticos](#-alertas-automáticos)
8. **[Área Administrativa - Estrutura Organizacional](#-área-administrativa---estrutura-organizacional)** ⭐ **(NOVO)**
9. **[Hierarquia Organizacional e Permissões](#-hierarquia-organizacional-e-permissões)** ⭐
10. **[Dashboard por Perfil](#-dashboard-por-perfil)** ⭐
11. [Adaptação Técnica](#-adaptação-técnica-do-sistema)
12. [Interface do Usuário](#-interface-do-usuário)
13. [Implementação por Fases](#-implementação-por-fases)
14. [Checklist de Implementação](#-checklist-de-implementação)

---

## 📋 RESUMO EXECUTIVO

Este documento define a adaptação do sistema dotProject para atender às necessidades específicas de gestão pública municipal, transformando a plataforma em uma ferramenta de **planejamento, execução e controle da política pública**.

### 🎯 Problema que Resolve

| Situação Atual (Caos) | Situação Futura (Controle) |
|----------------------|---------------------------|
| PPA em PDF esquecido | PPA vivo e rastreável |
| LDO como peça formal | LDO integrada aos projetos |
| LOA em planilha + sistema contábil | Orçamento vinculado a ações |
| Obras em planilha paralela | Obras como projetos integrados |
| Convênios em pasta física | Convênios com timeline e alertas |
| Emendas no "controle mental" | Emendas rastreadas com fonte |

> **Nada conversa com nada** → **Tudo conversa com tudo**

---

## 🧠 MODELO MENTAL: Hierarquia da Gestão Pública

```
┌─────────────────────────────────────────┐
│           PPA (Plano Plurianual)        │
│         ← Conjunto de Programas         │
├─────────────────────────────────────────┤
│  Programa: Mobilidade Urbana            │
│  ← Conjunto de Projetos                 │
├─────────────────────────────────────────┤
│  Projeto: Pavimentação Bairro X         │
│  ← Conjunto de Ações                    │
├─────────────────────────────────────────┤
│  Ação: Licitação + Execução + Medição   │
│  ← Orçamento + Prazo + Responsável      │
└─────────────────────────────────────────┘
```

### Entidades do Sistema

#### 📌 1. PROGRAMA

**Definição:** Estrutura de médio prazo que organiza projetos sob uma mesma política pública.

**Atributos:**
- `codigo` (ex: "MOB-2024")
- `nome` (ex: "Programa de Mobilidade Urbana")
- `objetivo_estrategico`
- `vinculacao_ppa` (ano base, eixo)
- `secretaria_lider` (vínculo com departamento)
- `indicadores[]` (array de metas)
- `responsavel_politico` (secretário)
- `responsavel_tecnico` (diretor)
- `valor_orcamentario`
- `data_inicio` / `data_fim`
- `status`: `planejamento` | `execucao` | `concluido` | `suspenso`

**No Sistema:** Extensão da entidade `Project` com novos campos + tipo = "programa"

---

#### 🧱 2. PROJETO

**Definição:** Iniciativa específica com entrega concreta, orçamento definido e responsável único.

**Tipos de Projeto:**
| Tipo | Características | Exemplo |
|------|-----------------|---------|
| `obra` | Execução física, licitação obrigatória | Construção de escola |
| `politica_publica` | Ação continuada, benefício social | Programa de assistência |
| `convenio` | Repasse de recursos, prestação de contas | Convênio estadual de saúde |
| `emenda` | Recursos de parlamentar, execução vinculada | Emenda para quadra |

**Atributos (além dos existentes):**
- `tipo_projeto`: `obra` | `politica_publica` | `convenio` | `emenda`
- `programa_id` (vínculo pai)
- `secretaria_id`
- `responsavel_tecnico_id`
- `responsavel_politico_id`
- `fonte_recurso`: `proprio` | `convenio` | `emenda` | `transferencia`
- `numero_convenio` (se aplicável)
- `numero_emenda` (se aplicável)
- `parlamentar` (para emendas)
- `concedente` (para convênios)
- `vigencia_inicio` / `vigencia_fim` (obrigatório para convênios/emendas)
- `valor_previsto`
- `valor_empenhado`
- `valor_pago`
- `situacao_orcamentaria`: `dotado` | `empenhado` | `liquidado` | `pago`

---

#### 🔧 3. AÇÃO (Etapas do Projeto)

**Definição:** Unidade de execução concreta dentro do projeto.

**Atributos:**
- `projeto_id`
- `nome` (ex: "Fase 1: Licitação")
- `descricao`
- `etapa_ordem` (1, 2, 3...)
- `responsavel_id`
- `data_prevista_inicio` / `data_prevista_fim`
- `data_real_inicio` / `data_real_fim`
- `valor_orcado`
- `valor_executado`
- `status`: `nao_iniciada` | `em_execucao` | `concluida` | `atrasada`
- `percentual_execucao`
- `documentos[]` (array de anexos)

**No Sistema:** Extensão da entidade `Task` com campos adicionais

---

## 💰 FONTES DE RECURSO (Diferencial)

Sistema de classificação obrigatória:

```php
enum FonteRecurso: string {
    case PROPRIO = 'recurso_proprio';           // Orçamento municipal
    case CONVENIO = 'convenio';                  // Acordo com outros entes
    case EMENDA_PARLAMENTAR = 'emenda';          // Indicação legislativa
    case TRANSFERENCIA_VOLUNTARIA = 'transferencia';  // Outros repasses
}
```

### Painel de Controle por Fonte

| Fonte | Total | Empenhado | Pago | Saldo | Risco |
|-------|-------|-----------|------|-------|-------|
| Próprio | R$ 10M | R$ 4M | R$ 2M | R$ 8M | 🟢 |
| Convênios | R$ 5M | R$ 4.5M | R$ 2M | R$ 0.5M | 🟡 |
| Emendas | R$ 2M | R$ 0.5M | R$ 0 | R$ 1.5M | 🔴 |

> **Alerta automático:** Emendas com execução abaixo de 30% a 4 meses do fim do exercício.

---

## 📊 LINHA DO TEMPO VISUAL (Timeline)

Cada projeto possui uma **timeline horizontal** com etapas padronizadas:

```
Planejamento → Licitação → Execução → Medição → Pagamento → Conclusão
    🟢           🟢          🟡         ⬜          ⬜           ⬜
   Concluído   Concluído   Em dia   Pendente   Pendente    Futuro
```

### Cores de Status por Etapa

| Cor | Significado | Gatilho |
|-----|-------------|---------|
| 🟢 | Concluído | Data real preenchida |
| 🟡 | Em dia | Dentro do prazo previsto |
| 🔴 | Atrasado | Data prevista ultrapassada |
| ⬜ | Pendente | Não iniciado |
| ⚫ | Suspenso | Interrompido manualmente |

### Campos da Timeline

- `etapa` (nome da fase)
- `data_prevista_inicio/fim`
- `data_real_inicio/fim`
- `responsavel_id`
- `status`
- `justificativa_atraso` (obrigatório se 🔴)

---

## 🚨 SISTEMA DE STATUS VISUAL (Anti-Burocracia)

### Status Macro do Projeto

Status calculado automaticamente baseado nas etapas:

| Status | Ícone | Definição Algorítmica |
|--------|-------|----------------------|
| **Em dia** | 🟢 | Todas etapas dentro do prazo |
| **Atenção** | 🟡 | Uma etapa atrasada OU prazo a 15 dias |
| **Travado** | 🔴 | Etapa crítica atrasada há +30 dias |
| **Suspenso** | ⚫ | Decisão administrativa de pausa |
| **Concluído** | ✅ | Todas etapas finalizadas |

### Motivos de Atraso (Catalogados)

Campo obrigatório quando status = 🟡 ou 🔴:

- `falta_documento` - Documentação incompleta
- `falta_empenho` - Crédito não disponível
- `falta_empresa` - Licitação deserta
- `falta_decisao_politica` - Deliberação superior pendente
- `falta_projeto` - Projeto técnico em desenvolvimento
- `impedimento_legal` - Liminar, inquérito, etc.
- `problema_tecnico` - Dificuldade de execução
- `outros` (descrição livre)

> **Benefício político:** O problema fica objetivo, não pessoal.

---

## 🔔 ALERTAS AUTOMÁTICOS

### Regras de Alerta

| Alerta | Condição | Destinatário | Antecedência |
|--------|----------|--------------|--------------|
| Convênio a vencer | Data fim - hoje < 60 dias | Secretário + Controle | 60 dias |
| Obra parada | Sem atualização > 15 dias | Responsável técnico | 15 dias |
| Projeto parado | Sem movimentação > 30 dias | Secretário | 30 dias |
| Recurso não pago | Empenhado há 90 dias, não pago | Financeiro | 90 dias |
| Emenda sem execução | % execução < 30% e faltam 4 meses | Prefeito + Parlamentar | 4 meses |
| Prazo etapa próximo | Data fim - hoje < 7 dias | Responsável técnico | 7 dias |
| Indicador abaixo da meta | Realizado < 80% do previsto | Secretário | Mensal |

### Canais de Notificação

- **In-app:** Notificações no sistema
- **Email:** Diário com resumo
- **WhatsApp:** Alertas críticos (integração futura)
- **Dashboard:** Widget de alertas prioritários

---

## 🏛️ HIERARQUIA ORGANIZACIONAL E PERMISSÕES

O sistema respeita a estrutura hierárquica da administração pública, com dashboards e permissões específicas por nível.

### Estrutura de Cargos

```
┌─────────────────────────────────────────────────────────┐
│                    PREFEITO                             │
│              (Acesso total - Todas Secretarias)         │
├─────────────────────────────────────────────────────────┤
│    SECRETÁRIO DE OBRAS    │    SECRETÁRIO DE SAÚDE     │
│    (Toda a Secretaria)    │    (Toda a Secretaria)     │
├───────────────────────────┼─────────────────────────────┤
│  Coord. de Projetos │ Coord. Convênios │ Coord. Regional │
│  (Seus Projetos)    │ (Seus Convênios) │ (Sua Região)    │
├─────────────────────┼──────────────────┼─────────────────┤
│  Técnicos │ Técnicos │ Técnicos │ Técnicos │ Técnicos   │
│  (Tarefas)│ (Tarefas)│ (Tarefas)│ (Tarefas)│ (Tarefas)  │
└─────────────────────────────────────────────────────────┘
```

### Perfis de Acesso

| Perfil | Escopo de Dados | Ações Permitidas | Dashboard |
|--------|-----------------|------------------|-----------|
| **Prefeito** | Toda a prefeitura | Visualização, aprovações, alertas críticos | Executivo Geral |
| **Secretário** | Sua secretaria | Criar projetos, aprovar ações, definir prioridades | Secretaria |
| **Coordenador** | Seus programas/projetos | Gerenciar projetos, atualizar etapas, upload docs | Projetos |
| **Técnico** | Tarefas atribuídas | Executar ações, atualizar andamento | Tarefas |
| **Controlador** | Toda a prefeitura (leitura) | Auditar, gerar relatórios, ver alertas | Fiscalização |

### Hierarquia de Dados

```php
// Lógica de filtro baseada no perfil do usuário
function getEscopoDados(Usuario $user) {
    return match($user->perfil) {
        'prefeito' => [
            'secretarias' => 'todas',
            'programas' => 'todos',
            'projetos' => 'todos',
            'visualizar' => true,
            'editar' => false, // Apenas aprovações
        ],
        'secretario' => [
            'secretarias' => [$user->secretaria_id],
            'programas' => 'todos_da_secretaria',
            'projetos' => 'todos_da_secretaria',
            'visualizar' => true,
            'editar' => true, // Da sua secretaria
            'aprovar' => true,
        ],
        'coordenador' => [
            'secretarias' => [$user->secretaria_id],
            'programas' => [$user->programas_responsavel],
            'projetos' => 'responsavel_ou_equipe',
            'visualizar' => true,
            'editar' => true, // Seus projetos
        ],
        'tecnico' => [
            'secretarias' => [$user->secretaria_id],
            'programas' => [],
            'projetos' => [],
            'acoes' => 'atribuidas_a_mim',
            'visualizar' => true,
            'editar' => true, // Apenas suas ações
        ],
        'controlador' => [
            'secretarias' => 'todas',
            'programas' => 'todos',
            'projetos' => 'todos',
            'visualizar' => true,
            'editar' => false, // Apenas leitura
            'auditar' => true,
        ],
    };
}
```

---

## 🖥️ DASHBOARD POR PERFIL

### 1️⃣ DASHBOARD DO PREFEITO - Executivo Geral

**Visão:** Macro da gestão. Toda a prefeitura em uma tela.  
**Objetivo:** Tomada de decisão estratégica e identificação rápida de problemas.

#### Layout do Dashboard

```
┌──────────────────────────────────────────────────────────────────────┐
│  🏛️ PREFEITURA DE [NOME]          │  Olá, Prefeito [Nome]   🔔 12   │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📊 EXECUÇÃO DO PPA 2024-2027                                       │
│  ████████████████████░░░░░░░░░░  73%  (+5% vs mês anterior)         │
│  45 programas ativos de 62 previstos no plano                       │
└──────────────────────────────────────────────────────────────────────┘

┌───────────────┬───────────────┬───────────────┬──────────────────────┐
│   🟢 89       │   🟡 23       │   🔴 12       │   💰 R$ 5,2M        │
│   EM DIA      │   ATENÇÃO     │   TRAVADOS    │   EM RISCO          │
│   Projetos    │   Projetos    │   Projetos    │   (3 convênios)     │
│   (+12)       │   (+5)        │   (-2)        │                     │
└───────────────┴───────────────┴───────────────┴──────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  🚨 ALERTAS CRÍTICOS (Requerem Ação Imediata)                       │
├──────────────────────────────────────────────────────────────────────┤
│  🔴 Convênio Saúde #123 vence em 15 dias - R$ 2M em risco           │
│  🔴 5 obras da Secretaria de Obras paradas há +30 dias              │
│  🟡 8 emendas com execução abaixo de 30% - Faltam 3 meses           │
└──────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────┬──────────────────────────────┐
│  📉 PROJETOS TRAVADOS POR SECRETARIA  │  💵 EXECUÇÃO ORÇAMENTÁRIA    │
├───────────────────────────────────────┼──────────────────────────────┤
│  Obras        ████████░░  5 travados  │  Previsto:    R$ 150.000.000 │
│  Saúde        ██████░░░░  4 travados  │  Empenhado:   R$  98.500.000 │
│  Educação     ████░░░░░░  3 travados  │  Pago:        R$  72.300.000 │
│  Assistência  ██░░░░░░░░  2 travados  │  Executado:   48,2%          │
│  Transporte   █░░░░░░░░░  1 travado   │                              │
└───────────────────────────────────────┴──────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  🏗️ OBRAS EM DESTAQUE                                               │
├──────────────────────────────────────────────────────────────────────┤
│  Nome                    │ Prazo  │ Status │ Secretaria    │ Atraso  │
├──────────────────────────┼────────┼────────┼───────────────┼─────────┤
│  Escola Jardim das Flores│ 90 dias│  🔴    │ Educação      │ 60 dias │
│  Asfalto Rua dos Pinheiros│ 45 dias│  🔴   │ Obras         │ 35 dias │
│  UBS Centro              │ 120 dias│ 🟡    │ Saúde         │ 12 dias │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📝 EMENDAS PARLAMENTARES - Resumo                                  │
├──────────────────────────────────────────────────────────────────────┤
│  Recebidas: R$ 12.000.000  │  Executadas: R$ 7.200.000 (60%)        │
│  Em risco:  R$  2.400.000  │  Não executadas: R$ 2.400.000 (20%)    │
│  Parlamentares com emendas em risco: Dep. Silva, Dep. Costa        │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📅 AGENDA DOS PRÓXIMOS 30 DIAS                                     │
├──────────────────────────────────────────────────────────────────────┤
│  07/02  Convênio Saúde #123 vence                                   │
│  15/02  Entrega prevista: Escola Jardim das Flores (ATRASADA)       │
│  20/02  Prazo final para execução Emenda Dep. Silva                 │
│  28/02  Fim do exercício orçamentário                               │
└──────────────────────────────────────────────────────────────────────┘
```

#### Ações do Prefeito no Dashboard

| Ação | Descrição |
|------|-----------|
| 🔍 Ver detalhes | Drill-down para qualquer indicador |
| 📧 Enviar lembrete | Alerta ao responsável por projeto travado |
| ✅ Aprovar liberação | Aprovar recursos extras para projeto |
| 📝 Solicitar explicação | Registrar demanda de informação |

---

### 2️⃣ DASHBOARD DO SECRETÁRIO - Gestão da Secretaria

**Visão:** Todos os programas e projetos da sua secretaria.  
**Objetivo:** Gestão operacional e resolução de gargalos.

```
┌──────────────────────────────────────────────────────────────────────┐
│  🏛️ SECRETARIA MUNICIPAL DE OBRAS                                   │
│  Secretário: [Nome]              │  Projetos: 32  │  Equipe: 45     │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📊 INDICADORES DA SECRETARIA                                       │
│  Projetos em dia: 20  │  Atenção: 7  │  Travados: 5  │  Concluídos: 8│
│  Orçamento executado: 68%  │  R$ 32M de R$ 47M previstos            │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  🚨 ALERTAS DA SUA SECRETARIA (5)                                   │
├──────────────────────────────────────────────────────────────────────┤
│  🔴 Obra "Praça Central" - Parada há 35 dias (Falta empresa)        │
│  🔴 Projeto "Drenagem Bairro Sul" - Sem atualização há 40 dias      │
│  🟡 Convênio Infraestrutura - Vence em 45 dias                      │
│  🟡 3 projetos com prazo a vencer em 15 dias                        │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  👥 DESEMPENHO POR COORDENADOR                                      │
├──────────────────────────────────────────────────────────────────────┤
│  Coordenador          │ Projetos │ Em dia │ Atrasados │ % Execução  │
├──────────────────────────────────────────────────────────────────────┤
│  João Silva           │    8     │   6    │    2      │    78%      │
│  Maria Santos         │   12     │   9    │    3      │    65%      │
│  Pedro Costa          │    5     │   2    │    3      │    45% 🔴   │
│  Ana Pereira          │    7     │   7    │    0      │    92% 🟢   │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📋 SEUS PROGRAMAS                                                  │
├──────────────────────────────────────────────────────────────────────┤
│  Programa de Pavimentação        🟡  12 projetos │ 2 travados       │
│  Programa de Drenagem            🔴   8 projetos │ 3 travados       │
│  Programa de Praças e Parques    🟢   5 projetos │ 0 travados       │
│  Convênios de Infraestrutura     🟡   7 projetos │ 1 a vencer       │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  ⏱️ PROJETOS QUE PRECISAM DE ATENÇÃO                                │
├──────────────────────────────────────────────────────────────────────┤
│  Obra                  │ Etapa Atual      │ Status │ Responsável    │
├──────────────────────────────────────────────────────────────────────┤
│  Asfalto Av. Brasil    │ Execução         │ 🟡     │ João Silva     │
│  Drenagem B. Sul       │ Licitação        │ 🔴     │ Pedro Costa    │
│  Praça do Mercado      │ Planejamento     │ 🟡     │ Ana Pereira    │
└──────────────────────────────────────────────────────────────────────┘
```

#### Ações do Secretário

- Criar novo programa
- Aprovar projetos propostos pelos coordenadores
- Reordenar prioridades
- Solicitar reunião com coordenador
- Aprovar pagamentos

---

### 3️⃣ DASHBOARD DO COORDENADOR - Gestão de Projetos

**Visão:** Seus programas e projetos sob responsabilidade.  
**Objetivo:** Execução operacional e atualização de andamento.

```
┌──────────────────────────────────────────────────────────────────────┐
│  👤 COORDENADOR: João Silva                                         │
│  Secretaria: Obras  │  Programas: 2  │  Projetos: 8  │  Técnicos: 12 │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📊 SEUS PROJETOS                                                   │
│  Em dia: 6  │  Atenção: 1  │  Travados: 1  │  Concluídos: 3 (2024) │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  🎯 PROGRAMAS SOB SUA RESPONSABILIDADE                              │
├──────────────────────────────────────────────────────────────────────┤
│  Programa de Pavimentação Urbana                                    │
│  🟡 6 projetos │ R$ 15M orçamento │ 72% executado │ 1 travado       │
├──────────────────────────────────────────────────────────────────────┤
│  Programa de Recapeamento                                           │
│  🟢 2 projetos │ R$ 8M orçamento  │ 85% executado │ 0 travados      │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  ⚠️ PROJETOS QUE PRECISAM DE AÇÃO                                   │
├──────────────────────────────────────────────────────────────────────┤
│  🔴 Pavimentação Rua dos Trabalhadores                              │
│     Motivo: Falta empresa (licitação deserta)                       │
│     Atraso: 30 dias │ Prazo original: 15/01/2026                    │
│     [Nova Licitação]  [Prorrogar Prazo]  [Solicitar Apoio]          │
├──────────────────────────────────────────────────────────────────────┤
│  🟡 Asfalto Av. Brasil - Etapa: Execução (85%)                      │
│     Prazo: 30 dias para conclusão │ Tudo em dia                     │
│     [Atualizar Andamento]  [Ver Documentos]                         │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📋 PRÓXIMAS ETAPAS A COMPLETAR (Próximos 7 dias)                   │
├──────────────────────────────────────────────────────────────────────┤
│  Projeto                     │ Etapa              │ Prazo  │ Status │
├──────────────────────────────────────────────────────────────────────┤
│  Pavimentação Bairro Norte   │ Medição Janeiro    │ 05/02  │ ⬜     │
│  Recapeamento Centro         │ Ordem de Serviço   │ 07/02  │ ⬜     │
│  Drenagem Av. Principal      │ Relatório técnico  │ 10/02  │ ⬜     │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  👥 SUA EQUIPE                                                      │
├──────────────────────────────────────────────────────────────────────┤
│  Técnico            │ Tarefas Ativas │ Concluídas │ Performance     │
├──────────────────────────────────────────────────────────────────────┤
│  Carlos Souza       │      5         │    12      │ 🟢 Excelente    │
│  Fernanda Lima      │      3         │     8      │ 🟢 Boa          │
│  Ricardo Oliveira   │      8         │     5      │ 🟡 Sobrecarregado│
└──────────────────────────────────────────────────────────────────────┘
```

#### Ações do Coordenador

- Criar novo projeto
- Atualizar etapas/timeline
- Designar tarefas
- Fazer upload de documentos
- Solicitar recursos ao secretário
- Atualizar percentual de execução

---

### 4️⃣ DASHBOARD DO TÉCNICO - Minhas Tarefas

**Visão:** Apenas suas atribuições. Simples e direto.  
**Objetivo:** Execução das tarefas do dia a dia.

```
┌──────────────────────────────────────────────────────────────────────┐
│  👷 TÉCNICO: Carlos Souza                                           │
│  Coordenação: Pavimentação  │  Projetos: 3  │  Supervisor: João S.  │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📋 MINHAS TAREFAS                                                  │
│  A fazer: 3  │  Em andamento: 2  │  Em revisão: 1  │  Concluídas: 12 │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  ⭐ PRIORIDADES DE HOJE                                             │
├──────────────────────────────────────────────────────────────────────┤
│  🔴 Urgente - Vence hoje                                            │
│  □ Enviar relatório de medição - Obra Rua dos Trabalhadores         │
│    Projeto: Pavimentação Rua dos Trabalhadores                      │
│    [Anexar Arquivo]  [Marcar Concluída]                             │
├──────────────────────────────────────────────────────────────────────┤
│  🟡 Importante - Vence em 3 dias                                    │
│  □ Atualizar fotos de acompanhamento - Asfalto Av. Brasil           │
│    5 fotos necessárias                                              │
│    [Subir Fotos]                                                    │
├──────────────────────────────────────────────────────────────────────┤
│  ⬜ Normal - Sem prazo definido                                     │
│  □ Revisar projeto técnico - Drenagem Bairro Sul                    │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📊 MINHA PRODUÇÃO                                                  │
├──────────────────────────────────────────────────────────────────────┤
│  Tarefas concluídas esta semana: 5                                  │
│  Tarefas concluídas este mês: 12                                    │
│  Média de conclusão: 2.5 tarefas/dia                                │
│  Taxa de entrega no prazo: 92% 🟢                                    │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📁 PROJETOS QUE ESTOU PARTICIPANDO                                 │
├──────────────────────────────────────────────────────────────────────┤
│  Pavimentação Rua dos Trabalhadores  │ 3 tarefas ativas │ 🟡 Atrasado│
│  Asfalto Av. Brasil                  │ 2 tarefas ativas │ 🟢 Em dia  │
│  Recapeamento Centro                 │ 1 tarefa ativa   │ 🟢 Em dia  │
└──────────────────────────────────────────────────────────────────────┘
```

#### Ações do Técnico

- Ver tarefas atribuídas
- Atualizar status de tarefas
- Anexar documentos/evidências
- Registrar horas trabalhadas
- Solicitar ajuda ao coordenador

---

### 5️⃣ DASHBOARD DO CONTROLADOR - Visão de Fiscalização

**Visão:** Todas as secretarias em modo leitura + alertas de conformidade.  
**Objetivo:** Auditoria, transparência e prestação de contas.

```
┌──────────────────────────────────────────────────────────────────────┐
│  🔍 CONTROLADORIA INTERNA                                           │
│  Auditor: [Nome]         │  Acesso: Todas Secretarias (Leitura)     │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  🚨 ALERTAS DE CONFORMIDADE                                         │
├──────────────────────────────────────────────────────────────────────┤
│  🔴 3 projetos sem prestação de contas há +90 dias                  │
│  🔴 2 convênios com execução acima do cronograma físico             │
│  🟡 5 processos de licitação sem publicação no portal               │
│  🟡 8 projetos com diferença entre empenho e execução >20%          │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📊 PANORAMA GERAL POR SECRETARIA                                   │
├──────────────────────────────────────────────────────────────────────┤
│  Secretaria      │ Projetos │ Alertas │ Execução │ Transparência    │
├──────────────────────────────────────────────────────────────────────┤
│  Obras           │   32     │   5 🔴  │   68%    │   95% 🟢         │
│  Saúde           │   28     │   2 🟡  │   72%    │   88% 🟡         │
│  Educação        │   18     │   1 🟡  │   81%    │   92% 🟢         │
│  Assistência     │   12     │   0 🟢  │   75%    │   98% 🟢         │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  📈 RELATÓRIOS DISPONÍVEIS                                          │
├──────────────────────────────────────────────────────────────────────┤
│  📄 Relatório de Execução Orçamentária (Consolidado)                │
│  📄 Relatório de Convênios (Prestação de Contas)                    │
│  📄 Relatório de Emendas Parlamentares                              │
│  📄 Relatório de Transparência Passiva                              │
│  📄 Relatório de Atrasos e suas Causas                              │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 🔐 IMPLEMENTAÇÃO DA HIERARQUIA

### Tabela de Permissões

```sql
-- Tabela de perfis
CREATE TABLE dotp_perfis (
    perfil_id INT AUTO_INCREMENT PRIMARY KEY,
    perfil_nome VARCHAR(50) NOT NULL UNIQUE,
    perfil_descricao TEXT,
    perfil_nivel INT NOT NULL, -- 1=Prefeito, 2=Secretário, 3=Coordenador, 4=Técnico, 5=Controlador
    perfil_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO dotp_perfis (perfil_nome, perfil_nivel) VALUES
('prefeito', 1),
('secretario', 2),
('coordenador', 3),
('tecnico', 4),
('controlador', 5);

-- Vinculação usuário-perfil
ALTER TABLE dotp_users ADD COLUMN user_perfil_id INT NULL AFTER user_id;
ALTER TABLE dotp_users ADD COLUMN user_secretaria_id INT NULL;
ALTER TABLE dotp_users ADD COLUMN user_departamento_id INT NULL;

-- Tabela de permissões granulares
CREATE TABLE dotp_permissoes (
    permissao_id INT AUTO_INCREMENT PRIMARY KEY,
    permissao_perfil_id INT NOT NULL,
    permissao_recurso VARCHAR(100) NOT NULL, -- 'projeto', 'programa', 'relatorio'
    permissao_acao VARCHAR(50) NOT NULL,     -- 'visualizar', 'criar', 'editar', 'excluir', 'aprovar'
    permissao_escopo VARCHAR(50) NOT NULL,   -- 'todos', 'secretaria', 'proprio'
    FOREIGN KEY (permissao_perfil_id) REFERENCES dotp_perfis(perfil_id)
);

-- Permissões do Prefeito
INSERT INTO dotp_permissoes VALUES
(NULL, 1, 'projeto', 'visualizar', 'todos'),
(NULL, 1, 'projeto', 'aprovar', 'todos'),
(NULL, 1, 'programa', 'visualizar', 'todos'),
(NULL, 1, 'relatorio', 'visualizar', 'todos'),
(NULL, 1, 'alerta', 'visualizar', 'todos');

-- Permissões do Secretário
INSERT INTO dotp_permissoes VALUES
(NULL, 2, 'projeto', 'visualizar', 'secretaria'),
(NULL, 2, 'projeto', 'criar', 'secretaria'),
(NULL, 2, 'projeto', 'editar', 'secretaria'),
(NULL, 2, 'projeto', 'aprovar', 'secretaria'),
(NULL, 2, 'programa', 'visualizar', 'secretaria'),
(NULL, 2, 'programa', 'criar', 'secretaria'),
(NULL, 2, 'programa', 'editar', 'secretaria');

-- Permissões do Coordenador
INSERT INTO dotp_permissoes VALUES
(NULL, 3, 'projeto', 'visualizar', 'proprio'),
(NULL, 3, 'projeto', 'criar', 'secretaria'),
(NULL, 3, 'projeto', 'editar', 'proprio'),
(NULL, 3, 'programa', 'visualizar', 'proprio'),
(NULL, 3, 'tarefa', 'visualizar', 'equipe'),
(NULL, 3, 'tarefa', 'criar', 'equipe'),
(NULL, 3, 'tarefa', 'editar', 'equipe');
```

### Middleware de Autorização Hierárquica

```php
// src/Api/Middleware/HierarquiaMiddleware.php

class HierarquiaMiddleware {
    
    public function verificarAcesso(Usuario $user, string $recurso, string $acao, ?int $recursoId = null): bool {
        $perfil = $user->getPerfil();
        
        // Prefeito e Controlador veem tudo
        if (in_array($perfil, ['prefeito', 'controlador'])) {
            return true;
        }
        
        // Secretário só vê sua secretaria
        if ($perfil === 'secretario') {
            return $this->verificarSecretaria($user, $recursoId);
        }
        
        // Coordenador só vê seus projetos
        if ($perfil === 'coordenador') {
            return $this->verificarProjeto($user, $recursoId);
        }
        
        // Técnico só vê suas tarefas
        if ($perfil === 'tecnico') {
            return $this->verificarTarefa($user, $recursoId);
        }
        
        return false;
    }
    
    private function verificarSecretaria(Usuario $user, int $projetoId): bool {
        $db = Database::getInstance();
        $secretariaProjeto = $db->fetchColumn(
            "SELECT project_secretaria_id FROM dotp_projects WHERE project_id = ?",
            [$projetoId]
        );
        return $secretariaProjeto === $user->getSecretariaId();
    }
}
```

### Filtro de Dados no Repository

```php
// src/Repository/ProjetoRepository.php

public function findAllComFiltroHierarquia(Usuario $user, array $filtros = []): array {
    $sql = "SELECT p.* FROM dotp_projects p WHERE 1=1";
    $params = [];
    
    switch ($user->getPerfil()) {
        case 'prefeito':
        case 'controlador':
            // Sem filtro adicional
            break;
            
        case 'secretario':
            $sql .= " AND p.project_secretaria_id = ?";
            $params[] = $user->getSecretariaId();
            break;
            
        case 'coordenador':
            $sql .= " AND (p.project_responsavel_tecnico_id = ? OR p.project_coordenador_id = ?)";
            $params[] = $user->getId();
            $params[] = $user->getId();
            break;
            
        case 'tecnico':
            // Técnico só vê projetos onde tem tarefas
            $sql .= " AND p.project_id IN (
                SELECT task_project FROM dotp_tasks 
                WHERE task_responsavel_id = ?
            )";
            $params[] = $user->getId();
            break;
    }
    
    // Aplicar outros filtros
    if (!empty($filtros['status'])) {
        $sql .= " AND p.project_status = ?";
        $params[] = $filtros['status'];
    }
    
    return $this->db->fetchAll($sql, $params);
}
```

---

## 🏗️ ÁREA ADMINISTRATIVA - ESTRUTURA ORGANIZACIONAL

Esta seção define a **área de administração do sistema** onde o gestor/administrador pode cadastrar toda a estrutura organizacional da prefeitura, vincular responsáveis e gerar automaticamente os níveis de acesso.

### 🎯 Objetivo

Permitir que cada prefeitura configure seu próprio organograma administrativo de forma flexível, determinando:
- Quais são as secretarias
- Quais são os departamentos/coordenações dentro de cada secretaria
- Quem são os responsáveis em cada nível
- Quais permissões cada nível terá no sistema

### 📊 Organograma Dinâmico

```
┌─────────────────────────────────────────────────────────────────┐
│                    PREFEITURA MUNICIPAL                         │
├─────────────────────────────────────────────────────────────────┤
│  PREFEITO (Chefe do Executivo)                                  │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ SECRETARIA DE   │  │ SECRETARIA DE   │  │ SECRETARIA DE   │ │
│  │ OBRAS           │  │ SAÚDE           │  │ EDUCAÇÃO        │ │
│  ├─────────────────┤  ├─────────────────┤  ├─────────────────┤ │
│  │ • Gabinete      │  │ • Gabinete      │  │ • Gabinete      │ │
│  │ • Coord. Projetos│ │ • Vigilância    │  │ • Coord. Pedagógica│
│  │ • Coord. Obras  │  │ • Coord. APS    │  │ • Coord. Infra    │ │
│  │ • Departamento  │  │ • Departamento  │  │ • Departamento  │ │
│  │   Fiscalização  │  │   Regulação     │  │   Administração │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

> Cada prefeitura tem sua estrutura única - o sistema deve ser flexível para acomodar qualquer organização.

---

### 🏛️ Hierarquia Configurável

O administrador define os **níveis hierárquicos** da prefeitura:

| Nível | Descrição | Exemplos Típicos |
|-------|-----------|------------------|
| **Nível 1** | Chefe do Executivo | Prefeito, Vice-Prefeito |
| **Nível 2** | Secretarias | Secretário de Obras, Secretário de Saúde |
| **Nível 3** | Coordenações | Coordenador de Projetos, Coordenador de Convênios |
| **Nível 4** | Departamentos | Diretor de Fiscalização, Diretor de Regulação |
| **Nível 5** | Equipe Técnica | Técnicos, Engenheiros, Assistentes |

> **Nota:** O administrador pode criar mais ou menos níveis conforme a necessidade da prefeitura.

---

### 📋 MÓDULO: ADMINISTRAÇÃO DA ESTRUTURA

#### Tela 1: Configuração de Níveis Hierárquicos

```
┌─────────────────────────────────────────────────────────────────────┐
│  ⚙️ CONFIGURAÇÃO DE NÍVEIS HIERÁRQUICOS                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Defina os níveis da estrutura organizacional da prefeitura:        │
│                                                                     │
│  ┌─────────┬──────────────────────┬──────────────┬────────────────┐ │
│  │ Ordem   │ Nome do Nível        │ Padrão       │ Ações          │ │
│  ├─────────┼──────────────────────┼──────────────┼────────────────┤ │
│  │ 1       │ Prefeitura           │ Chefe do     │ ⬆️ ⬇️ ✏️ 🗑️   │ │
│  │         │                      │ Executivo    │                │ │
│  ├─────────┼──────────────────────┼──────────────┼────────────────┤ │
│  │ 2       │ Secretaria           │ Secretário   │ ⬆️ ⬇️ ✏️ 🗑️   │ │
│  ├─────────┼──────────────────────┼──────────────┼────────────────┤ │
│  │ 3       │ Coordenação          │ Coordenador  │ ⬆️ ⬇️ ✏️ 🗑️   │ │
│  ├─────────┼──────────────────────┼──────────────┼────────────────┤ │
│  │ 4       │ Departamento         │ Diretor      │ ⬆️ ⬇️ ✏️ 🗑️   │ │
│  ├─────────┼──────────────────────┼──────────────┼────────────────┤ │
│  │ 5       │ Equipe Técnica       │ Técnico      │ ⬆️ ⬇️ ✏️ 🗑️   │ │
│  └─────────┴──────────────────────┴──────────────┴────────────────┘ │
│                                                                     │
│  [+ Adicionar Novo Nível]                                           │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Campos por Nível:**
- `ordem` (1, 2, 3...) - Define a hierarquia
- `nome` - Ex: "Secretaria", "Coordenação"
- `titulo_responsavel` - Ex: "Secretário", "Coordenador"
- `descricao` - Função deste nível
- `cor` - Cor identificativa no organograma

---

#### Tela 2: Cadastro de Unidades Organizacionais

```
┌─────────────────────────────────────────────────────────────────────┐
│  🏢 CADASTRO DE UNIDADES ORGANIZACIONAIS                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  FILTROS:  [Nível ▼]  [Status ▼]  [🔍 Buscar...]             │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ORGANOGRAMA DA PREFEITURA                                          │
│  ═══════════════════════════                                        │
│                                                                     │
│  📁 PREFEITURA MUNICIPAL DE [NOME]                                  │
│  ├── 📂 Secretaria Municipal de Obras                               │
│  │   ├── 📂 Coordenação de Projetos                                │
│  │   │   ├── 📄 Departamento de Fiscalização                       │
│  │   │   └── 📄 Departamento de Planejamento                       │
│  │   ├── 📂 Coordenação de Obras                                   │
│  │   │   ├── 📄 Equipe de Pavimentação                             │
│  │   │   └── 📄 Equipe de Drenagem                                 │
│  │   └── 📄 Gabinete                                               │
│  │                                                                 │
│  ├── 📂 Secretaria Municipal de Saúde                               │
│  │   ├── 📂 Coordenação de Vigilância Sanitária                    │
│  │   ├── 📂 Coordenação de Atenção Primária                        │
│  │   └── 📄 Gabinete                                               │
│  │                                                                 │
│  └── 📂 Secretaria Municipal de Educação                            │
│      ├── 📂 Coordenação Pedagógica                                  │
│      ├── 📂 Coordenação de Infraestrutura                           │
│      └── 📄 Gabinete                                                │
│                                                                     │
│  [+ Nova Unidade]  [📥 Importar CSV]  [📤 Exportar Estrutura]       │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

#### Tela 3: Formulário de Cadastro de Unidade

```
┌─────────────────────────────────────────────────────────────────────┐
│  📝 NOVA UNIDADE ORGANIZACIONAL                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📊 NÍVEL HIERÁRQUICO *                                             │
│  [Secretaria                      ▼]                                │
│                                                                     │
│  📁 UNIDADE PAI (opcional)                                          │
│  [Prefeitura Municipal            ▼]                                │
│                                                                     │
│  🏷️ NOME DA UNIDADE *                                               │
│  [Secretaria Municipal de Obras.............................]       │
│                                                                     │
│  📝 SIGLA / CÓDIGO                                                  │
│  [SMO.......................................................]       │
│                                                                     │
│  📄 DESCRIÇÃO                                                       │
│  [Responsável pela execução de obras...                    ]        │
│  [                                                           ]      │
│                                                                     │
│  👤 RESPONSÁVEL MÁXIMO (Título)                                     │
│  [Secretário................................................]       │
│                                                                     │
│  📍 ENDEREÇO / LOCALIZAÇÃO                                          │
│  [Rua das Flores, 123 - Centro..............................]       │
│                                                                     │
│  📧 EMAIL INSTITUCIONAL                                             │
│  [obras@prefeitura.gov.br...................................]       │
│                                                                     │
│  📞 TELEFONE                                                        │
│  [(11) 3333-4444............................................]       │
│                                                                     │
│  ⚙️ CONFIGURAÇÕES DE ACESSO                                         │
│  ☑️ Esta unidade pode criar projetos                                │
│  ☑️ Esta unidade pode criar programas                               │
│  ☐ Esta unidade aprova pagamentos                                   │
│  ☐ Esta unidade aprova contratações                               │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  👥 VINCULAR USUÁRIOS A ESTA UNIDADE                          │  │
│  │                                                               │  │
│  │  Responsável Principal:                                       │  │
│  │  [João Silva - Secretário ▼]        [Definir como Gestor]     │  │
│  │                                                               │  │
│  │  Equipe:                                                      │  │
│  │  • Maria Santos - Coordenadora de Projetos    [❌]            │  │
│  │  • Pedro Costa - Coordenador de Obras         [❌]            │  │
│  │  • Ana Pereira - Diretora de Fiscalização     [❌]            │  │
│  │                                                               │  │
│  │  [+ Adicionar Usuário]                                        │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│            [Cancelar]        [💾 Salvar Rascunho]    [✅ Ativar]    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

#### Tela 4: Gestão de Usuários e Vínculos

```
┌─────────────────────────────────────────────────────────────────────┐
│  👥 GESTÃO DE USUÁRIOS E HIERARQUIA                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  🔍 Buscar usuário...  [                ]  [Buscar]         │    │
│  │                                                             │    │
│  │  FILTROS:  [Unidade ▼]  [Nível ▼]  [Status ▼]  [Perfil ▼]  │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ┌──────────┬──────────────────────┬─────────────────────┬────────┐ │
│  │ Usuário  │ Nome                 │ Vínculo Atual       │ Acesso │ │
│  ├──────────┼──────────────────────┼─────────────────────┼────────┤ │
│  │ 👤       │ João Silva           │ Secretário de Obras │ 🟢     │ │
│  │ 👤       │ Maria Santos         │ Coord. Projetos/SMO │ 🟢     │ │
│  │ 👤       │ Pedro Costa          │ Coord. Obras/SMO    │ 🟡     │ │
│  │ 👤       │ Ana Pereira          │ Dir. Fiscalização   │ 🟢     │ │
│  │ 👤       │ Carlos Souza         │ Técnico/Projetos    │ 🟢     │ │
│  │ 👤       │ Fernanda Lima        │ Não vinculado       │ ⚫     │ │
│  └──────────┴──────────────────────┴─────────────────────┴────────┘ │
│                                                                     │
│  LEGENDA: 🟢 Ativo  🟡 Pendente  ⚫ Sem vínculo  🔴 Bloqueado      │
│                                                                     │
│  [+ Novo Usuário]  [📤 Exportar Lista]  [🔄 Sincronizar AD]        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

#### Tela 5: Matriz de Permissões por Nível

```
┌─────────────────────────────────────────────────────────────────────┐
│  🔐 MATRIZ DE PERMISSÕES POR NÍVEL HIERÁRQUICO                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Configure automaticamente as permissões de cada nível:             │
│                                                                     │
│  ┌─────────────────┬──────────┬──────────┬──────────┬──────────┐   │
│  │ RECURSO / AÇÃO  │ Prefeito │Secretário│Coordenad.│  Técnico │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ PROJETOS                                                │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ Visualizar todos  │   ✅    │   🔶    │   🔷    │   🔹    │   │
│  │ Criar            │   ✅    │   ✅    │   ✅    │   ☐    │   │
│  │ Editar           │   ✅    │   🔶    │   🔷    │   ☐    │   │
│  │ Excluir          │   ✅    │   🔶    │   ☐    │   ☐    │   │
│  │ Aprovar          │   ✅    │   🔶    │   ☐    │   ☐    │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ PROGRAMAS                                               │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ Visualizar todos  │   ✅    │   🔶    │   🔷    │   🔹    │   │
│  │ Criar            │   ✅    │   ✅    │   ☐    │   ☐    │   │
│  │ Editar           │   ✅    │   🔶    │   ☐    │   ☐    │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ TAREFAS                                                 │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ Visualizar todas │   ✅    │   ✅    │   ✅    │   🔹    │   │
│  │ Atribuir         │   ✅    │   ✅    │   ✅    │   ☐    │   │
│  │ Executar         │   ✅    │   ✅    │   ✅    │   ✅    │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ RELATÓRIOS                                              │   │
│  ├─────────────────┼──────────┼──────────┼──────────┼──────────┤   │
│  │ Executivos       │   ✅    │   ☐    │   ☐    │   ☐    │   │
│  │ Gerenciais       │   ✅    │   ✅    │   ✅    │   ☐    │   │
│  │ Operacionais     │   ✅    │   ✅    │   ✅    │   ✅    │   │
│  └─────────────────┴──────────┴──────────┴──────────┴──────────┘   │
│                                                                     │
│  LEGENDA: ✅ Todos  🔶 Sua secretaria  🔷 Seus projetos            │
│           🔹 Atribuídos a você  ☐ Não tem acesso                   │
│                                                                     │
│  [🔄 Aplicar Permissões a Todos os Usuários]                        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

### 🔄 FLUXO DE GERAÇÃO DE ACESSOS

```
┌─────────────────────────────────────────────────────────────────────┐
│           FLUXO: DA ESTRUTURA AO ACESSO                             │
└─────────────────────────────────────────────────────────────────────┘

  1️⃣ CADASTRO DA ESTRUTURA                    2️⃣ VINCULAÇÃO DE USUÁRIOS
  ┌─────────────────────┐                    ┌─────────────────────┐
  │ • Definir níveis    │                    │ • Selecionar usuário│
  │ • Criar unidades    │                    │ • Atribuir unidade  │
  │ • Organizar hierarquia│                  │ • Definir cargo     │
  └──────────┬──────────┘                    └──────────┬──────────┘
           │                                          │
           ▼                                          ▼
  3️⃣ CONFIGURAÇÃO DE PERMISSÕES              4️⃣ GERAÇÃO AUTOMÁTICA
  ┌─────────────────────┐                    ┌─────────────────────┐
  │ • Definir o que cada│                    │ • Perfil criado     │
  │   nível pode fazer  │                    │ • Permissões aplicadas│
  │ • Escopo de dados   │                    │ • Dashboard definido│
  └──────────┬──────────┘                    └──────────┬──────────┘
           │                                          │
           └──────────────────┬───────────────────────┘
                              ▼
                   ┌─────────────────────┐
                   │  ✅ USUÁRIO PRONTO  │
                   │  • Login ativo      │
                   │  • Acesso limitado  │
                   │    à sua hierarquia │
                   └─────────────────────┘
```

---

### 📊 MODELO DE DADOS - ESTRUTURA ORGANIZACIONAL

```sql
-- Tabela de Níveis Hierárquicos (configurável)
CREATE TABLE dotp_niveis_hierarquicos (
    nivel_id INT AUTO_INCREMENT PRIMARY KEY,
    nivel_ordem INT NOT NULL UNIQUE, -- 1, 2, 3...
    nivel_nome VARCHAR(100) NOT NULL, -- "Secretaria", "Coordenação"
    nivel_titulo_responsavel VARCHAR(100), -- "Secretário", "Coordenador"
    nivel_descricao TEXT,
    nivel_cor VARCHAR(7) DEFAULT '#007bff',
    nivel_ativo BOOLEAN DEFAULT TRUE,
    nivel_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Unidades Organizacionais
CREATE TABLE dotp_unidades_organizacionais (
    unidade_id INT AUTO_INCREMENT PRIMARY KEY,
    unidade_nivel_id INT NOT NULL,
    unidade_pai_id INT NULL, -- auto-relacionamento
    unidade_nome VARCHAR(255) NOT NULL,
    unidade_sigla VARCHAR(50),
    unidade_descricao TEXT,
    unidade_endereco VARCHAR(255),
    unidade_email VARCHAR(100),
    unidade_telefone VARCHAR(20),
    unidade_responsavel_id INT NULL, -- usuário responsável principal
    unidade_pode_criar_projetos BOOLEAN DEFAULT TRUE,
    unidade_pode_criar_programas BOOLEAN DEFAULT FALSE,
    unidade_aprova_pagamentos BOOLEAN DEFAULT FALSE,
    unidade_aprova_contratacoes BOOLEAN DEFAULT FALSE,
    unidade_status ENUM('ativo', 'inativo', 'em_reorganizacao') DEFAULT 'ativo',
    unidade_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unidade_updated_at TIMESTAMP NULL,
    FOREIGN KEY (unidade_nivel_id) REFERENCES dotp_niveis_hierarquicos(nivel_id),
    FOREIGN KEY (unidade_pai_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (unidade_responsavel_id) REFERENCES dotp_users(user_id)
);

-- Tabela de Vínculo Usuário-Unidade (um usuário pode estar em várias unidades)
CREATE TABLE dotp_usuario_unidades (
    vinculo_id INT AUTO_INCREMENT PRIMARY KEY,
    vinculo_user_id INT NOT NULL,
    vinculo_unidade_id INT NOT NULL,
    vinculo_cargo VARCHAR(100), -- "Secretário", "Coordenador Adj"
    vinculo_nivel_acesso INT DEFAULT 3, -- 1=Total, 2=Escrita, 3=Leitura
    vinculo_is_principal BOOLEAN DEFAULT FALSE, -- unidade principal do usuário
    vinculo_data_inicio DATE,
    vinculo_data_fim DATE NULL,
    vinculo_status ENUM('ativo', 'afastado', 'substituto', 'inativo') DEFAULT 'ativo',
    vinculo_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vinculo_user_id) REFERENCES dotp_users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vinculo_unidade_id) REFERENCES dotp_unidades_organizacionais(unidade_id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_unidade (vinculo_user_id, vinculo_unidade_id)
);

-- Tabela de Permissões por Nível (template)
CREATE TABLE dotp_permissoes_nivel (
    permissao_id INT AUTO_INCREMENT PRIMARY KEY,
    permissao_nivel_id INT NOT NULL,
    permissao_recurso VARCHAR(100) NOT NULL, -- 'projeto', 'programa', 'tarefa'
    permissao_acao VARCHAR(50) NOT NULL, -- 'visualizar', 'criar', 'editar', 'excluir'
    permissao_escopo VARCHAR(50) NOT NULL, -- 'todos', 'unidade', 'subordinados', 'proprio'
    permissao_ativo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (permissao_nivel_id) REFERENCES dotp_niveis_hierarquicos(nivel_id)
);

-- Tabela de Histórico de Movimentações (quando usuário muda de unidade)
CREATE TABLE dotp_historico_movimentacoes (
    historico_id INT AUTO_INCREMENT PRIMARY KEY,
    historico_user_id INT NOT NULL,
    historico_unidade_origem_id INT,
    historico_unidade_destino_id INT NOT NULL,
    historico_cargo_anterior VARCHAR(100),
    historico_cargo_novo VARCHAR(100),
    historico_tipo_movimentacao ENUM('promocao', 'remocao', 'exoneracao', 'reintegracao'),
    historico_data_movimentacao DATE,
    historico_observacao TEXT,
    historico_responsavel_id INT, -- quem fez a movimentação
    FOREIGN KEY (historico_user_id) REFERENCES dotp_users(user_id),
    FOREIGN KEY (historico_unidade_origem_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (historico_unidade_destino_id) REFERENCES dotp_unidades_organizacionais(unidade_id)
);
```

---

### 🔧 API - ENDPOINTS DE ADMINISTRAÇÃO

```php
// Administração da Estrutura Organizacional

// Níveis Hierárquicos
GET    /api/v1/admin/niveis                    // Lista níveis
POST   /api/v1/admin/niveis                    // Cria novo nível
PUT    /api/v1/admin/niveis/{id}               // Atualiza nível
DELETE /api/v1/admin/niveis/{id}               // Remove nível
PUT    /api/v1/admin/niveis/{id}/reordenar     // Reordena níveis

// Unidades Organizacionais
GET    /api/v1/admin/unidades                  // Lista unidades (com hierarquia)
GET    /api/v1/admin/unidades/arvore           // Retorna árvore completa
POST   /api/v1/admin/unidades                  // Cria unidade
GET    /api/v1/admin/unidades/{id}             // Detalhes da unidade
PUT    /api/v1/admin/unidades/{id}             // Atualiza unidade
DELETE /api/v1/admin/unidades/{id}             // Remove unidade
GET    /api/v1/admin/unidades/{id}/usuarios    // Lista usuários da unidade
GET    /api/v1/admin/unidades/{id}/subordinadas // Lista unidades subordinadas

// Vínculos Usuário-Unidade
GET    /api/v1/admin/vinculos                  // Lista vínculos
POST   /api/v1/admin/vinculos                  // Cria vínculo
PUT    /api/v1/admin/vinculos/{id}             // Atualiza vínculo
DELETE /api/v1/admin/vinculos/{id}             // Remove vínculo
PUT    /api/v1/admin/vinculos/{id}/principal   // Define como unidade principal

// Permissões
GET    /api/v1/admin/permissoes/matriz         // Matriz de permissões
PUT    /api/v1/admin/permissoes/matriz         // Atualiza matriz
POST   /api/v1/admin/permissoes/aplicar        // Aplica permissões a todos usuários

// Importação/Exportação
POST   /api/v1/admin/importar/csv              // Importa estrutura via CSV
GET    /api/v1/admin/exportar/csv              // Exporta estrutura para CSV
GET    /api/v1/admin/exportar/pdf              // Exporta organograma em PDF

// Dashboard Admin
GET    /api/v1/admin/dashboard                 // Métricas da estrutura
       // Retorna: total_unidades, usuarios_ativos, usuarios_sem_vinculo, etc.
```

---

### 💻 COMPONENTES REACT - ÁREA ADMIN

```
frontend/src/pages/admin/
├── AdminLayout.jsx              // Layout com menu lateral
├── DashboardAdmin.jsx           // Dashboard geral da administração
├── niveis/
│   ├── NiveisList.jsx          // Lista de níveis hierárquicos
│   ├── NivelForm.jsx           // Formulário de nível
│   └── NivelReordenar.jsx      // Drag-and-drop para reordenar
├── unidades/
│   ├── UnidadesTree.jsx        // Visualização em árvore
│   ├── UnidadesList.jsx        // Lista tabular
│   ├── UnidadeForm.jsx         // Formulário de unidade
│   └── UnidadeDetalhe.jsx      // Detalhes e usuários vinculados
├── usuarios/
│   ├── UsuariosList.jsx        // Lista de usuários
│   ├── UsuarioForm.jsx         // Formulário de usuário
│   ├── UsuarioVinculos.jsx     // Gerenciamento de vínculos
│   └── UsuarioImportar.jsx     // Importação em massa
├── permissoes/
│   ├── MatrizPermissoes.jsx    // Matriz visual de permissões
│   └── PermissoesAplicar.jsx   // Aplicação de permissões
└── organograma/
    └── OrganogramaVisual.jsx   // Visualização gráfica da estrutura
```

---

### 📋 CHECKLIST - MÓDULO ADMINISTRATIVO

- [ ] Criar tabela `dotp_niveis_hierarquicos`
- [ ] Criar tabela `dotp_unidades_organizacionais`
- [ ] Criar tabela `dotp_usuario_unidades`
- [ ] Criar tabela `dotp_permissoes_nivel`
- [ ] Criar tabela `dotp_historico_movimentacoes`
- [ ] Criar endpoints da API de administração
- [ ] Desenvolver tela de configuração de níveis
- [ ] Desenvolver tela de cadastro de unidades (árvore)
- [ ] Desenvolver tela de vínculo de usuários
- [ ] Desenvolver matriz de permissões
- [ ] Implementar geração automática de acessos
- [ ] Criar visualização do organograma
- [ ] Implementar importação/exportação CSV
- [ ] Criar dashboard administrativo
- [ ] Desenvolver relatório de estrutura organizacional

---

### Visão Executiva - Painel de Avião

**Sem jargão técnico. Dados brutos e claros.**

#### Seção 1: Indicadores Principais

```
┌─────────────────────────────────────────────────────────┐
│  PPA EM EXECUÇÃO                              73%       │
│  ████████████████████░░░░░                              │
│  45 de 62 programas ativos                              │
└─────────────────────────────────────────────────────────┘

┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
│  🔴 12     │ │  🟡 23     │ │  🟢 89     │ │  💰 R$ 5M  │
│  Travados  │ │  Atenção   │ │  Em dia    │ │  em risco  │
│  (-2)      │ │  (+5)      │ │  (+12)     │ │  (2 conv.) │
└────────────┘ └────────────┘ └────────────┘ └────────────┘
```

#### Seção 2: Projetos Travados por Secretaria

| Secretaria | Travados | Principal Motivo |
|------------|----------|------------------|
| Obras | 5 | Falta projeto técnico |
| Saúde | 4 | Falta empresa (licitação) |
| Educação | 3 | Falta empenho |

#### Seção 3: Emendas Parlamentares

```
Recebidas:      R$ 12.000.000
Executadas:     R$  7.200.000 (60%)
Em risco:       R$  2.400.000 (20% - vencem em 60 dias)
Não executadas: R$  2.400.000 (20%)
```

#### Seção 4: Obras em Atraso (Top 5)

| Obra | Previsão | Real | Atraso | Motivo |
|------|----------|------|--------|--------|
| Escola Jardim | 90 dias | 150 dias | 60 dias | Chuvas |
| Asfalto Rua X | 45 dias | 80 dias | 35 dias | Falta empresa |

#### Seção 5: Timeline dos Próximos 30 Dias

```
Hoje ──┬── 07/02: Fim vigência Convênio Saúde #123
       ├── 15/02: Entrega obra Escola Jardim
       ├── 20/02: Prazo final Emenda Dep. Silva
       └── 28/02: Fim exercício orçamentário
```

---

## 🔧 ADAPTAÇÃO TÉCNICA DO SISTEMA

### Novas Entidades/Modelos

```php
// src/Entity/Programa.php
class Programa extends Project {
    const TIPO = 'programa';
    
    protected array $campos_adicionais = [
        'objetivo_estrategico',
        'vinculacao_ppa',
        'indicadores',
        'secretaria_lider_id',
        'responsavel_politico_id',
        'responsavel_tecnico_id',
    ];
}

// src/Entity/ProjetoPublico.php
class ProjetoPublico extends Project {
    const TIPOS = ['obra', 'politica_publica', 'convenio', 'emenda'];
    
    protected array $campos_adicionais = [
        'tipo_projeto',
        'programa_id',
        'secretaria_id',
        'fonte_recurso',
        'numero_convenio',
        'numero_emenda',
        'parlamentar',
        'concedente',
        'vigencia_inicio',
        'vigencia_fim',
        'valor_empenhado',
        'valor_pago',
        'situacao_orcamentaria',
        'etapa_atual',
        'motivo_atraso',
    ];
}

// src/Entity/Acao.php (extensão de Task)
class Acao extends Task {
    protected array $campos_adicionais = [
        'etapa_ordem',
        'data_prevista_inicio',
        'data_prevista_fim',
        'data_real_inicio',
        'data_real_fim',
        'valor_orcado',
        'valor_executado',
    ];
}
```

### Novas Tabelas no Banco

```sql
-- dotp_programas (herda de projects com flag tipo='programa')
ALTER TABLE dotp_projects ADD COLUMN project_tipo ENUM('projeto', 'programa') DEFAULT 'projeto';

-- Novos campos para projetos públicos
ALTER TABLE dotp_projects ADD COLUMN (
    project_tipo_publico ENUM('obra', 'politica_publica', 'convenio', 'emenda') NULL,
    project_programa_id INT NULL,
    project_secretaria_id INT NULL,
    project_fonte_recurso VARCHAR(50) NULL,
    project_numero_convenio VARCHAR(50) NULL,
    project_numero_emenda VARCHAR(50) NULL,
    project_parlamentar VARCHAR(255) NULL,
    project_concedente VARCHAR(255) NULL,
    project_vigencia_inicio DATE NULL,
    project_vigencia_fim DATE NULL,
    project_valor_empenhado DECIMAL(15,2) DEFAULT 0,
    project_valor_pago DECIMAL(15,2) DEFAULT 0,
    project_situacao_orcamentaria VARCHAR(50) NULL,
    project_motivo_atraso VARCHAR(50) NULL
);

-- Tabela de etapas/timeline
CREATE TABLE dotp_projeto_etapas (
    etapa_id INT AUTO_INCREMENT PRIMARY KEY,
    etapa_project_id INT NOT NULL,
    etapa_nome VARCHAR(100) NOT NULL,
    etapa_ordem INT NOT NULL,
    etapa_data_prevista_inicio DATE NULL,
    etapa_data_prevista_fim DATE NULL,
    etapa_data_real_inicio DATE NULL,
    etapa_data_real_fim DATE NULL,
    etapa_status VARCHAR(50) DEFAULT 'nao_iniciada',
    etapa_responsavel_id INT NULL,
    etapa_justificativa TEXT NULL,
    FOREIGN KEY (etapa_project_id) REFERENCES dotp_projects(project_id),
    FOREIGN KEY (etapa_responsavel_id) REFERENCES dotp_users(user_id)
);

-- Tabela de alertas
CREATE TABLE dotp_alertas (
    alerta_id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_tipo VARCHAR(50) NOT NULL,
    alerta_titulo VARCHAR(255) NOT NULL,
    alerta_descricao TEXT,
    alerta_project_id INT NULL,
    alerta_task_id INT NULL,
    alerta_destinatario_id INT NOT NULL,
    alerta_data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    alerta_data_leitura TIMESTAMP NULL,
    alerta_lido BOOLEAN DEFAULT FALSE,
    alerta_prioridade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media'
);
```

### API Endpoints Adicionais

```php
// Rotas específicas para gestão pública

// Dashboard Executivo
GET /api/v1/prefeitura/dashboard

// Programas
GET    /api/v1/programas
POST   /api/v1/programas
GET    /api/v1/programas/{id}
PUT    /api/v1/programas/{id}
GET    /api/v1/programas/{id}/projetos

// Projetos Públicos (com filtros específicos)
GET    /api/v1/projetos-publicos
GET    /api/v1/projetos-publicos?fonte_recurso=emenda
GET    /api/v1/projetos-publicos?status=travado
GET    /api/v1/projetos-publicos?secretaria_id=5

// Timeline/Etapas
GET    /api/v1/projetos/{id}/etapas
POST   /api/v1/projetos/{id}/etapas
PUT    /api/v1/etapas/{id}
PUT    /api/v1/etapas/{id}/concluir

// Alertas
GET    /api/v1/alertas
PUT    /api/v1/alertas/{id}/ler
GET    /api/v1/alertas/nao-lidos/count

// Relatórios
GET    /api/v1/relatorios/ppa-execucao
GET    /api/v1/relatorios/emendas
GET    /api/v1/relatorios/convenios-a-vencer
```

---

## 🎨 INTERFACE DO USUÁRIO

### Novas Telas

1. **Dashboard Executivo** - Visão do Prefeito
2. **Listagem de Programas** - Hierarquia PPA
3. **Detalhe do Programa** - Com indicadores
4. **Listagem de Projetos Públicos** - Com filtros avançados
5. **Timeline do Projeto** - Visual horizontal interativo
6. **Gestão de Emendas** - Painel específico
7. **Gestão de Convênios** - Alertas de vigência
8. **Configuração de Alertas** - Regras do sistema

### Componentes React Adicionais

```jsx
// components/prefeitura/
├── DashboardExecutivo.jsx
├── ProgramaCard.jsx
├── TimelineProjeto.jsx
├── EtapaProgress.jsx
├── AlertaBadge.jsx
├── IndicadorChart.jsx
├── ResumoEmendas.jsx
├── TabelaObras.jsx
└── FiltroFonteRecurso.jsx
```

---

## 📈 IMPLEMENTAÇÃO POR FASES

### Fase 0: Estrutura Organizacional (2-3 semanas) ⭐ **FUNDAMENTAL**
> **Sem esta fase, o sistema não funciona.** A estrutura organizacional é a base de toda a hierarquia de acesso.

- [ ] Desenvolver **Área Administrativa**
- [ ] Cadastrar níveis hierárquicos da prefeitura
- [ ] Cadastrar todas as unidades organizacionais
- [ ] Vincular usuários às suas unidades
- [ ] Configurar matriz de permissões por nível
- [ ] Gerar acessos automaticamente
- [ ] Testar hierarquia e permissões

**Entregável:** Estrutura organizacional completa cadastrada e funcionando.

### Fase 1: Módulos de Gestão (2-3 semanas)
- [ ] Criar tabelas adicionais no banco (Programas, Projetos Públicos)
- [ ] Estender entidades Project e Task
- [ ] Criar API endpoints básicos
- [ ] Adaptar dashboard atual

### Fase 2: Gestão de Projetos Públicos (2-3 semanas)
- [ ] Tela de programas
- [ ] Tela de projetos públicos com filtros
- [ ] Timeline visual
- [ ] Upload de documentos por etapa

### Fase 3: Financeiro e Alertas (2 semanas)
- [ ] Controle de fontes de recurso
- [ ] Sistema de alertas
- [ ] Notificações por email
- [ ] Dashboard executivo completo (integrado com estrutura)

### Fase 4: Relatórios e Integrações (2 semanas)
- [ ] Relatórios gerenciais
- [ ] Exportação para SICOM/TCU
- [ ] Integração com sistema contábil (futuro)

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### FASE 1: Estrutura Organizacional (Base de Tudo)
- [ ] Criar tabela `dotp_niveis_hierarquicos`
- [ ] Criar tabela `dotp_unidades_organizacionais`
- [ ] Criar tabela `dotp_usuario_unidades`
- [ ] Criar tabela `dotp_permissoes_nivel`
- [ ] Desenvolver **Área Administrativa** - Configuração de Níveis
- [ ] Desenvolver **Área Administrativa** - Cadastro de Unidades (árvore)
- [ ] Desenvolver **Área Administrativa** - Vínculo de Usuários
- [ ] Desenvolver **Área Administrativa** - Matriz de Permissões
- [ ] Implementar geração automática de acessos
- [ ] Criar visualização do organograma
- [ ] Implementar importação/exportação CSV de estrutura

### FASE 2: Módulos de Gestão
- [ ] Criar migration das tabelas de Programas e Projetos Públicos
- [ ] Implementar entidades Programa e ProjetoPublico
- [ ] Criar endpoints da API de gestão
- [ ] Desenvolver componentes React de gestão
- [ ] Implementar timeline visual
- [ ] Criar sistema de alertas
- [ ] Criar relatórios específicos

### FASE 3: Dashboards por Perfil
- [ ] Implementar middleware de autorização hierárquica
- [ ] Criar filtros de dados por nível hierárquico (baseado na estrutura)
- [ ] Desenvolver **Dashboard do Prefeito** (Visão Geral)
- [ ] Desenvolver **Dashboard do Secretário** (Por Secretaria)
- [ ] Desenvolver **Dashboard do Coordenador** (Por Projetos)
- [ ] Desenvolver **Dashboard do Técnico** (Por Tarefas)
- [ ] Desenvolver **Dashboard do Controlador** (Fiscalização)
- [ ] Configurar permissões dinâmicas baseadas na estrutura

### FASE 4: Lançamento
- [ ] Testes com usuários (prefeitura piloto)
- [ ] Documentação de uso
- [ ] Treinamento da equipe de TI (área admin)
- [ ] Treinamento por perfil (prefeito, secretário, etc.)

---

## 🏆 BENEFÍCIOS ESPERADOS

| Métrica | Antes | Depois | Impacto |
|---------|-------|--------|---------|
| Tempo para relatório PPA | 5 dias | 5 min | -99% |
| Projetos sem acompanhamento | 40% | <5% | +88% |
| Recursos perdidos (convênios) | R$ 2M/ano | R$ 0 | Economia total |
| Tempo de resposta a problemas | 30 dias | 24h | -92% |
| Satisfação do gestor | N/A | >80% | Qualidade |

---

## 📞 PRÓXIMOS PASSOS

1. **Reunião de Alinhamento** - Validar escopo com equipe da prefeitura
2. **Definição de Piloto** - Escolher 1 secretaria para teste
3. **Carga de Dados** - Importar PPA e projetos existentes
4. **Treinamento** - Capacitar usuários-chave
5. **Go Live** - Lançamento gradual

---

**Documento elaborado para direcionamento estratégico do sistema dotProject para o segmento de gestão pública municipal.**

*"Tecnologia a serviço da transparência e eficiência administrativa"*
