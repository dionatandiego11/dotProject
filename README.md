<p align="center">
  <h1 align="center">🏛️ dotProject Prefeituras</h1>
  <p align="center">
    <strong>Sistema de gestão pública municipal</strong><br/>
    Projetos · Tarefas · Kanban · Organograma · Dashboards por perfil
  </p>
  <p align="center">
    <img alt="PHP 8.2" src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white"/>
    <img alt="React 18" src="https://img.shields.io/badge/React-18.3-61DAFB?logo=react&logoColor=black"/>
    <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-10.11-003545?logo=mariadb"/>
    <img alt="Redis" src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white"/>
    <img alt="Docker" src="https://img.shields.io/badge/Docker-compose-2496ED?logo=docker&logoColor=white"/>
    <img alt="License GPLv2" src="https://img.shields.io/badge/License-GPLv2-blue"/>
  </p>
</p>

---

## Visão Geral

Plataforma SaaS **multi-tenant** para gestão de projetos e estrutura organizacional de prefeituras brasileiras. Cada prefeitura recebe seu próprio tenant com isolamento completo de dados, níveis hierárquicos configuráveis, e dashboards filtrados por escopo de permissão.

### Funcionalidades Principais

| Módulo | Descrição |
|--------|-----------|
| **Estrutura Organizacional** | Cadastro de níveis hierárquicos, unidades (secretarias, departamentos, setores) e organograma visual |
| **Projetos** | CRUD completo, vínculo obrigatório com unidade responsável, histórico de status, progresso automático |
| **Tarefas** | CRUD, dependências, responsáveis por unidade, log de atividades |
| **Kanban** | Boards por projeto, colunas customizáveis, drag'n'drop |
| **Dashboards** | Painéis filtrados por hierarquia: Prefeito (tudo), Secretário (secretaria), Chefe (unidade), Técnico (pessoal) |
| **Administração** | Setup wizard para onboarding, gestão de usuários, configuração de prefeitura, permissões RBAC |
| **Multi-Tenancy** | Shared-schema com `tenant_id` em 15 tabelas, resolução por subdomínio/header |

---

## Arquitetura

```
┌──────────────────────────────────────────────────────────┐
│                     Frontend (React 18)                   │
│  SPA · Vite · Lazy Loading · Design System · 102 files   │
└──────────────┬───────────────────────────────┬────────────┘
               │ REST API (JSON)               │
┌──────────────▼───────────────────────────────▼────────────┐
│                   Nginx (reverse proxy)                    │
│                     :8088 / :8443                          │
└──────────────┬────────────────────────────────────────────┘
               │
┌──────────────▼────────────────────────────────────────────┐
│                    PHP-FPM 8.2 (API)                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌───────────┐ │
│  │Controller│→ │ Service  │→ │Repository│→ │  Entity   │ │
│  │  (35)    │  │  (22)    │  │  (21)    │  │  (22)     │ │
│  └──────────┘  └──────────┘  └──────────┘  └───────────┘ │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Middleware: Auth (JWT) · Tenant · CORS · RateLimit   │ │
│  └──────────────────────────────────────────────────────┘ │
└──────────────┬────────────────────────┬───────────────────┘
               │                        │
  ┌────────────▼──────────┐  ┌──────────▼──────────┐
  │ MariaDB 10.11         │  │ Redis 7 (cache)     │
  │ 35 migrations         │  │ 256MB, LRU eviction │
  │ 15 tabelas c/ tenant  │  │ AOF persistence     │
  └───────────────────────┘  └─────────────────────┘
```

### Estrutura de Diretórios

```
dotProject/
├── frontend/              # React SPA (Vite)
│   └── src/
│       ├── components/    # 26 componentes reutilizáveis
│       ├── pages/         # 46 páginas (lazy loaded)
│       │   ├── admin/     # Área administrativa (8 arquivos)
│       │   ├── projects/  # Projetos decompostos
│       │   ├── kanban/    # Kanban decompostos
│       │   └── setupWizard/ # Wizard de onboarding
│       ├── services/      # 15 modules de API
│       ├── hooks/         # 7 custom hooks
│       └── styles/        # Design tokens
│
├── src/                   # Backend moderno (PHP 8.2, PSR-4)
│   ├── Api/
│   │   ├── Controller/    # 35 controllers (REST)
│   │   └── Middleware/    # Auth, Tenant, CORS
│   ├── Core/              # TenantContext, Database, Cache, Logger
│   ├── Entity/            # 22 POPOs tipados
│   ├── Repository/        # 21 repos (BaseRepository com auto-tenant)
│   └── Service/           # 22 services de negócio
│
├── db/
│   ├── dotproject.sql     # Schema base
│   └── migrations/        # 35 migrations SQL (idempotentes)
│
├── docs/                  # Documentação
│   ├── API.md
│   ├── openapi.yaml
│   └── TROUBLESHOOTING.md
│
├── docker-compose.yml     # Stack completa
└── .github/workflows/     # CI (lint + build)
```

---

## Quick Start

### Pré-requisitos

