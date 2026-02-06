<?php
/**
 * Estado: Impedido
 *
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;

class ImpedidoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Impedido';
    }

    public function getLabel(): string
    {
        return 'Impedido';
    }

    public function getColor(): string
    {
        return '#6b7280';
    }

    public function getEtapaNumero(): int
    {
        return 0;
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Recuperacao',
            'Cancelado',
        ];
    }

    public function calcularPercentualExecucao(ProjetoEntity $projeto): float
    {
        return $projeto->getPercentExecucao();
    }
}
