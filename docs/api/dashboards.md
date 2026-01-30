# API de Dashboards

## Visao geral
Os endpoints abaixo entregam dados consolidados por perfil de acesso (prefeito, secretario, coordenador, tecnico e controlador). O endpoint base faz o auto-detect pelo usuario autenticado.

## Autenticacao
Envie o token no header:
- Authorization: Bearer <token>

## Endpoints

### GET /api/v1/dashboard
Auto-detecta o perfil do usuario logado e retorna o dashboard correspondente.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "prefeito",
    "ppa_execucao": {
      "total_programas": 62,
      "concluidos": 18,
      "criticos": 4,
      "atencao": 9,
      "percentual_medio": 73.2
    },
    "projetos_status": [
      { "estado": "Em_Dia", "total": 89, "valor_total": 12000000 }
    ],
    "por_secretaria": [
      { "unidade_id": 2, "unidade_nome": "Obras", "unidade_sigla": "OBR", "total_projetos": 12, "atrasados": 3, "concluidos": 5, "percentual_execucao": 64.5 }
    ],
    "obras_atrasadas": [],
    "convenios_vencer": [],
    "emendas": { "total": 12, "executadas": 6, "em_risco": 2 },
    "timeline_30dias": [],
    "orcamento": { "previsto": 18000000, "empenhado": 6000000, "pago": 4200000, "percentual_executado": 23.33 }
  }
}
```

Erros comuns:
- 401 Unauthorized: token ausente ou invalido
- 403 Forbidden: usuario sem escopo valido

---

### GET /api/v1/dashboard/prefeito
Dashboard executivo com visao geral da prefeitura.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (campos principais):
- perfil
- ppa_execucao (total_programas, concluidos, criticos, atencao, percentual_medio)
- projetos_status (lista por estado)
- por_secretaria
- obras_atrasadas
- convenios_vencer
- emendas (total, executadas, em_risco)
- timeline_30dias
- orcamento (previsto, empenhado, pago, percentual_executado)

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "prefeito",
    "ppa_execucao": { "total_programas": 62, "concluidos": 18, "criticos": 4, "atencao": 9, "percentual_medio": 73.2 },
    "projetos_status": [ { "estado": "Atrasado", "total": 12, "valor_total": 5200000 } ],
    "por_secretaria": [ { "unidade_id": 2, "unidade_nome": "Saude", "unidade_sigla": "SAU", "total_projetos": 8, "atrasados": 2, "concluidos": 3, "percentual_execucao": 58.4 } ],
    "obras_atrasadas": [ { "id": 101, "nome": "Escola Jardim", "estado": "Atrasado", "percent_execucao": 35, "data_prevista_fim": "2026-02-15", "justificativa_atraso": "Chuvas", "secretaria": "Educacao", "etapa_atual": "Execucao", "etapa_estado": "Atrasada", "dias_atraso": 20 } ],
    "convenios_vencer": [ { "id": 88, "nome": "Convenio Saude 123", "data_prevista_fim": "2026-03-10", "valor_previsto": 800000, "unidade_nome": "Saude", "dias_restantes": 39 } ],
    "emendas": { "total": 12, "executadas": 6, "em_risco": 2 },
    "timeline_30dias": [ { "id": 88, "nome": "Convenio Saude 123", "data": "2026-03-10", "tipo_evento": "vencimento", "unidade_nome": "Saude" } ],
    "orcamento": { "previsto": 18000000, "empenhado": 6000000, "pago": 4200000, "percentual_executado": 23.33 }
  }
}
```

---

