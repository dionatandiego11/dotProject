<?php
/**
 * Estado: Cancelado
 *
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;

class CanceladoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Cancelado';
    }

    public function getLabel(): string
    {
        return 'Cancelado';
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
        return [];
    }

    public function calcularPercentualExecucao(ProjetoEntity $projeto): float
    {
        return $projeto->getPercentExecucao();
    }

    public function podeAvancarEtapa(ProjetoEntity $projeto): bool
    {
        return false;
    }
}
