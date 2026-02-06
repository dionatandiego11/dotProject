<?php
/**
 * Factory para criação de instâncias de estado
 * 
 * @package DotProject\State
 */

declare(strict_types=1);

namespace DotProject\State;

use DotProject\State\Projeto\ProjetoStateInterface;
use DotProject\State\Etapa\EtapaStateInterface;
use DotProject\State\Programa\ProgramaStateInterface;

class StateFactory
{
    private static array $cache = [];
    
    /**
     * Cria instância de estado de projeto
     */
    public static function createProjetoState(string $estado): ProjetoStateInterface
    {
        $className = self::buildClassName('DotProject\\State\\Projeto', $estado);
        return self::createState($className, $estado);
    }
    
    /**
     * Cria instância de estado de etapa
     */
    public static function createEtapaState(string $estado): EtapaStateInterface
    {
        $className = self::buildClassName('DotProject\\State\\Etapa', $estado);
        return self::createState($className, $estado);
    }
    
    /**
     * Cria instância de estado de programa
     */
    public static function createProgramaState(string $estado): ProgramaStateInterface
    {
        $className = self::buildClassName('DotProject\\State\\Programa', $estado);
        return self::createState($className, $estado);
    }

    /**
     * Resolve fully-qualified state class name from raw state value.
     */
    private static function buildClassName(string $namespace, string $estado): string
    {
        $stateClass = self::normalizeStateClass($estado);
        return "{$namespace}\\{$stateClass}State";
    }

    /**
     * Normalize snake_case, kebab-case and spaced names to PascalCase.
     */
    private static function normalizeStateClass(string $estado): string
    {
        $estado = trim($estado);
        if ($estado === '') {
            return $estado;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $estado) === 1) {
            return $estado;
        }

        $parts = preg_split('/[^A-Za-z0-9]+/', $estado, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return $estado;
        }

        $parts = array_map(
            static fn(string $part): string => ucfirst(strtolower($part)),
            $parts
        );

        return implode('', $parts);
    }
    
    /**
     * Cria estado com cache
     */
    private static function createState(string $className, string $estado): object
    {
        if (!isset(self::$cache[$className])) {
            if (!class_exists($className)) {
                throw new \InvalidArgumentException(
                    "Estado '{$estado}' não encontrado. Classe: {$className}"
                );
            }
            self::$cache[$className] = new $className();
        }
        
        return self::$cache[$className];
    }
    
    /**
     * Limpa cache de estados (útil para testes)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
