<?php
/**
 * Interface para estados de etapa
 * 
 * @package DotProject\State\Etapa
 */

declare(strict_types=1);

namespace DotProject\State\Etapa;

use DotProject\Entity\EtapaEntity;
use DotProject\State\StateInterface;

interface EtapaStateInterface extends StateInterface
{
    /**
     * Verifica e calcula dias de atraso
     * 
     * @return int|null Dias de atraso ou null se não aplicável
     */
    public function verificarAtraso(EtapaEntity $etapa): ?int;
    
    /**
     * Verifica se a etapa está concluída
     */
    public function isConcluida(): bool;
    
    /**
     * Verifica se a etapa está ativa (não concluída nem cancelada)
     */
    public function isAtiva(): bool;
}
