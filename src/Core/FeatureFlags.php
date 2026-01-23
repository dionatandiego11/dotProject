<?php
/**
 * DotProject Feature Flags
 * 
 * Sistema de feature toggles para habilitar/desabilitar funcionalidades
 * durante o processo de modernização.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Gerenciador de Feature Flags
 * 
 * Permite habilitar/desabilitar módulos e funcionalidades
 * de forma centralizada durante a transição de modernização.
 */
class FeatureFlags
{
    private static ?FeatureFlags $instance = null;

    /**
     * Módulos que foram descontinuados e devem ser removidos
     * @var array<string, bool>
     */
    private array $deprecatedModules = [
        'calendar' => true,      // Substituído por Google Calendar
        'files' => true,         // Substituído por Google Drive
        'forums' => true,        // Substituído por Slack/Teams
        'ticketsmith' => true,   // Substituído por sistema externo
        'links' => true,         // Baixo valor agregado
        'help' => true,          // Documentação externa
    ];

    /**
     * Módulos que serão mantidos e evoluídos
     * @var array<string, bool>
     */
    private array $coreModules = [
        'projects' => true,
        'tasks' => true,
        'companies' => true,
        'departments' => true,
        'resources' => true,
        'risks' => true,
        'admin' => true,
        'system' => true,
        'public' => true,
        'history' => true,
        'smartsearch' => true,
        'projectdesigner' => true,
        'scope_and_schedule' => true,
    ];

    /**
     * Feature flags gerais
     * @var array<string, bool>
     */
    private array $features = [
        'modern_api' => true,           // API REST moderna
        'jwt_auth' => false,            // Autenticação JWT (fase 1)
        'google_integration' => false,   // Integração Google (fase 3)
        'microsoft_integration' => false, // Integração Microsoft (fase 3)
        'react_frontend' => false,       // Frontend React (fase 4)
    ];

    private function __construct()
    {
        // Singleton
    }

    /**
     * Obtém a instância única
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verifica se um módulo está ativo (não foi deprecated)
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        // Se está na lista de deprecated, está desabilitado
        if (isset($this->deprecatedModules[$moduleName]) && $this->deprecatedModules[$moduleName]) {
            return false;
        }

        // Se está na lista de core, está habilitado
        if (isset($this->coreModules[$moduleName])) {
            return $this->coreModules[$moduleName];
        }

        // Módulos desconhecidos são habilitados por padrão (compatibilidade)
        return true;
    }

    /**
     * Verifica se um módulo foi marcado como deprecated
     */
    public function isModuleDeprecated(string $moduleName): bool
    {
        return isset($this->deprecatedModules[$moduleName]) && $this->deprecatedModules[$moduleName];
    }

    /**
     * Verifica se uma feature está habilitada
     */
    public function isFeatureEnabled(string $featureName): bool
    {
        return $this->features[$featureName] ?? false;
    }

    /**
     * Habilita uma feature (para uso em testes ou configuração dinâmica)
     */
    public function enableFeature(string $featureName): void
    {
        $this->features[$featureName] = true;
    }

    /**
     * Desabilita uma feature
     */
    public function disableFeature(string $featureName): void
    {
        $this->features[$featureName] = false;
    }

    /**
     * Retorna lista de módulos deprecated
     * 
     * @return array<string>
     */
    public function getDeprecatedModules(): array
    {
        return array_keys(array_filter($this->deprecatedModules));
    }

    /**
     * Retorna lista de módulos ativos (core)
     * 
     * @return array<string>
     */
    public function getCoreModules(): array
    {
        return array_keys(array_filter($this->coreModules));
    }

    /**
     * Retorna todas as features e seus estados
     * 
     * @return array<string, bool>
     */
    public function getAllFeatures(): array
    {
        return $this->features;
    }
}

/**
 * Helper function para acesso rápido às feature flags
 */
if (!function_exists('feature')) {
    function feature(string $name): bool
    {
        return FeatureFlags::getInstance()->isFeatureEnabled($name);
    }
}

/**
 * Helper function para verificar se módulo está habilitado
 */
if (!function_exists('moduleEnabled')) {
    function moduleEnabled(string $name): bool
    {
        return FeatureFlags::getInstance()->isModuleEnabled($name);
    }
}
