<?php
/**
 * Estado: Recuperacao
 *
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;

class RecuperacaoState extends AbstractProjetoState
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

    public function getEtapaNumero(): int
    {
        return 0;
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Planejamento',
            'Licitacao',
            'Execucao',
            'Medicao',
            'Pagamento',
            'Atrasado',
            'Cancelado',
        ];
    }

    public function calcularPercentualExecucao(ProjetoEntity $projeto): float
    {
        return $projeto->getPercentExecucao();
    }
}
