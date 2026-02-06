<?php
/**
 * Classe abstrata para estados de projeto
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;
use DotProject\State\AbstractState;
use DotProject\Event\ProjetoStatusChangedEvent;

abstract class AbstractProjetoState extends AbstractState implements ProjetoStateInterface
{
    public function getColor(): string
    {
        return $this->getDefaultColor();
    }

    /**
     * {@inheritdoc}
     */
    public function calcularPercentualExecucao(ProjetoEntity $projeto): float
    {
        $etapaAtual = $this->getEtapaNumero();
        $percentEtapa = $projeto->getEtapaAtual()?->getPercentConclusao() ?? 0;
        
        // Fórmula: ((etapa-1) / 5 * 100) + (percent da etapa / 5)
        return (($etapaAtual - 1) / 5 * 100) + ($percentEtapa / 5);
    }
    
    /**
     * {@inheritdoc}
     */
    public function podeAvancarEtapa(ProjetoEntity $projeto): bool
    {
        $etapa = $projeto->getEtapaAtual();
        
        // Só pode avançar se etapa atual estiver concluída
        if (!$etapa || !$etapa->isConcluida()) {
            return false;
        }
        
        // Não pode avançar além da etapa 5
        if ($this->getEtapaNumero() >= 5) {
            return false;
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof ProjetoEntity) {
            // Dispara evento de mudança de status
            $this->events->dispatch(new ProjetoStatusChangedEvent(
                $context->getId(),
                $context->getEstadoAnterior(),
                $this->getName(),
                $context->getCoordenadorId()
            ));
            
            // Recalcula percentual
            $context->setPercentExecucao($this->calcularPercentualExecucao($context));
            
            // Propaga para programa pai
            $this->atualizarProgramaPai($context);
        }
    }
    
    /**
     * Atualiza o programa pai quando o projeto muda de estado
     */
    protected function atualizarProgramaPai(ProjetoEntity $projeto): void
    {
        $programa = $projeto->getPrograma();
        if ($programa) {
            $programa->recalcularPercentualExecucao();
            $programa->atualizarStatusAutomatico();
        }
    }
}
