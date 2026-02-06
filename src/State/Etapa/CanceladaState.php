<?php
/**
 * Estado: Cancelada
 *
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class CanceladaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Cancelada';
    }

    public function getLabel(): string
    {
        return 'Cancelada';
    }

    public function getColor(): string
    {
        return '#6b7280';
    }

    public function getAllowedTransitions(): array
    {
        return [];
    }

    public function isAtiva(): bool
    {
        return false;
    }
}
