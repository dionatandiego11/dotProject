# 🚀 dotProject - Quick Start with Docker

## Pré-requisitos

- [Docker](https://www.docker.com/get-started) instalado
- [Docker Compose](https://docs.docker.com/compose/) instalado

---

## Subindo o Projeto

### 1. Copiar variáveis de ambiente

```bash
cp .env.example .env
```

### 2. Iniciar os containers

```bash
# Subir apenas o backend (PHP + MariaDB + Nginx)
docker-compose up -d

# Ou subir com frontend React
docker-compose --profile frontend up -d

# Ou subir com phpMyAdmin
docker-compose --profile tools up -d

# Ou subir tudo
docker-compose --profile frontend --profile tools up -d
```

### 3. Acessar

| Serviço | URL |
|---------|-----|
| **dotProject** | http://localhost |
| **API REST** | http://localhost/api.php/v1/health |
| **Frontend React** | http://localhost:5173 |
| **phpMyAdmin** | http://localhost:8081 |

---

## Comandos Úteis

```bash
# Ver logs
docker-compose logs -f

# Parar containers
docker-compose down

# Reconstruir containers
docker-compose build --no-cache

# Acessar container PHP
docker-compose exec phpfpm bash

# Acessar container MySQL
docker-compose exec mariadb mysql -u dotproject -p dotproject
```

---

## Credenciais Padrão

| Serviço | Usuário | Senha |
|---------|---------|-------|
| **MySQL** | dotproject | dotproject123 |
| **dotProject** | admin | admin |

---

## Configuração Google (Opcional)

1. Crie um projeto no [Google Cloud Console](https://console.cloud.google.com)
2. Ative as APIs: Calendar, Drive
3. Crie credenciais OAuth2
4. Edite o arquivo `.env`:

```env
GOOGLE_CLIENT_ID=seu_client_id
GOOGLE_CLIENT_SECRET=seu_client_secret
```

5. Configure em `includes/config.php`:

```php
$dPconfig['google_client_id'] = getenv('GOOGLE_CLIENT_ID');
$dPconfig['google_client_secret'] = getenv('GOOGLE_CLIENT_SECRET');
$dPconfig['google_redirect_uri'] = 'http://localhost/api.php/v1/integrations/google/callback';
```

---

## Estrutura da API

A API REST está disponível em `/api.php/v1/`:

| Endpoint | Descrição |
|----------|-----------|
| `POST /auth/login` | Autenticação (retorna JWT) |
| `GET /projects` | Listar projetos |
| `GET /tasks` | Listar tarefas |
| `GET /analytics/dashboard` | KPIs do dashboard |

Veja todos os 33 endpoints na [documentação da API](./docs/API.md).

---

## Backup e Restore (Banco)

```bash
# Gerar backup
bash scripts/ops/backup_db.sh

# Verificar restore em base temporaria (nao destrutivo)
bash scripts/ops/verify_backup_restore.sh

# Restore real (destrutivo)
bash scripts/ops/restore_db.sh --file ./backups/db_dotproject_YYYYMMDDTHHMMSSZ.sql.gz
```

Runbook completo: `docs/operations/backup_restore.md`