- **Docker** e **Docker Compose** v2+
- **Node.js** 20+ (para desenvolvimento frontend)

### 1. Configuração

```bash
# Clone o repositório
git clone <repo-url> dotProject
cd dotProject

# Configure as variáveis de ambiente
cp .env.example .env
# Edite .env com seu JWT_SECRET e credenciais do banco
```

### 2. Subir os Containers

```bash
# Stack principal (API + DB + Redis + Nginx)
docker compose up -d

# Com frontend em dev mode (hot reload)
docker compose --profile frontend up -d

# Com phpMyAdmin (gestão do banco)
docker compose --profile tools up -d
```

### 3. Executar Migrations

```bash
# Conecte ao container do MariaDB
docker compose exec mariadb mysql -u root -psecretpw <database_name>

# Execute as migrations na ordem
source /docker-entrypoint-initdb.d/01-schema.sql
# ... ou execute cada migration em db/migrations/
```

### 4. Acessar

| Serviço | URL |
|---------|-----|
| **Frontend** | http://localhost:5173 |
| **API** | http://localhost:8088/api/v1 |
| **phpMyAdmin** | http://localhost:8081 (profile `tools`) |

---

## Hierarquia e Permissões

O sistema implementa **visibilidade por escopo hierárquico**:

| Perfil | Escopo | Exemplo |
|--------|--------|---------|
| 🏛️ **Prefeito** | Visão total | Vê todas secretarias, projetos e tarefas |
| 📋 **Secretário** | Secretaria + subordinadas | Vê sua secretaria e departamentos abaixo |
| 🏢 **Chefe** | Unidade específica | Vê apenas sua unidade |
| 👤 **Técnico** | Tarefas pessoais | Dashboard simplificado |

---

## Multi-Tenancy

Arquitetura **shared-schema** com isolamento por `tenant_id`:

- **15 tabelas** com coluna `tenant_id` (FK para `dotp_tenants`)
- **Resolução automática** via subdomínio (`demo.dotproject.app`), header (`X-Tenant-ID`), ou fallback
- **Filtro transparente** via `BaseRepository.appendTenantScopeToSql()` e `TenantAwareTrait`
- **Onboarding** com seed template SQL parametrizável

### Variáveis de Ambiente

```env
TENANCY_ENABLED=true
TENANT_DEFAULT_ID=1
TENANT_DEFAULT_SLUG=default
TENANCY_STRICT_MODE=false          # true = rejeita requests sem tenant
TENANT_ALLOW_HEADER_OVERRIDE=true  # permite X-Tenant-ID header
```

---

## Área Administrativa

Acessível em `/admin` (requer permissão de administrador):

| Rota | Função |
|------|--------|
| `/admin` | Painel com estatísticas + ações rápidas |
| `/admin/prefeitura` | Dados do município (CNPJ, cidade, estado) |
| `/admin/niveis` | Configurar níveis hierárquicos |
| `/admin/unidades` | Árvore de unidades organizacionais |
| `/admin/usuarios` | CRUD de usuários + vínculos |
| `/admin/organograma` | Visualização da estrutura |
| `/admin/setup` | Wizard guiado (onboarding ou reconfiguração) |

---

## API

A API REST segue o padrão `/api/v1/{recurso}` com autenticação via **JWT Bearer Token**.

```bash
# Autenticar
curl -X POST http://localhost:8088/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"senha"}'

# Listar projetos (autenticado)
curl http://localhost:8088/api/v1/projects \
  -H "Authorization: Bearer <token>"
```

Documentação completa: [`docs/API.md`](docs/API.md) · [`docs/openapi.yaml`](docs/openapi.yaml)

---

## Desenvolvimento

### Frontend

```bash
cd frontend
npm install
npm run dev        # Dev server com hot reload
npm run build      # Build de produção
npm run test       # Testes
npm run lint       # ESLint
```

### Backend

O backend PHP não requer build — o PHP-FPM serve diretamente. Para rodar testes:

```bash
# Dentro do container
docker compose exec phpfpm vendor/bin/phpunit
```

### CI/CD

- **GitHub Actions**: `.github/workflows/ci.yml` (lint + build frontend) e `php.yml` (PHP lint)

---

## Stack Tecnológica

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Frontend | React + Vite | 18.3 / 6.x |
| Backend | PHP-FPM | 8.2 |
| Banco | MariaDB | 10.11 |
| Cache | Redis | 7 (Alpine) |
| Proxy | Nginx | Alpine |
| Containers | Docker Compose | v2+ |
| CI | GitHub Actions | — |

---

## Documentação

| Documento | Descrição |
|-----------|-----------|
| [`docs/API.md`](docs/API.md) | Referência dos endpoints REST |
| [`docs/openapi.yaml`](docs/openapi.yaml) | Especificação OpenAPI 3.0 |
| [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) | Guia do usuário |
| [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) | Resolução de problemas comuns |
| [`docs/operations/backup_restore.md`](docs/operations/backup_restore.md) | Backup e restauração |

---

## Licença

[GPL v2.0](COPYING)
