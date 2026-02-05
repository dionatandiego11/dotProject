<?php
/**
 * Estado: Atrasada
 * Data prevista passou, menos de 30 dias
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

class AtrasadaState extends AbstractEtapaState
{
    public function getName(): string
    {
        return 'Atrasada';
    }
    
    public function getLabel(): string
    {
        return 'Atrasada';
    }
    
    public function getColor(): string
    {
        return '#ef4444'; // Vermelho
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Concluida_Com_Atraso',  // Entregue atrasada
            'Critica',               // Passou de 30 dias
            'Impedida',
            'Recuperacao',
        ];
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\EtapaEntity) {
            // Calcula dias de atraso
            $dias = $this->verificarAtraso($context);
            $context->setDiasAtraso($dias ?? 0);
            
            // Notifica
            $this->notificador->notificarAtraso($context, $dias);
            
            // Propaga para projeto
            $projeto = $context->getProjeto();
            if ($projeto) {
                $projeto->transicionarEstado('Atrasado');
            }
        }
    }
}
