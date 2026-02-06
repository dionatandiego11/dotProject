<?php
/**
 * Estado: Aguardando inicio
 *
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;

class AguardandoInicioState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Aguardando_Inicio';
    }

    public function getLabel(): string
    {
        return 'Aguardando inicio';
    }

    public function getEtapaNumero(): int
    {
        return 0;
    }

    public function getAllowedTransitions(): array
    {
        return [
            'Planejamento',
            'Cancelado',
        ];
    }

    public function calcularPercentualExecucao(ProjetoEntity $projeto): float
    {
        return 0.0;
    }
}
