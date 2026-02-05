<?php
/**
 * Estado: Não Iniciada
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class NaoIniciadaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Nao_Iniciada';
    }
    
    public function getLabel(): string
    {
        return 'Não Iniciada';
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Em_Andamento',
            'Adiada',
        ];
    }
    
    public function isAtiva(): bool
    {
        return false;
    }
}
