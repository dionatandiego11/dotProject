<?php
/**
 * Estado: Impedida
 *
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class ImpedidaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Impedida';
    }

    public function getLabel(): string
    {
        return 'Impedida';
    }

    public function getColor(): string
    {
        return '#6b7280';
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Recuperacao',
            'Em_Andamento',
            'Atrasada',
            'Concluida_Com_Atraso',
        ];
    }
}
