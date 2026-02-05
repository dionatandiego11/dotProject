<?php
/**
 * Estado: Dentro do Prazo
 * Mais de 7 dias do prazo final
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class DentroPrazoState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Dentro_Prazo';
    }
    
    public function getLabel(): string
    {
        return 'Dentro do Prazo';
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Proximo_Prazo',  // Faltam 7 dias
            'Atrasada',       // Atrasou
            'Impedida',
            'Concluida',      // Entregue no prazo
        ];
    }
}
