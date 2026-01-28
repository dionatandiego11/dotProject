# 🚀 Guia de Migração - dotProject

> Guia passo a passo para migração gradual do código legado

---

## 📋 Visão Geral

Este guia descreve como migrar módulos do código legado PHP procedural para o novo padrão orientado a objetos com:
- Repository Pattern
- Entity Pattern
- Cache em camadas
- Feature Flags

---

## 🗺️ Roadmap de Migração

```
Módulos para migrar:
✅ Projects (CONCLUÍDO)
⏳ Tasks (PRÓXIMO)
⏳ Calendar
⏳ Files
⏳ Admin
```

---

## 🛠️ Passo a Passo

### Passo 1: Criar Entidade

Crie a entidade em `src/Entity/{Nome}Entity.php`:

```php
<?php
namespace DotProject\Entity;

use DateTime;

class TaskEntity
{
    private ?int $id = null;
    private string $name;
    private ?string $description = null;
    private int $projectId;
    private ?int $assignedTo = null;
    private int $status = 0;
    private int $priority = 3;
    private int $percentComplete = 0;
    private ?DateTime $startDate = null;
    private ?DateTime $endDate = null;
    
    // Getters e Setters...
    
    public function isOverdue(): bool
    {
        if ($this->endDate === null) {
            return false;
        }
        return new DateTime() > $this->endDate && $this->status !== 1;
    }
}
```

### Passo 2: Criar Repository

Crie o repository em `src/Repository/{Nome}Repository.php`:

```php
<?php
namespace DotProject\Repository;

use DotProject\Entity\TaskEntity;

class TaskRepository extends BaseRepository
{
    protected string $table = 'dotp_tasks';
    protected string $primaryKey = 'task_id';

    protected function hydrate(array $data): TaskEntity
    {
        $entity = new TaskEntity();
        $entity->setId((int) $data['task_id']);
        $entity->setName($data['task_name']);
        // ... mapear todos os campos
        return $entity;
    }

    protected function extract(object $entity): array
    {
        // Retornar array para INSERT/UPDATE
        return [
            'task_id' => $entity->getId(),
            'task_name' => $entity->getName(),
            // ...
        ];
    }

    // Métodos específicos
    public function findByProject(int $projectId): array
    {
        return $this->findBy(['task_project' => $projectId]);
    }

    public function findOverdue(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE task_end_date < CURDATE() 
                AND task_status != 1";
        $results = $this->db->fetchAll($sql);
        return array_map([$this, 'hydrate'], $results);
    }
}
```

### Passo 3: Criar Feature Flag

Adicione a feature flag no arquivo de configuração ou no código:

```php
use DotProject\Core\FeatureFlag;

$features = FeatureFlag::getInstance();

// Verificar se deve usar novo código
if ($features->isEnabled('modern_tasks', $userId)) {
    // Usar novo repository
    $repo = new TaskRepository();
    $tasks = $repo->findByProject($projectId);
} else {
    // Usar código legado
    $tasks = $db->fetchAll(
        "SELECT * FROM dotp_tasks WHERE task_project = ?",
        [$projectId]
    );
}
```

### Passo 4: Configurar Feature Flag

Adicione no `.env`:

```env
# Feature Flags
FEATURE_MODERN_TASKS=false
FEATURE_MODERN_TASKS_PERCENT=0
```

Ou crie `config/features.php`:

```php
<?php
return [
    'modern_tasks' => [
        'enabled' => false,
        'rollout_percentage' => 0,
        'allowed_users' => [1, 2, 3], // IDs de usuários de teste
    ],
];
```

### Passo 5: Testar Migração

Habilite para usuários de teste:

```php
use DotProject\Core\FeatureFlag;

$features = FeatureFlag::getInstance();

// Habilitar para usuário específico
$features->enableForUser('modern_tasks', 123);

// Ou aumentar rollout gradualmente
$features->setRolloutPercentage('modern_tasks', 10); // 10% dos usuários
```

