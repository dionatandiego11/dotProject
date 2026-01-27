# 🚀 dotProject - Modernizado

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=white" alt="React 18">
  <img src="https://img.shields.io/badge/MariaDB-10.11-003545?logo=mariadb&logoColor=white" alt="MariaDB">
  <img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis">
  <img src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/License-GPL%202.0-blue.svg" alt="License">
</p>

<p align="center">
  <b>Sistema de Gestão de Projetos modernizado com arquitetura REST API, React 18 e PHP 8.2</b>
</p>

<p align="center">
  <a href="#-funcionalidades">Funcionalidades</a> •
  <a href="#-stack-tecnológica">Stack</a> •
  <a href="#-arquitetura">Arquitetura</a> •
  <a href="#-instalação">Instalação</a> •
  <a href="#-screenshots">Screenshots</a>
</p>

---

## 📋 Sobre o Projeto

O **dotProject** é um sistema open-source de gestão de projetos amplamente utilizado. Esta versão representa uma **modernização completa** da aplicação legada, transformando-a em uma solução moderna baseada em API REST com frontend React.

### 🎯 Objetivo da Modernização

Migrar o código legado procedural PHP para uma arquitetura moderna, mantendo todas as funcionalidades originais enquanto adiciona:
- ✅ Segurança enterprise-grade
- ✅ Performance otimizada
- ✅ UX/UI moderna
- ✅ Arquitetura escalável
- ✅ Testes automatizados

---

## ✨ Funcionalidades

### 📊 Gestão de Projetos
- Criação e acompanhamento de projetos
- Definição de prazos, prioridades e status
- Controle de progresso percentual
- Associação a empresas e departamentos

### ✅ Gestão de Tarefas
- Tarefas hierárquicas (subtarefas)
- Atribuição a usuários
- Controle de tempo (estimado vs real)
- Prioridades e status de conclusão
- Notificações de atraso

### 📅 Calendário
- Visualização de eventos por período
- Integração com tarefas e projetos
- Suporte a eventos all-day
- Sincronização com Google Calendar

### 📁 Gestão de Arquivos
- Upload e versionamento de arquivos
- Check-in/Check-out para edição colaborativa
- Categorização e organização em pastas
- Visualização de imagens

### 👥 Gestão de Usuários
- Autenticação JWT segura
- Controle de permissões por módulo
- Perfis de usuário
- Departamentos e empresas

### 📈 Analytics & Dashboard
- Dashboard com métricas em tempo real
- Gráficos de produtividade
- Burndown charts
- Relatórios de desempenho da equipe

---

## 🛠️ Stack Tecnológica

### Backend
| Tecnologia | Versão | Uso |
|------------|--------|-----|
| **PHP** | 8.2 | Lógica de negócio, API REST |
| **MariaDB** | 10.11 | Banco de dados relacional |
| **Redis** | 7 | Cache L1/L2, sessões |
| **Nginx** | 1.29 | Servidor web, proxy reverso |
| **Composer** | 2.x | Gerenciamento de dependências |

### Frontend
| Tecnologia | Versão | Uso |
|------------|--------|-----|
| **React** | 18 | Interface do usuário |
| **Vite** | 5.x | Build tool e dev server |
| **Recharts** | 2.x | Gráficos e visualizações |
| **CSS3** | - | Design System customizado |

### DevOps & Infraestrutura
| Tecnologia | Uso |
|------------|-----|
| **Docker** | Containerização |
| **Docker Compose** | Orquestração de serviços |
| **GitHub Actions** | CI/CD pipeline |
| **PHPUnit** | Testes unitários backend |
| **Vitest** | Testes unitários frontend |

---

## 🏗️ Arquitetura

### Padrões Implementados

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENTE                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │   React 18  │  │   Vite      │  │  Navegador  │         │
│  └──────┬──────┘  └─────────────┘  └─────────────┘         │
└─────────┼───────────────────────────────────────────────────┘
          │ HTTPS /api
