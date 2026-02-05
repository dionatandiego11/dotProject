<?php
/**
 * Interface para estados de projeto
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Entity\ProjetoEntity;
use DotProject\State\StateInterface;

interface ProjetoStateInterface extends StateInterface
{
    /**
     * Calcula percentual de execução do projeto neste estado
     */
    public function calcularPercentualExecucao(ProjetoEntity $projeto): float;
    
    /**
     * Verifica se o projeto pode avançar para próxima etapa
     */
    public function podeAvancarEtapa(ProjetoEntity $projeto): bool;
    
    /**
     * Retorna número da etapa atual (1-5)
     */
    public function getEtapaNumero(): int;
}
