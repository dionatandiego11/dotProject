<?php
/**
 * Estado: Planejamento (Etapa 1)
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class PlanejamentoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Planejamento';
    }
    
    public function getLabel(): string
    {
        return 'Em Planejamento';
    }
    
    public function getEtapaNumero(): int
    {
        return 1;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Licitacao',      // Avança para próxima etapa
            'Atrasado',       // Atraso detectado
            'Impedido',       // Bloqueio externo
            'Cancelado',      // Cancelamento
        ];
    }
}
