<?php
/**
 * Estado: Crítica
 * Mais de 30 dias de atraso
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class CriticaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Critica';
    }
    
    public function getLabel(): string
    {
        return 'Crítica';
    }
    
    public function getColor(): string
    {
        return '#dc2626'; // Vermelho escuro
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Concluida_Com_Atraso',
            'Impedida',
            'Recuperacao',
        ];
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            // Notificação de alta prioridade
            $this->notificador->notificarCritico($context);
            
            $this->logger->error(
                "Etapa {$context->getId()} em estado CRÍTICO - mais de 30 dias de atraso"
            );
        }
    }
}
