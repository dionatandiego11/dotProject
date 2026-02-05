<?php
/**
 * DotProject - Sistema de Gestão Pública baseado em PPA
 * Interface base para todos os estados do sistema
 * 
 * @package DotProject\State
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\State;

/**
 * Interface base para implementação do State Pattern
 * 
 * Todas as entidades que possuem máquina de estado devem implementar
 * estados que respeitem esta interface.
 */
interface StateInterface
{
    /**
     * Retorna o nome técnico do estado (usado em código)
     */
    public function getName(): string;
    
    /**
     * Retorna o label amigável do estado (usado em UI)
     */
    public function getLabel(): string;
    
    /**
     * Retorna a cor hexadecimal para representação visual
     */
    public function getColor(): string;
    
    /**
     * Verifica se é possível transicionar para um novo estado
     */
    public function canTransitionTo(string $newState): bool;
    
    /**
     * Retorna lista de estados permitidos para transição
     * 
     * @return array<string>
     */
    public function getAllowedTransitions(): array;
    
    /**
     * Executado ao entrar no estado
     */
    public function onEnter(?object $context = null): void;
    
    /**
     * Executado ao sair do estado
     */
    public function onExit(?object $context = null): void;
}
