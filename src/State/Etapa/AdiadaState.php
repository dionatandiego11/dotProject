<?php
/**
 * Estado: Adiada
 *
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class AdiadaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Adiada';
    }

    public function getLabel(): string
    {
        return 'Adiada';
    }

    public function getColor(): string
    {
        return '#6b7280';
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Nao_Iniciada',
            'Em_Andamento',
            'Impedida',
        ];
    }

    public function isAtiva(): bool
    {
        return false;
    }
}
