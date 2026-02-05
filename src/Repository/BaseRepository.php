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

/**
 * Repository base com cache integrado
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Database $db;
    protected Cache $cache;
    /** @var string */
    protected $table;
    /** @var string */
    protected $primaryKey;
    protected int $cacheTtl;

    public function __construct(?Database $db = null, ?Cache $cache = null)
    {
        error_log('[DEBUG] BaseRepository::__construct() iniciado');
        try {
            $this->db = $db ?? Database::getInstance();
            error_log('[DEBUG] Database instance OK');
            $this->cache = $cache ?? new Cache();
            error_log('[DEBUG] Cache instance OK');
            if (!isset($this->primaryKey) || $this->primaryKey === '') {
                $this->primaryKey = 'id';
            }
            $this->cacheTtl = 300; // 5 minutos
            error_log('[DEBUG] BaseRepository::__construct() concluido');
        } catch (\Throwable $e) {
            error_log('[DEBUG] ERRO em BaseRepository::__construct(): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gera chave de cache
     */
    protected function cacheKey(string $suffix): string
    {
        return sprintf('%s:%s:%s', static::class, $this->table, $suffix);
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

        $data = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        );

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

        $results = $this->db->fetchAll("SELECT * FROM {$this->table}");
        $entities = array_map([$this, 'hydrate'], $results);
        
        $this->cache->set($cacheKey, $entities, $this->cacheTtl);

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $cacheKey = $this->cacheKey('findBy:' . md5(serialize(func_get_args())));
        
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
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $this->db->execute($sql, [$id]);
        $this->clearCache();
        return $this->db->rowCount() > 0;
    }
}
