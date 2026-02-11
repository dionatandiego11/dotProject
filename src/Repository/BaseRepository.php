<?php
/**
 * Repository base com implementações comuns
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Core\Cache;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;

/**
 * Repository base com cache integrado
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Database $db;
    protected Cache $cache;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected int $cacheTtl;
    protected bool $tenantScoped = true;
    private ?string $tenantColumn = null;
    private ?bool $tenantColumnResolved = null;

    public function __construct(?Database $db = null, ?Cache $cache = null)
    {
        Logger::debug('BaseRepository::__construct() iniciado', [
            'repository' => static::class,
        ]);
        try {
            $this->db = $db ?? Database::getInstance();
            Logger::debug('Database instance OK', [
                'repository' => static::class,
            ]);
            $this->cache = $cache ?? new Cache();
            Logger::debug('Cache instance OK', [
                'repository' => static::class,
            ]);
            if (!isset($this->primaryKey) || $this->primaryKey === '') {
                $this->primaryKey = 'id';
            }
            $this->cacheTtl = 300; // 5 minutos
            Logger::debug('BaseRepository::__construct() concluido', [
                'repository' => static::class,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Erro em BaseRepository::__construct()', [
                'repository' => static::class,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Gera chave de cache
     */
    protected function cacheKey(string $suffix): string
    {
        $repositoryNamespace = str_replace('\\', '.', static::class);
        $tenantSegment = '';
        if (TenantContext::isEnabled()) {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId !== null && $tenantId > 0) {
                $tenantSegment = 'tenant:' . $tenantId . ':';
            }
        }

        return sprintf('%s:%s:%s%s', $repositoryNamespace, $this->table, $tenantSegment, $suffix);
    }

    /**
     * Invalida cache do repository
     */
    protected function clearCache(): void
    {
        $this->cache->invalidate($this->cacheKey('*'));
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?object
    {
        $cacheKey = $this->cacheKey("find:{$id}");
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $params = [$id];
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $sql = $this->appendTenantScopeToSql($sql, $params);

        $data = $this->db->fetchOne($sql, $params);

        if ($data === null) {
            return null;
        }

        $entity = $this->hydrate($data);
        $this->cache->set($cacheKey, $entity, $this->cacheTtl);

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $cacheKey = $this->cacheKey('findAll');
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $params = [];
        $sql = "SELECT * FROM {$this->table}";
        $sql = $this->appendTenantScopeToSql($sql, $params);
        $results = $this->db->fetchAll($sql, $params);
        $entities = array_map([$this, 'hydrate'], $results);
        
        $this->cache->set($cacheKey, $entities, $this->cacheTtl);

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $criteria = $this->applyTenantCriteria($criteria);
        $cacheKey = $this->cacheKey('findBy:' . md5(serialize([$criteria, $orderBy, $limit, $offset])));
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $where = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        if ($orderBy !== null) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $orderParts[] = "{$field} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }
        
        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        $results = $this->db->fetchAllParams($sql, $params);
        $entities = array_map([$this, 'hydrate'], $results);
        
        $this->cache->set($cacheKey, $entities, $this->cacheTtl);

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneBy(array $criteria): ?object
    {
        $results = $this->findBy($criteria, null, 1);
        return $results[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $criteria = []): int
    {
        $criteria = $this->applyTenantCriteria($criteria);
        $cacheKey = $this->cacheKey('count:' . md5(serialize($criteria)));
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $where = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $count = (int) $this->db->fetchColumn($sql, $params);
        $this->cache->set($cacheKey, $count, $this->cacheTtl);

        return $count;
    }

    /**
     * Hidrata dados em entidade
     * 
     * @param array<string, mixed> $data
     */
    abstract protected function hydrate(array $data): object;

    /**
     * Extrai dados da entidade
     * 
     * @return array<string, mixed>
     */
    abstract protected function extract(object $entity): array;

    /**
     * Salva (insere ou atualiza) uma entidade
     * 
     * @param object $entity
     * @return int ID da entidade
     */
    public function save(object $entity): int
    {
        $data = $this->extract($entity);
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $applyTenant = $this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null;
        if ($applyTenant && (!array_key_exists($tenantColumn, $data) || $data[$tenantColumn] === null || $data[$tenantColumn] === '')) {
            $data[$tenantColumn] = $tenantId;
        }

        $id = $entity->getId();
        
        if ($id) {
            // UPDATE
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
            $values[] = $id;
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            if ($applyTenant) {
                $sql .= " AND {$tenantColumn} = ?";
                $values[] = $tenantId;
            }
            $this->db->execute($sql, $values);
            $this->clearCache();
            return $id;
        } else {
            // INSERT
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->execute($sql, array_values($data));
            $newId = (int) $this->db->lastInsertId();
            $this->clearCache();
            return $newId;
        }
    }

    /**
     * Exclui uma entidade pelo ID
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $params = [$id];
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $sql = $this->appendTenantScopeToSql($sql, $params);
        $this->db->execute($sql, $params);
        $this->clearCache();
        return $this->db->rowCount() > 0;
    }

    protected function getTenantColumn(): ?string
    {
        if ($this->tenantColumnResolved !== null) {
            return $this->tenantColumn;
        }

        $this->tenantColumnResolved = true;
        $this->tenantColumn = null;

        if (!$this->tenantScoped || !TenantContext::isEnabled()) {
            return null;
        }

        try {
            $tableName = trim($this->table, '`');
            $count = (int) ($this->db->fetchColumn(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'tenant_id'",
                [$tableName]
            ) ?? 0);

            if ($count > 0) {
                $this->tenantColumn = 'tenant_id';
            }
        } catch (\Throwable $e) {
            $this->tenantColumn = null;
        }

        return $this->tenantColumn;
    }

    protected function getTenantId(): ?int
    {
        return TenantContext::getTenantId();
    }

    protected function shouldApplyTenantScope(): bool
    {
        return $this->tenantScoped
            && TenantContext::isEnabled()
            && $this->getTenantColumn() !== null
            && $this->getTenantId() !== null;
    }

    protected function applyTenantCriteria(array $criteria): array
    {
        if (!$this->shouldApplyTenantScope()) {
            return $criteria;
        }

        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        if ($tenantColumn === null || $tenantId === null) {
            return $criteria;
        }

        $criteria[$tenantColumn] = $tenantId;
        return $criteria;
    }

    protected function appendTenantScopeToSql(string $sql, array &$params, ?string $tableAlias = null): string
    {
        if (!$this->shouldApplyTenantScope()) {
            return $sql;
        }

        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        if ($tenantColumn === null || $tenantId === null) {
            return $sql;
        }

        $column = $tableAlias !== null && $tableAlias !== ''
            ? $tableAlias . '.' . $tenantColumn
            : $tenantColumn;

        $sql .= stripos($sql, ' WHERE ') === false
            ? " WHERE {$column} = ?"
            : " AND {$column} = ?";
        $params[] = $tenantId;

        return $sql;
    }
}
