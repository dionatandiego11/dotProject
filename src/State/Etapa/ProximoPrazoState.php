<?php
/**
 * Estado: Próximo do Prazo
 * Faltam 7 dias ou menos
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class ProximoPrazoState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Proximo_Prazo';
    }
    
    public function getLabel(): string
    {
        return 'Próximo do Prazo';
    }
    
    public function getColor(): string
    {
        return '#f59e0b'; // Amarelo
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Dentro_Prazo',      // Recuperou
            'Atrasada',          // Atrasou
            'Concluida',         // Entregue no limite
            'Concluida_Com_Atraso',
        ];
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            // Notifica responsável
            $this->notificador->notificarProximoPrazo($context);
        }
    }
}