### GET /api/v1/dashboard/secretario
Dashboard da secretaria do usuario logado.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "secretario",
    "unidade_id": 2,
    "programas": [ { "id": 10, "nome": "Mobilidade", "estado": "Em_Andamento", "percent_execucao": 41.2, "total_projetos": 7 } ],
    "projetos_resumo": [ { "estado": "Atrasado", "total": 3 } ],
    "projetos_atencao": [ { "id": 101, "nome": "Escola Jardim", "estado": "Atrasado", "percent_execucao": 35, "programa_nome": "Educacao", "etapa_atual": "Execucao", "etapa_estado": "Atrasada", "data_prevista_fim": "2026-02-15", "tipo_atencao": "atrasado" } ],
    "coordenadores": [ { "user_id": 21, "nome": "Ana Souza", "total_projetos": 5, "percentual_medio": 63.2, "atrasados": 1 } ],
    "alertas": { "total": 12, "nao_lidos": 4, "criticos": 1 }
  }
}
```

---

### GET /api/v1/dashboard/coordenador
Dashboard do coordenador com foco em projetos e etapas.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "coordenador",
    "resumo": { "total_projetos": 9, "concluidos": 2, "atrasados": 1, "em_andamento": 6, "percentual_medio": 57.4 },
    "projetos": [ { "id": 101, "nome": "Escola Jardim", "tipo": "Obra", "estado": "Atrasado", "percent_execucao": 35, "data_prevista_inicio": "2025-11-01", "data_prevista_fim": "2026-02-15", "programa_nome": "Educacao", "etapa_atual": "Execucao", "etapa_estado": "Atrasada", "etapa_prazo": "2026-02-15", "etapa_progresso": 40 } ],
    "etapas_atencao": [ { "id": 301, "nome": "Medição", "estado": "Proximo_Prazo", "data_prevista_fim": "2026-02-05", "percent_conclusao": 60, "projeto_id": 101, "projeto_nome": "Escola Jardim", "dias_restantes": 6 } ],
    "proximas_etapas": [ { "projeto": "UBS Centro", "etapa": "Licitação", "prazo": "2026-02-03" } ],
    "equipe": [ { "user_id": 55, "nome": "Carlos Lima", "tarefas_ativas": 4 } ]
  }
}
```

---

### GET /api/v1/dashboard/tecnico
Dashboard do tecnico com foco em tarefas e produtividade.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "tecnico",
    "resumo": { "total": 14, "concluidas": 6, "pendentes": 6, "bloqueadas": 1, "atrasadas": 2 },
    "tarefas_prioritarias": [ { "id": 9001, "nome": "Revisar projeto", "estado": "Em_Andamento", "prioridade": 2, "progresso": 40, "prazo": "2026-02-02", "projeto": "Escola Jardim", "urgencia": "urgente" } ],
    "concluidas_semana": [ { "id": 9000, "nome": "Memorial descritivo", "data_conclusao": "2026-01-27" } ],
    "projetos": [ { "id": 101, "nome": "Escola Jardim", "estado": "Atrasado", "percent_execucao": 35, "minhas_tarefas": 3, "tarefas_concluidas": 1 } ],
    "produtividade_30d": [ { "data": "2026-01-29", "tarefas_concluidas": 2 } ]
  }
}
```

---

### GET /api/v1/dashboard/controlador
Dashboard de fiscalizacao e conformidade.

Headers:
- Authorization: Bearer <token>

Query params:
- Nenhum

Response 200 (exemplo):
```json
{
  "data": {
    "perfil": "controlador",
    "alertas_conformidade": { "sem_prestacao_contas": 3, "execucao_acima_cronograma": 1, "diferenca_empenho_execucao": 2 },
    "panorama_secretarias": [ { "unidade_id": 2, "unidade_nome": "Obras", "total_projetos": 12, "alertas": 3, "execucao_media": 52.1, "com_documentacao": 9 } ],
    "irregularidades": [ { "id": 77, "nome": "Convenio XYZ", "estado": "Concluido", "percent_execucao": 100, "situacao_orcamentaria": "pago", "secretaria": "Saude", "irregularidade": "Sem prestacao de contas" } ],
    "convenios_prestacao_pendente": [ { "id": 77, "nome": "Convenio XYZ", "data_prevista_fim": "2025-10-10", "dias_pendentes": 112, "unidade_nome": "Saude" } ]
  }
}
```

---

### GET /api/v1/dashboard/alertas
Lista alertas do usuario logado e retorna estatisticas.

Headers:
- Authorization: Bearer <token>

Query params:
- nao_lidos (boolean, default: false)
- limit (int, default: 50)

Response 200 (exemplo):
```json
{
  "data": [
    { "id": 501, "titulo": "Convenio a vencer", "descricao": "Faltam 30 dias", "prioridade": "alta", "lido": false, "data_criacao": "2026-01-29", "projeto": "Convenio Saude 123" }
  ],
  "estatisticas": { "total": 12, "nao_lidos": 4, "criticos": 1 }
}
```

Erros comuns:
- 401 Unauthorized

---

### PUT /api/v1/dashboard/alertas/{id}/lido
Marca um alerta como lido.

Headers:
- Authorization: Bearer <token>

Response 200 (exemplo):
```json
{ "message": "Alerta marcado como lido" }
```

Erros comuns:
- 401 Unauthorized
- 404 Not Found: alerta inexistente ou nao pertence ao usuario

---

### PUT /api/v1/dashboard/alertas/lidos
Marca todos os alertas do usuario como lidos.

Headers:
- Authorization: Bearer <token>

Response 200 (exemplo):
```json
{ "message": "Todos os alertas marcados como lidos" }
```

Erros comuns:
- 401 Unauthorized
