<?php
/**
 * Estado: Concluida com atraso
 *
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class ConcluidaComAtrasoState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Concluida_Com_Atraso';
    }

    public function getLabel(): string
    {
        return 'Concluida com atraso';
    }

    public function getColor(): string
    {
        return '#f59e0b';
    }

    public function getAllowedTransitions(): array
    {
        return [];
    }

    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);

        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            if ($context->getDataRealFim() === null) {
                $context->setDataRealFim(new \DateTime());
            }
            $context->setPercentConclusao(100.0);
        }
    }
}
