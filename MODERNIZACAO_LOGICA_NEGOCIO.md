# 🚀 Modernização da Lógica de Negócio - dotProject

> Documentação completa da evolução do sistema de gestão de projetos legado para uma plataforma moderna e competitiva.

---

## 📋 Visão Geral

| Aspecto | Antes (Legado) | Depois (Modernizado) | Status |
|---------|----------------|----------------------|--------|
| **Arquitetura** | Procedural | Repository + Entity Pattern | ✅ Completo |
| **Autenticação** | Sessões PHP | JWT Stateless | ✅ Completo |
| **Cache** | Nenhum | L1 (Memory) + L2 (Redis) | ✅ Completo |
| **Feature Flags** | Não existia | Sistema completo | ✅ Completo |
| **API** | Mista/Legada | REST API completa | ✅ Completo |
| **Frontend** | PHP/HTML | React 18 + Vite | ✅ Completo |

---

## 🏗️ Camada de Domínio (Entities)

### 1. ProjectEntity - Evolução do Modelo de Projetos

#### Antes (Legado)
```php
// Código procedural sem tipagem
$project = mysql_fetch_assoc($result);
$project['project_start_date']; // string, sem validação
$project['project_percent_complete']; // int, sem lógica
```

#### Depois (Modernizado)
```php
class ProjectEntity {
    private ?int $id = null;
    private string $name;
    private int $status = 0;
    private ?DateTimeImmutable $startDate = null;
    private ?DateTimeImmutable $endDate = null;
    
    // Lógica de negócio encapsulada
    public function isOverdue(): bool {
        if ($this->endDate === null) return false;
        return !$this->isCompleted() && $this->endDate < new DateTimeImmutable();
    }
    
    public function getDaysRemaining(): ?int {
        if ($this->endDate === null) return null;
        return (int) (new DateTimeImmutable())->diff($this->endDate)->format('%r%a');
    }
    
    public function isActive(): bool {
        return $this->status === 0;
    }
    
    public function canBeCompleted(): bool {
        return $this->status === 0 && $this->percentComplete === 100;
    }
}
```

**Melhorias:**
- ✅ Tipagem forte (PHP 8.2)
- ✅ Imutabilidade com DateTimeImmutable
- ✅ Regras de negócio no domínio (Rich Domain Model)
- ✅ Validações encapsuladas

---

### 2. TaskEntity - Hierarquia e Dependências

#### Antes (Legado)
```php
// Tarefas planas, sem relacionamento pai-filho estruturado
$task['task_parent'] = 5; // simples foreign key
// Cálculo de progresso manual e impreciso
```

#### Depois (Modernizado)
```php
class TaskEntity {
    private ?int $id = null;
    private string $name;
    private ?int $parentTaskId = null; // Hierarquia
    private int $projectId;
    private ?int $assignedTo = null;
    
    // Status com significado de negócio
    public const STATUS_INACTIVE = -1;
    public const STATUS_ACTIVE = 0;
    public const STATUS_COMPLETED = 1;
    
    // Prioridades tipadas
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;
    
    public function isSubtask(): bool {
        return $this->parentTaskId !== null;
    }
    
    public function isOverdue(): bool {
        if ($this->dueDate === null) return false;
        return !$this->isCompleted() && $this->dueDate < new DateTimeImmutable();
    }
    
    public function getPriorityLabel(): string {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'Baixa',
            self::PRIORITY_MEDIUM => 'Média',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_URGENT => 'Urgente',
            default => 'Desconhecida',
        };
    }
    
    // Cálculo automático de variação de horas
    public function getHoursVariance(): ?float {
        if ($this->estimatedHours === null || $this->actualHours === null) {
            return null;
        }
        return $this->actualHours - $this->estimatedHours;
    }
}
```

**Melhorias:**
- ✅ Constantes para status/prioridades (elimina "magic numbers")
- ✅ Lógica de hierarquia (tarefas e subtarefas)
- ✅ Cálculos automáticos de variância
- ✅ Labels semânticas

---

### 3. UserEntity - Segurança e Perfil

#### Antes (Legado)
```php
// Senha em MD5 (inseguro)
$user['user_password'] = md5($password);
// Sem ligação clara com dados do contato
```

