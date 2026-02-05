<?php
/**
 * Estado: Em Andamento
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class EmAndamentoState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Em_Andamento';
    }
    
    public function getLabel(): string
    {
        return 'Em Andamento';
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Dentro_Prazo',
            'Proximo_Prazo',
            'Atrasada',
            'Impedida',
            'Concluida',
        ];
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            $context->setDataRealInicio(new \DateTime());
        }
    }
}
