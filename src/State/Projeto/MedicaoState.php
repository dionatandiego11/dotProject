<?php
/**
 * Estado: Medição (Etapa 4)
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class MedicaoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Medicao';
    }
    
    public function getLabel(): string
    {
        return 'Em Medição';
    }
    
    public function getEtapaNumero(): int
    {
        return 4;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Pagamento',      // Avança para última etapa
            'Atrasado',
            'Impedido',
        ];
    }
}
