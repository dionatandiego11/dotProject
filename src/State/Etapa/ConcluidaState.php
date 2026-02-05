<?php
/**
 * Estado: Concluída
 * Etapa finalizada no prazo
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class ConcluidaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Concluida';
    }
    
    public function getLabel(): string
    {
        return 'Concluída';
    }
    
    public function getColor(): string
    {
        return '#22c55e'; // Verde
    }
    
    public function getAllowedTransitions(): array
    {
        return [];
    }
    
    public function isConcluida(): bool
    {
        return true;
    }
    
    public function isAtiva(): bool
    {
        return false;
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            $context->setDataRealFim(new \DateTime());
            $context->setPercentConclusao(100.0);
            $context->setDiasAtraso(0);
            
            $this->logger->info("Etapa {$context->getId()} concluída no prazo");
        }
    }
}
