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
        $className = "DotProject\\State\\Projeto\\{$estado}State";
        return self::createState($className, $estado);
    }
    
    /**
     * Cria instância de estado de etapa
     */
    public static function createEtapaState(string $estado): EtapaStateInterface
    {
        $className = "DotProject\\State\\Etapa\\{$estado}State";
        return self::createState($className, $estado);
    }
    
    /**
     * Cria instância de estado de programa
     */
    public static function createProgramaState(string $estado): ProgramaStateInterface
    {
        $className = "DotProject\\State\\Programa\\{$estado}State";
        return self::createState($className, $estado);
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
