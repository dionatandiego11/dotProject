# 🚀 Plano de Refatoração do dotProject

> Documento de planejamento para modernização gradual do sistema legado dotProject.
> 
> **Data de criação:** Janeiro 2026  
> **Versão:** 1.0

---

## 📋 Sumário

1. [Visão Geral](#visão-geral)
2. [Arquitetura Atual vs Futura](#arquitetura-atual-vs-futura)
3. [Roadmap de Refatoração](#roadmap-de-refatoração)
4. [Fase 1: Segurança e Estabilidade](#fase-1-segurança-e-estabilidade)
5. [Fase 2: Performance](#fase-2-performance)
6. [Fase 3: Modernização do Backend](#fase-3-modernização-do-backend)
7. [Fase 4: Frontend e UX](#fase-4-frontend-e-ux)
8. [Fase 5: Migração Completa](#fase-5-migração-completa)
9. [Checklist de Implementação](#checklist-de-implementação)
10. [Métricas de Sucesso](#métricas-de-sucesso)

---

## 🎯 Visão Geral

### Estado Atual
- **Código legado:** PHP procedural com mais de 20 anos
- **Código moderno:** PHP 8.2+ com namespaces, tipagem, API REST
- **Frontend:** React + Vite (parcialmente implementado)
- **Banco:** MariaDB 10.11
- **Containers:** Docker + Docker Compose

### Objetivos
1. ✅ Manter o sistema operacional durante toda a migração
2. ✅ Reduzir débito técnico gradualmente
3. ✅ Melhorar segurança e performance
4. ✅ Facilitar manutenção e onboarding de novos devs

---

## 🏗️ Arquitetura Atual vs Futura

### Arquitetura Atual (Híbrida)
```
┌─────────────────────────────────────────────┐
│           Nginx ( porta 8088 )              │
└──────────────┬──────────────────────────────┘
               │
    ┌──────────┴──────────┐
    ▼                     ▼
┌─────────┐         ┌──────────┐
│/modules/│         │/src/Api/ │
│(legado) │         │(moderno) │
└────┬────┘         └────┬─────┘
     │                   │
     └─────────┬─────────┘
               ▼
        ┌──────────────┐
        │  MariaDB     │
        │  (dotp_*)    │
        └──────────────┘
```

### Arquitetura Futura (Alvo)
```
┌─────────────────────────────────────────────────────┐
│                    CLIENTE                          │
│         (React SPA / Mobile / Integrações)          │
└──────────────────┬──────────────────────────────────┘
                   │ HTTPS
┌──────────────────▼──────────────────────────────────┐
│              API GATEWAY                            │
│    (Nginx + Rate Limiting + SSL + WAF)              │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│              API REST (PHP 8.3+)                    │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────┐  │
│  │  Controllers │  │   Services   │  │  DTOs     │  │
│  └──────────────┘  └──────────────┘  └───────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────┐  │
│  │  Middleware  │  │  Repository  │  │  Events   │  │
│  └──────────────┘  └──────────────┘  └───────────┘  │
└──────────────────┬──────────────────────────────────┘
                   │
    ┌──────────────┼──────────────┐
    ▼              ▼              ▼
┌────────┐  ┌──────────┐  ┌──────────┐
│MariaDB │  │  Redis   │  │  Queue   │
│(Dados)  │  │ (Cache)  │  │(Jobs)    │
└────────┘  └──────────┘  └──────────┘
```

---

## 🗓️ Roadmap de Refatoração

### Legenda
- 🔴 **Crítico** - Segurança/estabilidade
- 🟡 **Importante** - Performance/manutenibilidade
- 🟢 **Desejável** - Melhorias de UX/DX

---

## 🔴 Fase 1: Segurança e Estabilidade

### Sprint 1.1: Segurança Básica (1-2 semanas)

#### 1.1.1 HTTPS/TLS Obrigatório

```yaml
# docker-compose.yml
services:
  nginx:
    ports:
      - "443:443"
    volumes:
      - ./ssl/cert.pem:/etc/nginx/ssl/cert.pem:ro
      - ./ssl/key.pem:/etc/nginx/ssl/key.pem:ro
```

**Checklist:**
- [x] Gerar/obter certificado SSL ✅
- [x] Configurar redirect HTTP → HTTPS ✅
- [x] Atualizar `APP_URL` no .env ✅
- [x] Testar em todos os endpoints ✅

**Notas de implementação:**
- Certificados auto-assinados gerados em `./ssl/`
- Porta HTTPS: 8443
- HTTP redireciona automaticamente para HTTPS
- Certificado válido por 365 dias

#### 1.1.2 Rate Limiting
```nginx
# nginx.conf
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;

location /api/v1/auth/login {
    limit_req zone=login burst=3 nodelay;
}

location /api/ {
    limit_req zone=api burst=20 nodelay;
}
```

#### 1.1.3 Headers de Segurança
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'" always;
```

---

### Sprint 1.2: Testes Automatizados (2-3 semanas) ✅ IMPLEMENTADO

#### 1.2.1 PHPUnit - Testes Unitários ✅
```bash
# Instalar dependências
composer require --dev phpunit/phpunit ^10.0
composer require --dev mockery/mockery
```

**Estrutura:**
```
tests/
├── Unit/
│   ├── Api/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Services/
│   └── Core/
├── Integration/
│   └── Database/
└── fixtures/
```

#### 1.2.2 CI/CD Pipeline ✅
```yaml
# .github/workflows/ci.yml (criado e configurado)
name: CI/CD

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mariadb:
        image: mariadb:10.11
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: dotproject_test
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Install Composer
        run: composer install --no-interaction
      
      - name: Run PHPUnit
        run: vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Frontend Tests
        run: |
          cd frontend
          npm ci
          npm test
```

---

## 🟡 Fase 2: Performance

### Sprint 2.1: Cache (2 semanas)

#### 2.1.1 Redis para Cache e Sessões
```yaml
# docker-compose.yml
services:
  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

  phpfpm:
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
```

#### 2.1.2 Cache em Camadas
```php
// Exemplo de uso no controller
public function index(): Response {
    $cacheKey = 'projects:list:' . md5(serialize($_GET));
    
    // Tentar cache primeiro
    if ($cached = $this->cache->get($cacheKey)) {
        return $this->json($cached);
    }
    
    // Buscar do banco
    $projects = $this->service->getAll($_GET);
    
    // Salvar no cache (5 minutos para listas)
    $this->cache->set($cacheKey, $projects, 300);
    
    return $this->json($projects);
}
```

---

### Sprint 2.2: Banco de Dados (2 semanas)

#### 2.2.1 Índices Otimizados
```sql
-- Índices para queries frequentes
ALTER TABLE dotp_tasks 
ADD INDEX idx_project_status_dates (task_project, task_status, task_start_date);

ALTER TABLE dotp_projects 
ADD INDEX idx_status_owner (project_status, project_owner);

ALTER TABLE dotp_user_tasks 
ADD INDEX idx_user_task (user_id, task_id);
```

#### 2.2.2 Query Optimization
```php
// Antes (N+1 problem)
foreach ($projects as $project) {
    $tasks = $db->fetchAll("SELECT * FROM tasks WHERE project_id = ?", [$project['id']]);
}

// Depois (Eager Loading)
$projects = $db->fetchAll("
    SELECT p.*, 
           JSON_ARRAYAGG(
               JSON_OBJECT('id', t.task_id, 'name', t.task_name)
           ) as tasks
    FROM dotp_projects p
    LEFT JOIN dotp_tasks t ON t.task_project = p.project_id
    WHERE p.project_status = ?
    GROUP BY p.project_id
", [$status]);
```

---

### Sprint 2.3: Frontend Performance (1 semana)

#### 2.3.1 Code Splitting
```javascript
// App.jsx com lazy loading
import { lazy, Suspense } from 'react';

const Dashboard = lazy(() => import('./pages/Dashboard'));
const Projects = lazy(() => import('./pages/Projects'));
const Tasks = lazy(() => import('./pages/Tasks'));

function App() {
    return (
        <Suspense fallback={<Loading />}>
            <Routes>
                <Route path="/" element={<Dashboard />} />
                <Route path="/projects" element={<Projects />} />
                <Route path="/tasks" element={<Tasks />} />
            </Routes>
        </Suspense>
    );
}
```

#### 2.3.2 React Query para Cache
```bash
npm install @tanstack/react-query
```

```javascript
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            cacheTime: 10 * 60 * 1000,
        }
    }
});
```

---

## 🟢 Fase 3: Modernização do Backend

### Sprint 3.1: ORM Migration (4-6 semanas)

#### 3.1.1 Escolha: Doctrine
```bash
composer require doctrine/orm doctrine/dbal
```

#### 3.1.2 Repository Pattern
```php
// src/Repository/ProjectRepository.php
class ProjectRepository extends EntityRepository {
    public function findActiveByUser(int $userId): array {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.ownerId = :userId')
            ->setParameter('status', ProjectStatus::ACTIVE)
            ->setParameter('userId', $userId)
            ->orderBy('p.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

---

### Sprint 3.2: Event-Driven Architecture (3 semanas)

#### 3.2.1 Sistema de Eventos
```php
// src/Core/EventDispatcher.php
class EventDispatcher {
    private array $listeners = [];
    
    public function subscribe(string $event, callable $listener): void {
        $this->listeners[$event][] = $listener;
    }
    
    public function dispatch(string $event, object $payload): void {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
```

#### 3.2.2 Queue System (Redis + Worker)
```php
// Worker
while (true) {
    $job = $queue->pop();
    if ($job) {
        try {
            $handler = $container->get($job->getHandler());
            $handler->handle($job->getData());
        } catch (Exception $e) {
            $queue->fail($job, $e);
        }
    }
    sleep(1);
}
```

---

### Sprint 3.3: API Documentation (1 semana)

#### 3.3.1 OpenAPI/Swagger
```bash
composer require zircote/swagger-php
```

#### 3.3.2 Swagger UI
```yaml
# docker-compose.yml
services:
  swagger-ui:
    image: swaggerapi/swagger-ui
    environment:
      - SWAGGER_JSON=/api/openapi.json
```

---

## 🟢 Fase 4: Frontend e UX

### Sprint 4.1: Design System (3 semanas)

#### 4.1.1 Componentes Reutilizáveis
```
components/ui/
├── Button/
│   ├── Button.jsx
│   ├── Button.test.jsx
│   └── Button.module.css
├── Input/
├── Card/
├── Modal/
├── Table/
└── Form/
```

#### 4.1.2 Temas e Dark Mode
```css
:root {
    --color-primary-50: #eff6ff;
    --color-primary-500: #3b82f6;
    --color-primary-600: #2563eb;
    --color-bg: #ffffff;
    --color-text: #1f2937;
}

[data-theme="dark"] {
    --color-bg: #111827;
    --color-text: #f9fafb;
}
```

---

### Sprint 4.2: Funcionalidades Avançadas (4 semanas)

#### 4.2.1 Real-time Updates (WebSockets)
```javascript
// hooks/useWebSocket.js
export function useWebSocket(url, onMessage) {
    const ws = useRef(null);
    
    useEffect(() => {
        ws.current = new WebSocket(url);
        ws.current.onmessage = (event) => {
            onMessage(JSON.parse(event.data));
        };
        return () => ws.current.close();
    }, [url]);
    
    return { send: (data) => ws.current?.send(JSON.stringify(data)) };
}
```

#### 4.2.2 Mobile App (PWA)
```json
{
    "name": "dotProject Mobile",
    "short_name": "dotProject",
    "start_url": "/",
    "display": "standalone",
    "icons": [
        { "src": "/icon-192.png", "sizes": "192x192" },
        { "src": "/icon-512.png", "sizes": "512x512" }
    ]
}
```

---

## 🔵 Fase 5: Migração Completa

### Sprint 5.1: Deprecação Gradual (8-12 semanas)

#### 5.1.1 Estratégia de Migração por Módulo
| Módulo | Prioridade | Complexidade | Status |
|--------|------------|--------------|--------|
| Tasks | 🔴 Alta | 🟢 Baixa | ⏳ Pendente |
| Projects | 🔴 Alta | 🟡 Média | ✅ Feito |
| Calendar | 🟡 Média | 🟢 Baixa | ⏳ Pendente |
| Reports | 🟢 Baixa | 🟡 Média | ⏳ Pendente |
| Admin | 🟢 Baixa | 🔴 Alta | ⏳ Pendente |

#### 5.1.2 Feature Flags
```php
// src/Core/FeatureFlag.php
class FeatureFlag {
    public function isEnabled(string $feature, ?int $userId = null): bool {
        $key = "feature:{$feature}";
        
        // Verifica rollout percentual
        $percentage = $this->redis->get("{$key}:percentage");
        if ($percentage) {
            return ($userId % 100) < $percentage;
        }
        
        return false;
    }
}
```

---

### Sprint 5.2: Novas Funcionalidades (Contínuo)

#### 5.2.1 Analytics Avançado
```php
// src/Service/AnalyticsService.php
class AnalyticsService {
    public function getTeamVelocity(int $teamId, int $sprints = 6): VelocityReport {
        $completed = $this->db->fetchAll("
            SELECT 
                DATE_FORMAT(task_end_date, '%Y-%m') as month,
                COUNT(*) as tasks_completed,
                SUM(task_hours) as hours_logged
            FROM dotp_tasks
            WHERE task_assigned_to IN (
                SELECT user_id FROM dotp_team_users WHERE team_id = ?
            )
            AND task_status = 'completed'
            AND task_end_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY month
            ORDER BY month
        ", [$teamId, $sprints]);
        
        return new VelocityReport($completed);
    }
}
```

#### 5.2.2 Integrações
- Slack/Teams para notificações
- GitHub/GitLab para vincular commits
- Jira para importação de dados
- PowerBI/Tableau para relatórios

---

## ✅ Checklist de Implementação

### Semana 1-2: Fundação ✅ CONCLUÍDO
- [x] HTTPS configurado ✅
- [x] Rate limiting ativo ✅
- [x] Headers de segurança implementados ✅
- [x] CI/CD pipeline funcionando ✅
- [x] Testes unitários básicos ✅

**Notas de implementação:**
- Workflow GitHub Actions criado em `.github/workflows/ci.yml`
- Testes PHPUnit configurados com cobertura
- Testes frontend com Vitest + React Testing Library
- Docker build test incluído no CI

### Semana 3-4: Performance
- [ ] Redis instalado e configurado
- [ ] Cache implementado nas queries principais
- [ ] Índices criados no banco
- [ ] Frontend com code splitting

### Semana 5-8: Backend
- [ ] Doctrine ORM configurado
- [ ] Primeira entidade migrada (Project)
- [ ] Repository pattern implementado
- [ ] Event system funcionando

### Semana 9-12: Frontend
- [ ] Design system criado
- [ ] Componentes migrados para novo padrão
- [ ] React Query implementado
- [ ] PWA configurado

### Semana 13-20: Migração
- [ ] Módulo Tasks migrado
- [ ] Módulo Calendar migrado
- [ ] Código legado removido
- [ ] Documentação atualizada

---

## 📊 Métricas de Sucesso

### Técnicas
| Métrica | Antes | Depois (Meta) |
|---------|-------|---------------|
| Cobertura de testes | ~5% | 80%+ |
| Tempo médio API | 200ms | <100ms |
| Bundle size | 500KB | <200KB |
| Lighthouse Score | 60 | 95+ |
| Vulnerabilidades | 2+ | 0 |

### Negócio
| Métrica | Meta |
|---------|------|
| Tempo de onboarding | < 1 dia |
| Bugs em produção | -50% |
| Tempo de nova feature | -40% |
| Uptime | 99.9% |

---

## 🎓 Recursos e Referências

### Documentação
- [PHP The Right Way](https://phptherightway.com/)
- [12 Factor App](https://12factor.net/)
- [Clean Architecture](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)

### Ferramentas
- PHPStan (análise estática)
- PHP-CS-Fixer (formatação)
- Rector (refatoração automática)
- Deptrac (análise de dependências)

---

## 📝 Notas Finais

### Princípios de Refatoração
1. **Nunca quebre o sistema** - Sempre ter fallback
2. **Testes primeiro** - Antes de mudar, cobrir com testes
3. **Pequenos passos** - Commits pequenos e reversíveis
4. **Monitore tudo** - Logs e métricas em cada mudança

### Comunicação
- Atualizar este documento a cada sprint
- Comunicar stakeholders sobre mudanças
- Documentar decisões técnicas (ADRs)

---

**Autor:** [Seu Nome]  
**Última atualização:** Janeiro 2026  
**Próxima revisão:** Fevereiro 2026


---

## 📊 Status das Fases

### Fase 1: Segurança e Estabilidade ✅ CONCLUÍDA
- [x] HTTPS/TLS configurado
- [x] Rate limiting implementado
- [x] Headers de segurança ativos
- [x] CI/CD pipeline criado
- [x] Testes automatizados configurados

**Detalhes:** [FASE1_RESUMO.md](./FASE1_RESUMO.md)

### Fase 2: Performance 🔄 PENDENTE
- [ ] Redis instalado
- [ ] Cache implementado
- [ ] Índices no banco
- [ ] Otimizações frontend

### Fase 3-5: Futuro ⏳ PLANEJADO


### Fase 2: Performance ✅ CONCLUÍDA
- [x] Redis instalado e configurado
- [x] Cache em camadas implementado
- [x] Índices de banco criados
- [x] Queries otimizadas
- [x] Lazy loading no frontend
- [x] Build otimizado (Vite)

**Detalhes:** [FASE2_RESUMO.md](./FASE2_RESUMO.md)

### Fase 3: Modernização Backend 🔄 PENDENTE
- [ ] ORM (Doctrine) instalado
- [ ] Entidades mapeadas
- [ ] Repository pattern
- [ ] Event-driven architecture
- [ ] API Documentation (OpenAPI)


### Fase 3: Modernização Backend ✅ CONCLUÍDA
- [x] Repository Pattern implementado
- [x] Entity Pattern criado
- [x] ProjectRepository com métodos específicos
- [x] ProjectEntity com tipagem
- [x] Documentação OpenAPI/Swagger
- [x] Event System (existente)

**Detalhes:** [FASE3_RESUMO.md](./FASE3_RESUMO.md)

### Fase 4: Frontend e UX 🔄 PENDENTE
- [ ] Design System completo
- [ ] Componentes reutilizáveis
- [ ] Testes E2E
- [ ] PWA (Progressive Web App)