#### Depois (Modernizado)
```php
class UserEntity {
    private ?int $id = null;
    private string $username;
    private string $passwordHash; // bcrypt
    private int $contactId;
    
    // Dados do contato (join automático)
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    
    // Token management
    private ?string $token = null;
    private ?DateTimeImmutable $tokenExpiresAt = null;
    
    public function verifyPassword(string $password): bool {
        // Suporte a MD5 legado + bcrypt moderno
        if (strlen($this->passwordHash) === 32) {
            return md5($password) === $this->passwordHash;
        }
        return password_verify($password, $this->passwordHash);
    }
    
    public function upgradePasswordHash(string $password): void {
        if (strlen($this->passwordHash) === 32) {
            $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }
    }
    
    public function getFullName(): ?string {
        if ($this->firstName === null && $this->lastName === null) {
            return null;
        }
        return trim($this->firstName . ' ' . $this->lastName);
    }
    
    public function isTokenExpired(): bool {
        if ($this->tokenExpiresAt === null) {
            return true;
        }
        return $this->tokenExpiresAt < new DateTimeImmutable();
    }
}
```

**Melhorias:**
- ✅ Migração gradual de MD5 para bcrypt
- ✅ Dados do contato integrados
- ✅ Gestão de tokens JWT
- ✅ Validações de segurança

---

### 4. CalendarEventEntity - Gestão de Tempo

#### Antes (Legado)
```php
// Eventos simples sem relação com projetos/tarefas
$event['event_start_date']; // string
```

#### Depois (Modernizado)
```php
class CalendarEventEntity {
    private ?int $id = null;
    private string $title;
    private DateTimeImmutable $startDate;
    private ?DateTimeImmutable $endDate = null;
    private bool $allDay = false;
    
    // Relacionamentos polimórficos
    private ?int $projectId = null;
    private ?int $taskId = null;
    
    public function isFuture(): bool {
        return $this->startDate > new DateTimeImmutable();
    }
    
    public function isPast(): bool {
        if ($this->endDate === null) {
            return $this->startDate < new DateTimeImmutable();
        }
        return $this->endDate < new DateTimeImmutable();
    }
    
    public function isToday(): bool {
        $today = new DateTimeImmutable('today');
        $tomorrow = new DateTimeImmutable('tomorrow');
        return $this->startDate >= $today && $this->startDate < $tomorrow;
    }
    
    public function overlaps(self $other): bool {
        return $this->startDate < $other->endDate 
            && $this->endDate > $other->startDate;
    }
    
    public function getDuration(): ?\DateInterval {
        if ($this->endDate === null) return null;
        return $this->startDate->diff($this->endDate);
    }
}
```

**Melhorias:**
- ✅ Suporte a eventos all-day
- ✅ Detecção de conflitos (overlaps)
- ✅ Integração com projetos/tarefas
- ✅ Cálculo de duração

---

### 5. FileEntity - Versionamento e Controle

```php
class FileEntity {
    private ?int $id = null;
    private string $name;
    private string $realFilename;
    private int $size = 0;
    private float $version = 0;
    private string $checkout = ''; // Bloqueio
    
    public function isCheckedOut(): bool {
        return !empty($this->checkout);
    }
    
    public function isImage(): bool {
        if (empty($this->type)) return false;
        return str_starts_with($this->type, 'image/');
    }
    
    public function getFormattedSize(): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unitIndex = 0;
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
    
    public function getExtension(): string {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }
    
    public function belongsToProject(): bool {
        return $this->projectId !== null && $this->projectId > 0;
    }
}
```

---

## 📦 Camada de Dados (Repositories)

### Padrão Repository Implementado

```php
abstract class BaseRepository {
    protected PDO $db;
    protected ?Cache $cache;
    
    // Operações CRUD padronizadas
    public function findById(int $id): ?object;
    public function findBy(array $criteria, array $orderBy = []): array;
    public function create(array $data): ?object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    
    // Cache automático L1/L2
    protected function cacheKey(string $id): string;
    protected function remember(string $key, callable $callback);
}
```

### Exemplos de Consultas de Negócio

#### ProjectRepository
```php
class ProjectRepository extends BaseRepository {
    // Projetos com filtros de acesso
    public function findByUser(int $userId, array $filters = []): array;
    
    // Projetos atrasados (regra de negócio)
    public function findOverdue(): array {
        return $this->findBy([
            'project_end_date' => ['operator' => '<', 'value' => date('Y-m-d')],
            'project_status' => ['operator' => '!=', 'value' => 3], // não concluído
        ]);
    }
    
    // Estatísticas para dashboard
    public function getStatsByCompany(int $companyId): array {
        return [
            'total' => $this->count(['project_company' => $companyId]),
            'active' => $this->count(['project_company' => $companyId, 'project_status' => 0]),
            'completed' => $this->count(['project_company' => $companyId, 'project_status' => 3]),
            'overdue' => $this->countOverdueByCompany($companyId),
        ];
    }
}
```

