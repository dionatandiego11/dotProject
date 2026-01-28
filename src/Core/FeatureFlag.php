<?php
/**
 * Feature Flag System
 * 
 * Sistema de feature flags para migração gradual.
 * Permite ativar/desativar funcionalidades por ambiente, usuário ou percentual.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Gerenciador de Feature Flags
 */
class FeatureFlag
{
    private static ?self $instance = null;
    private Cache $cache;
    private array $config;

    private function __construct()
    {
        $this->cache = new Cache(prefix: 'feature:');
        $this->config = $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carrega configuração de feature flags
     * 
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        // Prioridade: arquivo > env > padrão
        $configFile = DP_BASE_DIR . '/config/features.php';
        
        if (file_exists($configFile)) {
            return require $configFile;
        }

        // Configuração padrão via environment
        return [
            'new_dashboard' => [
                'enabled' => filter_var(getenv('FEATURE_NEW_DASHBOARD') ?: 'false', FILTER_VALIDATE_BOOL),
                'rollout_percentage' => (int)(getenv('FEATURE_NEW_DASHBOARD_PERCENT') ?: 0),
                'allowed_users' => [],
            ],
            'modern_api_only' => [
                'enabled' => filter_var(getenv('FEATURE_MODERN_API_ONLY') ?: 'false', FILTER_VALIDATE_BOOL),
                'rollout_percentage' => 0,
                'allowed_users' => [],
            ],
            'legacy_routes' => [
                'enabled' => true, // Sempre ativo por padrão
                'rollout_percentage' => 100,
                'allowed_users' => [],
            ],
        ];
    }

    /**
     * Verifica se uma feature está ativada
     */
    public function isEnabled(string $feature, ?int $userId = null): bool
    {
        $cacheKey = "{$feature}:" . ($userId ?? 'global');
        
        // Verificar cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->checkEnabled($feature, $userId);
        
        // Cache por 1 minuto (feature flags mudam com frequência)
        $this->cache->set($cacheKey, $result, 60);
        
        return $result;
    }

    /**
     * Lógica de verificação
     */
    private function checkEnabled(string $feature, ?int $userId): bool
    {
        if (!isset($this->config[$feature])) {
            // Feature desconhecida = desativada por segurança
            return false;
        }

        $config = $this->config[$feature];

        // 1. Verificar se está totalmente ativada
        if ($config['enabled'] === true && $config['rollout_percentage'] === 100) {
            return true;
        }

        // 2. Verificar se está totalmente desativada
        if ($config['enabled'] === false || $config['rollout_percentage'] === 0) {
            // Verificar usuários específicos mesmo se desativada globalmente
            if ($userId !== null && !empty($config['allowed_users'])) {
                return in_array($userId, $config['allowed_users'], true);
            }
            return false;
        }

        // 3. Rollout percentual
        if ($userId !== null) {
            // Usar ID do usuário para determinismo
            $userHash = crc32((string) $userId);
            $userPercent = $userHash % 100;
            
            if ($userPercent < $config['rollout_percentage']) {
                return true;
            }

            // Verificar lista de usuários permitidos
            if (!empty($config['allowed_users'])) {
                return in_array($userId, $config['allowed_users'], true);
            }
        }

        return false;
    }

    /**
     * Ativa uma feature para um usuário específico
     */
    public function enableForUser(string $feature, int $userId): void
    {
        if (!isset($this->config[$feature])) {
            $this->config[$feature] = [
                'enabled' => false,
                'rollout_percentage' => 0,
                'allowed_users' => [],
            ];
        }

        if (!in_array($userId, $this->config[$feature]['allowed_users'], true)) {
            $this->config[$feature]['allowed_users'][] = $userId;
        }

        // Limpar cache
        $this->cache->delete("{$feature}:{$userId}");
        
        Logger::info("Feature '{$feature}' enabled for user {$userId}");
    }

    /**
     * Desativa uma feature para um usuário específico
     */
    public function disableForUser(string $feature, int $userId): void
    {
        if (!isset($this->config[$feature])) {
            return;
        }

        $key = array_search($userId, $this->config[$feature]['allowed_users'], true);
        if ($key !== false) {
            unset($this->config[$feature]['allowed_users'][$key]);
        }

        // Limpar cache
        $this->cache->delete("{$feature}:{$userId}");
        
        Logger::info("Feature '{$feature}' disabled for user {$userId}");
    }

    /**
     * Define rollout percentual
     */
    public function setRolloutPercentage(string $feature, int $percentage): void
    {
        if (!isset($this->config[$feature])) {
            $this->config[$feature] = [
                'enabled' => false,
                'rollout_percentage' => 0,
                'allowed_users' => [],
            ];
        }

        $this->config[$feature]['rollout_percentage'] = max(0, min(100, $percentage));
        
        // Limpar cache global
        $this->cache->invalidate("{$feature}:*");
        
        Logger::info("Feature '{$feature}' rollout set to {$percentage}%");
    }

    /**
     * Lista todas as features e seus status
     * 
     * @return array<string, array>
     */
    public function getAllFeatures(): array
    {
        $result = [];
        
        foreach ($this->config as $feature => $config) {
            $result[$feature] = [
                'enabled' => $config['enabled'],
                'rollout_percentage' => $config['rollout_percentage'],
                'allowed_users_count' => count($config['allowed_users']),
            ];
        }
        
        return $result;
    }

    /**
     * Força recarregamento da configuração
     */
    public function reload(): void
    {
        $this->config = $this->loadConfig();
        $this->cache->clear();
        
        Logger::info('Feature flags configuration reloaded');
    }
}
