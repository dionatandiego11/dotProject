<?php
/**
 * Classe abstrata para estados de etapa
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

use DotProject\Entity\EtapaEntity;
use DotProject\State\AbstractState;
use DotProject\Service\NotificationService;

abstract class AbstractEtapaState extends AbstractState implements EtapaStateInterface
{
    protected NotificationService $notificador;
    
    public function __construct()
    {
        parent::__construct();
        $this->notificador = new NotificationService();
    }
    
    public function verificarAtraso(EtapaEntity $etapa): ?int
    {
        if ($this->isConcluida()) {
            return null;
        }
        
        $dataPrevista = $etapa->getDataPrevistaFim();
        if (!$dataPrevista) {
            return null;
        }
        
        $hoje = new \DateTime();
        if ($hoje > $dataPrevista) {
            return $hoje->diff($dataPrevista)->days;
        }
        
        return null;
    }
    
    public function isConcluida(): bool
    {
        return in_array($this->getName(), ['Concluida', 'Concluida_Com_Atraso'], true);
    }
    
    public function isAtiva(): bool
    {
        return !in_array($this->getName(), [
            'Concluida', 'Concluida_Com_Atraso', 'Cancelada'
        ], true);
    }
    
    /**
     * Calcula cor baseada no estado
     */
    protected function getDefaultColor(): string
    {
        return match($this->getName()) {
            'Concluida' => '#22c55e',
            'Concluida_Com_Atraso' => '#f59e0b',
            'Dentro_Prazo' => '#3b82f6',
            'Em_Andamento' => '#3b82f6',
            'Proximo_Prazo' => '#f59e0b',
            'Atrasada' => '#ef4444',
            'Critica' => '#dc2626',
            'Impedida' => '#6b7280',
            'Nao_Iniciada' => '#9ca3af',
            default => '#9ca3af',
        };
    }
    
    public function getColor(): string
    {
        return $this->getDefaultColor();
    }
}
