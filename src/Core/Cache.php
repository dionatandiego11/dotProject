<?php
/**
 * DotProject Cache System
 * 
 * Sistema de cache em camadas usando Redis.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

use Redis;

/**
 * Cache em camadas com Redis
 * 
 * Implementa operações de cache com fallback para memória
 */
class Cache
{
    private ?Redis $redis = null;
    private bool $enabled;
    private int $defaultTtl;
    private string $prefix;
    
    /** @var array<string, mixed> Cache em memória (camada L1) */
    private array $memoryCache = [];
    
    /** @var int TTL máximo em memória (segundos) */
    private int $memoryTtl = 5;
    
    /** @var array<string, int> Timestamps do cache em memória */
    private array $memoryTimestamps = [];

    public function __construct(
        ?Redis $redis = null,
        string $prefix = 'dp:',
        ?int $defaultTtl = null
    ) {
        error_log('[DEBUG] Cache::__construct() iniciado');
        $this->enabled = filter_var(
            getenv('CACHE_ENABLED') ?: 'true',
            FILTER_VALIDATE_BOOL
        );
        $this->defaultTtl = $defaultTtl ?? (int)(getenv('CACHE_TTL') ?: 300);
        $this->prefix = $prefix;
        
        if ($redis !== null) {
            $this->redis = $redis;
        } elseif ($this->enabled) {
            $this->connect();
        }
    }

    /**
     * Conecta ao Redis
     */
    private function connect(): void
    {
        // Verificar se a extensão Redis está disponível
        if (!class_exists('Redis')) {
            $this->redis = null;
            return;
        }
        
        try {
            $this->redis = new Redis();
            $connected = $this->redis->connect(
                getenv('REDIS_HOST') ?: 'localhost',
                (int)(getenv('REDIS_PORT') ?: 6379),
                2.0 // timeout
            );
            
            if (!$connected) {
                $this->redis = null;
                Logger::warning('Cache: Falha ao conectar ao Redis');
            }
        } catch (\Exception $e) {
            $this->redis = null;
            Logger::warning('Cache: Exceção ao conectar ao Redis', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica se o cache está disponível
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }
        
        try {
            return $this->redis->ping() === '+PONG';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gera chave com prefixo
     */
    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Verifica se chave existe no cache em memória e é válida
     */
    private function hasInMemory(string $key): bool
    {
        if (!isset($this->memoryCache[$key])) {
            return false;
        }
        
        // Verificar se expirou
        if (isset($this->memoryTimestamps[$key])) {
            $age = time() - $this->memoryTimestamps[$key];
            if ($age > $this->memoryTtl) {
                unset($this->memoryCache[$key], $this->memoryTimestamps[$key]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Armazena no cache em memória
     */
    private function setInMemory(string $key, mixed $value): void
    {
        // Limpar cache em memória se ficar muito grande (>100 itens)
        if (count($this->memoryCache) > 100) {
            $this->memoryCache = [];
            $this->memoryTimestamps = [];
        }
        
        $this->memoryCache[$key] = $value;
        $this->memoryTimestamps[$key] = time();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Camada 1: Memória
        if ($this->hasInMemory($key)) {
            Logger::debug('Cache HIT [memory]', ['key' => $key]);
            return $this->memoryCache[$key];
        }
        
        // Camada 2: Redis
        if (!$this->isAvailable()) {
            return $default;
        }
        
        try {
            $data = $this->redis->get($this->key($key));
            
            if ($data === false) {
                Logger::debug('Cache MISS', ['key' => $key]);
                return $default;
            }
            
            $value = $this->unserialize($data);
            
            // Armazenar em memória para próximas chamadas
            $this->setInMemory($key, $value);
            
            Logger::debug('Cache HIT [redis]', ['key' => $key]);
            return $value;
            
        } catch (\Exception $e) {
            Logger::warning('Cache: Erro ao ler do Redis', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if (!$this->isAvailable()) {
            // Ainda armazena em memória mesmo sem Redis
            $this->setInMemory($key, $value);
            return true;
        }
        
        $ttl = $ttl ?? $this->defaultTtl;
        
        if ($ttl instanceof \DateInterval) {
            $ttl = (int)\DateTime::createFromFormat('U', '0')
                ->add($ttl)
                ->format('U');
        }
        
        try {
            $data = $this->serialize($value);
            
            if ($ttl > 0) {
                $result = $this->redis->setex($this->key($key), $ttl, $data);
            } else {
                $result = $this->redis->set($this->key($key), $data);
            }
            
            // Também armazenar em memória
            $this->setInMemory($key, $value);
            
            Logger::debug('Cache SET', ['key' => $key, 'ttl' => $ttl]);
            return (bool)$result;
            
        } catch (\Exception $e) {
            Logger::warning('Cache: Erro ao escrever no Redis', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        // Limpar da memória
        unset($this->memoryCache[$key], $this->memoryTimestamps[$key]);
        
        if (!$this->isAvailable()) {
            return true;
        }
        
        try {
            $result = $this->redis->del($this->key($key));
            Logger::debug('Cache DELETE', ['key' => $key]);
            return $result > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Limpar memória
        $this->memoryCache = [];
        $this->memoryTimestamps = [];
        
        if (!$this->isAvailable()) {
            return true;
        }
        
        try {
            // Deletar apenas chaves com nosso prefixo
            $keys = $this->redis->keys($this->prefix . '*');
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
            Logger::info('Cache CLEAR');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if ($this->hasInMemory($key)) {
            return true;
        }
        
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            return (bool)$this->redis->exists($this->key($key));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Serializa dados para armazenamento
     */
    private function serialize(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Desserializa dados do armazenamento
     */
    private function unserialize(string $data): mixed
    {
        return json_decode($data, true);
    }

    /**
     * Invalida cache por padrão (wildcard)
     */
    public function invalidate(string $pattern): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($this->key($pattern));
            
            // Também limpar da memória
            foreach ($this->memoryCache as $key => $value) {
                if (fnmatch($pattern, $key)) {
                    unset($this->memoryCache[$key], $this->memoryTimestamps[$key]);
                }
            }
            
            if (empty($keys)) {
                return 0;
            }
            
            $count = $this->redis->del(...$keys);
            Logger::info('Cache INVALIDATE', ['pattern' => $pattern, 'count' => $count]);
            return $count;
            
        } catch (\Exception $e) {
            Logger::error('Cache: Erro ao invalidar', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Retorna estatísticas do cache
     */
    public function getStats(): array
    {
        if (!$this->isAvailable()) {
            return [
                'available' => false,
                'memory_items' => count($this->memoryCache),
            ];
        }
        
        try {
            $info = $this->redis->info();
            
            return [
                'available' => true,
                'memory_items' => count($this->memoryCache),
                'redis_keys' => $this->redis->dbSize(),
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }
}