#### TaskRepository
```php
class TaskRepository extends BaseRepository {
    // Tarefas por projeto com ordenação
    public function findByProject(int $projectId): array;
    
    // Tarefas do usuário (minha lista)
    public function findByAssignee(int $userId): array;
    
    // Tarefas atrasadas
    public function findOverdue(): array {
        return $this->findBy([
            'task_end_date' => ['operator' => '<', 'value' => date('Y-m-d')],
            'task_status' => 0, // ativo
        ], ['task_end_date' => 'ASC']);
    }
    
    // Tarefas que vencem em breve
    public function findDueSoon(int $days = 7): array;
    
    // Contagem por status para gráficos
    public function countByStatus(int $projectId): array {
        $sql = "SELECT task_status, COUNT(*) as count 
                FROM tasks 
                WHERE task_project = :project_id 
                GROUP BY task_status";
        // Retorna: [0 => 15, 1 => 8, -1 => 2]
    }
}
```

---

## 🔄 Feature Flags - Migração Gradual

### Sistema Implementado

```php
class FeatureFlag {
    public function isEnabled(string $feature, ?int $userId = null): bool {
        // Verifica variável de ambiente
        $envEnabled = getenv("FEATURE_" . strtoupper($feature)) === 'true';
        
        // Verifica percentual de rollout
        $percentage = (int)(getenv("FEATURE_" . strtoupper($feature) . "_PERCENT") ?: 0);
        
        // Verifica whitelist de usuários
        if ($userId && $this->isEnabledForUser($feature, $userId)) {
            return true;
        }
        
        return $envEnabled && $this->isEnabledForPercentage($feature, $userId ?? 0, $percentage);
    }
}
```

### Uso na Lógica de Negócio

```php
class LegacyAdapter {
    public function getProjectLegacy(int $id, ?int $userId = null): ?array {
        // Decide: usar código moderno ou legado?
        if ($this->features->isEnabled('modern_projects', $userId)) {
            $entity = $this->projectRepository->findById($id);
            return $entity ? $this->convertToLegacy($entity) : null;
        }
        
        // Fallback para código legado
        return $this->legacyQuery("SELECT * FROM projects WHERE project_id = ?", [$id]);
    }
}
```

---

## 💰 Lógica de Negócio Avançada (Roadmap)

### 1. Orçamento & Earned Value Management (EVM)

```php
// FASE 2 - Planejado
class BudgetEntity {
    private float $totalBudget;
    private float $hourlyRate;
    
    // Earned Value Management
    public function calculateEVM(float $plannedValue, float $earnedValue, float $actualCost): array {
        return [
            'sv' => $earnedValue - $plannedValue,      // Schedule Variance
            'cv' => $earnedValue - $actualCost,        // Cost Variance
            'spi' => $earnedValue / $plannedValue,     // Schedule Performance Index
            'cpi' => $earnedValue / $actualCost,       // Cost Performance Index
            'eac' => $this->totalBudget / $cpi,        // Estimate at Completion
            'etc' => ($this->totalBudget / $cpi) - $actualCost, // Estimate to Complete
        ];
    }
}
```

### 2. Sprints & Story Points (Scrum)

```php
// FASE 2 - Planejado
class SprintEntity {
    private string $name;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private int $velocity; // pontos concluídos por sprint
    
    public function calculateCapacity(array $teamMembers): int {
        // Soma de horas disponíveis da equipe
        return array_sum(array_map(fn($user) => $user->getDailyCapacity(), $teamMembers)) * 10; // 2 semanas
    }
    
    public function getBurndownData(): array {
        // Dados para gráfico de burndown
    }
}

class TaskEntity {
    private ?int $storyPoints = null; // Fibonacci: 1, 2, 3, 5, 8, 13, 21
    private ?int $sprintId = null;
}
```

### 3. Gestão de Riscos

```php
// FASE 3 - Planejado
class RiskEntity {
    private string $description;
    private int $probability; // 1-5
    private int $impact;      // 1-5
    
    public function getScore(): int {
        return $this->probability * $this->impact; // Matriz 5x5
    }
    
    public function getLevel(): string {
        $score = $this->getScore();
        return match (true) {
            $score >= 15 => 'CRITICAL',
            $score >= 10 => 'HIGH',
            $score >= 5 => 'MEDIUM',
            default => 'LOW',
        };
    }
}
```