┌─────────┼───────────────────────────────────────────────────┐
│         ▼                    SERVIDOR                       │
│  ┌─────────────────────────────────────────────┐           │
│  │            Nginx (Porta 8443)               │           │
│  │  - Rate Limiting (5r/m login, 100r/m API)   │           │
│  │  - SSL/TLS 1.3                             │           │
│  │  - Security Headers (CSP, HSTS, etc)       │           │
│  └──────────────────┬──────────────────────────┘           │
│                     │                                       │
│  ┌──────────────────▼──────────────────────────┐           │
│  │         PHP-FPM 8.2 (FastCGI)               │           │
│  │  ┌─────────────────────────────────────┐   │           │
│  │  │         API REST (api.php)          │   │           │
│  │  │  ┌─────────┐ ┌─────────┐ ┌────────┐ │   │           │
│  │  │  │  Auth   │ │Projects │ │ Tasks  │ │   │           │
│  │  │  └────┬────┘ └────┬────┘ └───┬────┘ │   │           │
│  │  └───────┼──────────┼──────────┼──────┘   │           │
│  └──────────┼──────────┼──────────┼──────────┘           │
│             │          │          │                       │
│  ┌──────────▼──────────▼──────────▼──────────┐           │
│  │      Repository Pattern (src/)            │           │
│  │  - ProjectRepository                      │           │
│  │  - TaskRepository                         │           │
│  │  - UserRepository                         │           │
│  │  - FileRepository                         │           │
│  │  - CalendarEventRepository                │           │
│  └──────────────────┬────────────────────────┘           │
│                     │                                     │
│  ┌──────────────────▼────────────────────────┐           │
│  │      Entity Pattern (src/Entity/)         │           │
│  │  - ProjectEntity, TaskEntity, etc         │           │
│  └──────────────────┬────────────────────────┘           │
│                     │                                     │
│  ┌──────────────────▼────────────────────────┐           │
│  │         Core Services                     │           │
│  │  ┌────────────┐ ┌──────────────┐         │           │
│  │  │   Cache    │ │  FeatureFlag │         │           │
│  │  │  L1 / L2   │ │   System     │         │           │
│  │  └─────┬──────┘ └──────┬───────┘         │           │
│  └────────┼────────────────┼─────────────────┘           │
│           │                │                              │
│  ┌────────▼──────┐  ┌──────▼────────┐                    │
│  │   MariaDB     │  │    Redis      │                    │
│  │   (Dados)     │  │   (Cache)     │                    │
│  └───────────────┘  └───────────────┘                    │
└──────────────────────────────────────────────────────────┘
```

### Padrões de Design

- **Repository Pattern**: Abstração da camada de dados
- **Entity Pattern**: Objetos de domínio com tipagem forte
- **Feature Flags**: Migração gradual e A/B testing
- **Cache L1/L2**: Memória (5s) + Redis (5min)
- **JWT Authentication**: Stateless auth com refresh tokens

---

## 🔒 Segurança

### Implementações de Segurança

| Camada | Implementação |
|--------|---------------|
| **Transporte** | HTTPS/TLS 1.3 com certificados auto-assinados |
| **Autenticação** | JWT (JSON Web Tokens) com expiração |
| **Rate Limiting** | 5 req/min (login), 100 req/min (API) |
| **Headers** | CSP, HSTS, X-Frame-Options, X-XSS-Protection |
| **Senhas** | bcrypt com salt automático |
| **CORS** | Configurado para origens específicas |

### Headers de Segurança
```nginx
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: default-src 'self'...
```

---

## 🚀 Instalação

### Pré-requisitos
- Docker 20.10+
- Docker Compose 2.0+
- Git

### Quick Start

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/dotproject.git
cd dotproject

# 2. Configure as variáveis de ambiente (opcional)
cp .env.example .env
# Edite .env conforme necessário

# 3. Inicie os containers
docker-compose up -d

# 4. Aguarde a inicialização (30-60 segundos)
docker-compose logs -f

# 5. Acesse a aplicação
# Frontend: http://localhost:5173
# API:      https://localhost:8443
# phpMyAdmin: http://localhost:8080
```

### Credenciais Padrão
```
Usuário: admin
Senha: admin
```

### Comandos Úteis

```bash
# Ver logs
docker-compose logs -f [servico]

# Reiniciar serviço
docker-compose restart [servico]

# Executar testes backend
docker-compose exec phpfpm ./vendor/bin/phpunit

# Executar testes frontend
cd frontend && npm test

# Acessar container
docker-compose exec phpfpm bash
docker-compose exec mariadb mysql -u root -p

# Backup do banco
docker-compose exec mariadb mysqldump -u root -p dotproject > backup.sql
```

---

## 📸 Screenshots

### Login
<p align="center">
  <i>Tela de login moderna com design responsivo</i>
</p>

### Dashboard
<p align="center">
  <i>Dashboard com métricas em tempo real e gráficos</i>
</p>

### Projetos
<p align="center">
  <i>Listagem e gestão de projetos com filtros</i>
</p>

