<?php
/**
 * Estado: Licitação (Etapa 2)
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class LicitacaoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Licitacao';
    }
    
    public function getLabel(): string
    {
        return 'Em Licitação';
    }
    
    public function getEtapaNumero(): int
    {
        return 2;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Execucao',       // Avança para próxima etapa
            'Atrasado',       // Licitação parada
            'Impedido',       // Liminar
            'Recuperacao',    // Plano de ação
            'Cancelado',
        ];
    }
}
