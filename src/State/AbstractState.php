<?php
/**
 * Classe abstrata base para estados
 * 
 * @package DotProject\State
 */

declare(strict_types=1);

namespace DotProject\State;

use DotProject\Core\EventDispatcher;
use DotProject\Core\Logger;

abstract class AbstractState implements StateInterface
{
    protected EventDispatcher $events;
    protected Logger $logger;
    
    public function __construct()
    {
        $this->events = EventDispatcher::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * {@inheritdoc}
     */
    public function canTransitionTo(string $newState): bool
    {
        return in_array($newState, $this->getAllowedTransitions(), true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function onEnter(?object $context = null): void
    {
        $this->logger->debug("Entrando no estado: {$this->getName()}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function onExit(?object $context = null): void
    {
        $this->logger->debug("Saindo do estado: {$this->getName()}");
    }
    
    /**
     * Retorna cor padrão baseada no nome do estado
     */
    protected function getDefaultColor(): string
    {
        $colors = [
            'Concluido' => '#22c55e',
            'Concluida' => '#22c55e',
            'Ativo' => '#3b82f6',
            'Em_Dia' => '#22c55e',
            'Dentro_Prazo' => '#3b82f6',
            'Em_Andamento' => '#3b82f6',
            'Proximo_Prazo' => '#f59e0b',
            'Atencao' => '#f59e0b',
            'Atrasado' => '#ef4444',
            'Atrasada' => '#ef4444',
            'Critico' => '#dc2626',
            'Critica' => '#dc2626',
            'Impedido' => '#6b7280',
            'Impedida' => '#6b7280',
            'Inativo' => '#9ca3af',
            'Parado' => '#9ca3af',
            'Cancelado' => '#6b7280',
        ];
        
        return $colors[$this->getName()] ?? '#9ca3af';
    }
}