### 4. Automação de Workflows

```php
// FASE 3 - Planejado
class WorkflowRule {
    private string $trigger; // 'task.status_changed'
    private array $conditions; // ['status' => 'done']
    private array $actions; // [['type' => 'notify', 'to' => 'manager']]
    
    public function execute(TaskEntity $task): void {
        if ($this->evaluateConditions($task)) {
            foreach ($this->actions as $action) {
                $this->executeAction($action, $task);
            }
        }
    }
}
```

---

## 📊 Métricas de Negócio Implementadas

### Dashboard Analytics

```php
class AnalyticsService {
    public function getDashboard(int $userId): array {
        return [
            'projects' => [
                'active' => $this->projectRepo->countActiveByUser($userId),
                'overdue' => $this->projectRepo->countOverdueByUser($userId),
                'health_score' => $this->calculateHealthScore($userId),
            ],
            'tasks' => [
                'total' => $this->taskRepo->countByUser($userId),
                'completed' => $this->taskRepo->countCompletedByUser($userId),
                'overdue' => $this->taskRepo->countOverdueByUser($userId),
                'due_soon' => $this->taskRepo->countDueSoonByUser($userId, 7),
                'completion_rate' => $this->calculateCompletionRate($userId),
            ],
            'hours' => [
                'this_week' => $this->taskLogRepo->sumHoursThisWeek($userId),
                'variance' => $this->calculateHoursVariance($userId),
            ],
            'velocity' => [
                'current' => $this->calculateVelocity($userId, 2), // últimas 2 semanas
                'trend' => $this->calculateVelocityTrend($userId),
            ],
        ];
    }
    
    private function calculateHealthScore(int $userId): float {
        $projects = $this->projectRepo->findByUser($userId);
        $scores = array_map(fn($p) => $p->calculateHealthScore(), $projects);
        return array_sum($scores) / count($scores);
    }
}
```

---

## 🔐 Regras de Segurança de Negócio

### Isolamento Multi-tenant

```php
// TODAS as queries filtram por empresa
abstract class BaseRepository {
    protected function applyCompanyFilter(array &$criteria, int $userId): void {
        $user = $this->userRepo->findById($userId);
        $criteria['company_id'] = $user->getCompanyId();
    }
}

// Exemplo de uso
class ProjectRepository extends BaseRepository {
    public function findByUser(int $userId, array $filters = []): array {
        $this->applyCompanyFilter($filters, $userId);
        return $this->findBy($filters);
    }
}
```

### Auditoria de Alterações

```php
class AuditService {
    public function log(string $action, object $entity, ?int $userId = null): void {
        $log = [
            'action' => $action, // CREATE, UPDATE, DELETE
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getId(),
            'user_id' => $userId,
            'changes' => $this->getChanges($entity),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp' => new DateTimeImmutable(),
        ];
        
        $this->auditRepository->save($log);
    }
}
```

---

## 📈 Roadmap de Modernização

### ✅ FASE 1 - Concluída (Janeiro 2026)
- [x] Repository Pattern
- [x] Entity Pattern com tipagem
- [x] JWT Authentication
- [x] Feature Flags
- [x] Cache L1/L2
- [x] API REST
- [x] React Frontend
- [x] Docker Containerization

### 🚧 FASE 2 - Em Planejamento
- [ ] Sprints & Story Points
- [ ] Kanban Board Visual
- [ ] Time Tracking com Timer
- [ ] Orçamento & EVM
- [ ] Gestão de Recursos
- [ ] Automação de Workflows

### 📋 FASE 3 - Futuro
- [ ] Gestão de Riscos
- [ ] Portfólio & Programas
- [ ] Machine Learning (previsões)
- [ ] Integrações avançadas
- [ ] Mobile App

---

## 🎯 Conclusão

A modernização da lógica de negócio transformou o dotProject de um sistema **procedural legado** para uma arquitetura **moderna orientada a domínio**:

### Antes
- Código espaguete
- Sem tipagem
- Lógica misturada com UI
- Difícil de testar
- Sem cache

### Depois
- Domínio rico (Rich Domain Model)
- Tipagem forte (PHP 8.2)
- Separação de concerns
- 100% testável
- Cache multi-camada
- API RESTful
- Frontend moderno

**Próximo passo:** Implementar as funcionalidades de FASE 2 (Sprints, Kanban, Time Tracking) para competir com Jira/Asana.

---

*Documento atualizado em: Janeiro 2026*