### Tarefas
<p align="center">
  <i>Kanban e lista de tarefas com prioridades</i>
</p>

---

## ⚡ Performance

### Otimizações Implementadas

| Otimização | Impacto |
|------------|---------|
| **Cache L1 (Memory)** | 5 segundos, zero latência |
| **Cache L2 (Redis)** | 5 minutos, persistência |
| **Code Splitting** | Bundle dividido em chunks |
| **Lazy Loading** | Componentes carregados sob demanda |
| **Gzip Compression** | Redução de 70% no tamanho |
| **DB Indexes** | Consultas otimizadas |
| **HTTP/2** | Multiplexing de requisições |

### Métricas
- **Time to First Byte**: < 100ms
- **First Contentful Paint**: < 1.5s
- **API Response Time**: < 200ms (cache hit)

---

## 🧪 Testes

### Backend (PHPUnit)
```bash
docker-compose exec phpfpm ./vendor/bin/phpunit
docker-compose exec phpfpm ./vendor/bin/phpunit --coverage-html coverage
```

### Frontend (Vitest)
```bash
cd frontend
npm test
npm run coverage
```

### E2E (Playwright - em desenvolvimento)
```bash
cd frontend
npx playwright test
```

---

## 📁 Estrutura do Projeto

```
dotproject/
├── 📁 .docker-compose/          # Configurações Docker
│   ├── nginx.conf              # Config do nginx
│   └── redis/                  # Config do Redis
│
├── 📁 frontend/                 # Aplicação React
│   ├── src/
│   │   ├── components/         # Componentes React
│   │   ├── pages/              # Páginas da aplicação
│   │   ├── services/           # API services
│   │   └── hooks/              # Custom hooks
│   ├── public/                 # Assets estáticos
│   └── vite.config.js          # Config do Vite
│
├── 📁 src/                      # Código PHP moderno
│   ├── Core/                   # FeatureFlag, Cache, Database
│   ├── Entity/                 # Entidades (8 classes)
│   ├── Repository/             # Repositories (9 classes)
│   └── Services/               # Business logic
│
├── 📁 classes/                  # Código legado (compatibility)
├── 📁 modules/                  # Módulos legados
├── 📁 files/                    # Uploads de arquivos
├── 📁 db/                       # Scripts SQL
├── 📁 tests/                    # Testes PHPUnit
├── 📄 docker-compose.yml        # Orquestração
├── 📄 Dockerfile               # Build da aplicação
└── 📄 .env                     # Variáveis de ambiente
```

---

## 🔄 Migração de Dados

Se você tem uma instalação legada do dotProject, é possível migrar os dados:

```bash
# 1. Backup do banco legado
mysqldump -u root -p dotproject_legacy > legacy_backup.sql

# 2. Importar para o novo ambiente
docker-compose exec -T mariadb mysql -u root -p dotproject < legacy_backup.sql

# 3. Executar migrações (se necessário)
docker-compose exec phpfpm php migrate.php
```

---

## 🤝 Contribuição

Contribuições são bem-vindas! Por favor, siga estes passos:

1. **Fork** o projeto
2. Crie uma **branch** (`git checkout -b feature/nova-funcionalidade`)
3. **Commit** suas mudanças (`git commit -m 'Add: nova funcionalidade'`)
4. **Push** para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um **Pull Request**

### Diretrizes de Código
- Siga o padrão PSR-12 para PHP
- Use ESLint/Prettier para JavaScript
- Escreva testes para novas funcionalidades
- Documente mudanças na API

---

## 📝 Changelog

### Versão 2.0.0 - Modernização Completa
- ✅ Refatoração completa para PHP 8.2
- ✅ Nova API REST com JWT auth
- ✅ Frontend React 18 com Vite
- ✅ Arquitetura Repository + Entity
- ✅ Sistema de Feature Flags
- ✅ Cache L1/L2 com Redis
- ✅ Segurança enterprise-grade
- ✅ Docker containerization

---

## 📄 Licença

Este projeto é licenciado sob a GNU General Public License v2.0 ou posterior - veja o arquivo [COPYING](COPYING) para detalhes.

---

## 🙏 Agradecimentos

- **dotProject Original** - Equipe original do dotProject
- **Comunidade Open Source** - Todas as bibliotecas e ferramentas utilizadas
- **Contribuidores** - Todos que ajudaram na modernização

---

<p align="center">
  <b>⭐ Star este repo se ele te ajudou!</b>
</p>

<p align="center">
  Feito com ❤️ e ☕ usando PHP + React
</p>
