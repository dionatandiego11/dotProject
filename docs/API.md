# API - dotProject Prefeituras

Base URL (local): `http://localhost:8088/api.php/v1`

## Autenticacao

### POST /auth/login
Autentica usuario e retorna token JWT.

Request:
```json
{
  "username": "admin",
  "password": "admin"
}
```

Response 200:
```json
{
  "success": true,
  "token": "<jwt>",
  "user": {
    "id": 1,
    "nome": "Administrador",
    "perfil": "prefeito"
  }
}
```

Erros:
- 401: credenciais invalidas
- 422: campos obrigatorios faltando

### GET /auth/me
Retorna dados do usuario autenticado.

Headers:
```
Authorization: Bearer <jwt>
```

### POST /auth/refresh
Renova o token JWT.

---

## Dashboards

> Todas as rotas exigem JWT via header Authorization.

### GET /dashboard
Auto-detecta o perfil e retorna o dashboard correspondente.

### GET /dashboard/prefeito
### GET /dashboard/secretario
### GET /dashboard/coordenador
### GET /dashboard/tecnico
### GET /dashboard/controlador

Resposta (exemplo simplificado):
```json
{
  "success": true,
  "indicadores": {
    "projetos_ativos": 12,
    "projetos_atrasados": 3
  },
  "listas": {
    "alertas": []
  }
}
```

Docs detalhadas: `docs/api/dashboards.md`

---

## Admin - Estrutura Organizacional

### GET /admin/onboarding/readiness
Retorna checklist de prontidao de implantacao (niveis, unidades, usuarios, vinculos e fluxo piloto).

### GET /admin/niveis
Lista niveis hierarquicos.

### POST /admin/niveis
Cria novo nivel.

### PUT /admin/niveis/{id}
Atualiza nivel.

### DELETE /admin/niveis/{id}
Remove nivel.

### GET /admin/unidades
Lista unidades organizacionais.

### GET /admin/unidades/arvore
Retorna arvore completa de unidades.

### POST /admin/unidades
Cria unidade.

### GET /admin/unidades/{id}
Detalhes da unidade.

### PUT /admin/unidades/{id}
Atualiza unidade.

### DELETE /admin/unidades/{id}
Remove unidade.

### GET /admin/vinculos
Lista vinculos usuario-unidade.

### POST /admin/vinculos
Cria vinculo.

### PUT /admin/vinculos/{id}
Atualiza vinculo.

### DELETE /admin/vinculos/{id}
Remove vinculo.

---

## Padroes

### Headers padrao
```
Authorization: Bearer <jwt>
Content-Type: application/json
```

### Resposta padrao
```json
{
  "success": true,
  "data": {}
}
```
