# Unidade vs Company Contract (API)

Date: 2026-02-09
Status: active

## Purpose
Define one canonical field for organizational scope in modern API payloads and keep legacy compatibility.

## Canonical rule
1. Canonical request/response field: `unidade_id`
2. Legacy compatibility field: `company_id`
3. When both are present, `unidade_id` is source of truth.
4. In responses, both fields should carry the same value when available.

## Endpoint contract matrix

| Endpoint | Request accepted | Response canonical | Response compatibility |
|---|---|---|---|
| `GET /v1/projects` | `unidade_id`, `company_id` (filter) | `unidade_id`, `unidade.id`, `unidade.nome` | `company_id`, `company.id`, `company.name` |
| `GET /v1/projects/{id}` | n/a | `unidade_id`, `unidade.*` | `company_id`, `company.*` |
| `POST /v1/projects` | `unidade_id` preferred, `company_id` accepted | `unidade_id` in result | compatibility preserved |
| `PUT /v1/projects/{id}` | `unidade_id` preferred, `company_id` accepted | updated scope follows canonical rule | compatibility preserved |
| `POST /v1/kanban/boards` | `unidade_id` preferred, `company_id` accepted | `unidade_id`, `unidade.id` | `company_id`, `company.id` |
| `GET /v1/kanban/boards` | current user scope | board payload includes `unidade_id` | `company_id` kept |
| `GET /v1/kanban/boards/{id}` | n/a | `data.board.unidade_id`, `data.board.unidade.id` | `data.board.company_id`, `data.board.company.id` |

## Validation rule
Validation errors related to organizational scope should expose both keys:
1. `unidade_id`
2. `company_id`

This preserves old clients while guiding new clients to canonical usage.

## Migration guidance for clients
1. Send only `unidade_id` in new client code.
2. Read `unidade_id` first in responses.
3. Keep fallback to `company_id` only for backward compatibility.

## Test coverage
Integration tests enforcing canonical contract:
1. `tests/Integration/CriticalFlowsIntegrationTest.php`
2. `tests/Unit/Api/Controllers/ProjectControllerTest.php`