### Passo 6: Migrar Gradualmente

1. **0%** - Testes em desenvolvimento
2. **5%** - Usuários beta
3. **25%** - Rollout gradual
4. **50%** - Metade dos usuários
5. **100%** - Todos os usuários
6. **Remover** código legado

---

## 📊 Monitoramento

### Verificar status das features

```php
use DotProject\Core\FeatureFlag;

$features = FeatureFlag::getInstance();
print_r($features->getAllFeatures());
```

### Verificar status de migração

```php
use DotProject\Core\LegacyAdapter;

print_r(LegacyAdapter::getMigrationStatus());
```

---

## 🔧 Adapter Legado

Para facilitar a transição, use o `LegacyAdapter`:

```php
use DotProject\Core\LegacyAdapter;

$adapter = new LegacyAdapter();

// Busca compatível com código antigo
$project = $adapter->getProjectLegacy($projectId, $userId);

// Retorna array no formato legado
$projects = $adapter->getProjectsLegacy(['project_status' => 0], $userId);

// Criação compatível
$adapter->createProjectLegacy($data, $userId);
```

---

## 📝 Checklist de Migração

### Para cada módulo:

- [ ] Criar Entity com tipagem
- [ ] Criar Repository extends BaseRepository
- [ ] Implementar hydrate() e extract()
- [ ] Adicionar métodos específicos do domínio
- [ ] Criar Feature Flag
- [ ] Atualizar controllers para usar feature flag
- [ ] Testar com usuários beta (5%)
- [ ] Aumentar rollout gradualmente (25% → 50% → 100%)
- [ ] Remover código legado
- [ ] Atualizar documentação

---

## ⚠️ Dicas Importantes

### 1. Sempre mantenha fallback
```php
if ($features->isEnabled('modern_x', $userId)) {
    // Novo código
} else {
    // Código legado - mantenha funcionando!
}
```

### 2. Use cache nas entidades
```php
protected int $cacheTtl = 300; // 5 minutos no repository
```

### 3. Valide dados na entity
```php
public function setName(string $name): self
{
    if (strlen($name) < 3) {
        throw new \InvalidArgumentException('Name too short');
    }
    $this->name = $name;
    return $this;
}
```

### 4. Mantenha compatibilidade de nomes
```php
// Entity moderno
$entity->getName();

// Legacy adapter converte para
$data['task_name']; // ou 'project_name', etc
```

---

## 🚀 Exemplo Completo: Migração do Módulo Tasks

### 1. Entity (src/Entity/TaskEntity.php)
✅ Criada com todos os campos da tabela dotp_tasks

### 2. Repository (src/Repository/TaskRepository.php)
✅ Estende BaseRepository
✅ Implementa hydrate/extract
✅ Métodos específicos: findByProject(), findOverdue(), etc

### 3. Feature Flag
```env
FEATURE_MODERN_TASKS=true
FEATURE_MODERN_TASKS_PERCENT=100
```

### 4. Controller atualizado
```php
class TaskController extends BaseController
{
    private LegacyAdapter $adapter;
    
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->adapter = new LegacyAdapter();
    }
    
    public function index(): Response
    {
        $userId = $this->getUserId();
        $tasks = $this->adapter->getTasksLegacy(
            ['task_status' => 0],
            $userId
        );
        
        return $this->json(['data' => $tasks]);
    }
}
```

---

## 📚 Documentação Relacionada

- `FASE3_RESUMO.md` - Detalhes do Repository Pattern
- `src/Core/FeatureFlag.php` - Implementação de feature flags
- `src/Core/LegacyAdapter.php` - Adapter para migração

---

## ✨ Resumo

A migração gradual permite:
- ✅ Zero downtime
- ✅ Testes em produção com usuários limitados
- ✅ Rollback instantâneo
- ✅ Migração módulo por módulo

**Próximo módulo a migrar:** Tasks
