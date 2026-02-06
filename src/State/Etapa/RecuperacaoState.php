<?php
/**
 * Estado: Recuperacao
 *
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class RecuperacaoState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Recuperacao';
    }

    public function getLabel(): string
    {
        return 'Em recuperacao';
    }

    public function getColor(): string
    {
        return '#f59e0b';
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Em_Andamento',
            'Dentro_Prazo',
            'Atrasada',
            'Concluida_Com_Atraso',
        ];
    }
}
