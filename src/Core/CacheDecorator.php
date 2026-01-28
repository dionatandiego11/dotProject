<?php
/**
 * Cache Decorator para Services
 * 
 * Implementa cache transparente em serviços.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Decorator para adicionar cache a serviços
 */
class CacheDecorator
{
    private object $service;
    private Cache $cache;
    private int $defaultTtl;
    private string $prefix;

    public function __construct(
        object $service,
        ?Cache $cache = null,
        int $defaultTtl = 300,
        string $prefix = ''
    ) {
        $this->service = $service;
        $this->cache = $cache ?? new Cache();
        $this->defaultTtl = $defaultTtl;
        $this->prefix = $prefix ?: get_class($service) . ':';
    }

    /**
     * Executa método com cache
     */
    public function remember(string $method, array $args = [], ?int $ttl = null): mixed
    {
        $cacheKey = $this->buildKey($method, $args);
        $ttl = $ttl ?? $this->defaultTtl;

        // Tentar obter do cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            Logger::debug('Cache remember HIT', [
                'service' => get_class($this->service),
                'method' => $method
            ]);
            return $cached;
        }

        // Executar método do serviço
        $result = $this->service->$method(...$args);

        // Armazenar no cache
        $this->cache->set($cacheKey, $result, $ttl);

        Logger::debug('Cache remember MISS', [
            'service' => get_class($this->service),
            'method' => $method,
            'ttl' => $ttl
        ]);

        return $result;
    }

    /**
     * Invalida cache de um método
     */
    public function forget(string $method, array $args = []): bool
    {
        if (empty($args)) {
            // Invalidar todos os caches deste método
            $pattern = $this->prefix . $method . ':*';
        } else {
            $pattern = $this->buildKey($method, $args);
        }

        return $this->cache->invalidate($pattern) > 0;
    }

    /**
     * Limpa todo o cache do serviço
     */
    public function flush(): bool
    {
        return $this->cache->invalidate($this->prefix . '*') > 0;
    }

    /**
     * Gera chave de cache
     */
    private function buildKey(string $method, array $args): string
    {
        $key = $this->prefix . $method;
        if (!empty($args)) {
            $key .= ':' . md5(serialize($args));
        }
        return $key;
    }

    /**
     * Proxy para métodos do serviço (sem cache)
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->service->$method(...$args);
    }
}
