<?php
/**
 * Estado: Pagamento (Etapa 5 - Final)
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class PagamentoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Pagamento';
    }
    
    public function getLabel(): string
    {
        return 'Em Pagamento';
    }
    
    public function getEtapaNumero(): int
    {
        return 5;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Concluido',      // Projeto finalizado
            'Atrasado',
        ];
    }
    
    public function podeAvancarEtapa(\DotProject\Entity\ProjetoEntity $projeto): bool
    {
        // Na etapa 5, "avançar" significa concluir o projeto
        $etapa = $projeto->getEtapaAtual();
        return $etapa && $etapa->isConcluida();
    }
}
