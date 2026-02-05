<?php
/**
 * Estado: Cadastrado
 * Projeto recém-criado, aguardando início
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class CadastradoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Cadastrado';
    }
    
    public function getLabel(): string
    {
        return 'Cadastrado';
    }
    
    public function getEtapaNumero(): int
    {
        return 0; // Ainda não começou
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Aguardando_Inicio',
            'Planejamento',
            'Cancelado',
        ];
    }
    
    public function calcularPercentualExecucao(\DotProject\Entity\ProjetoEntity $projeto): float
    {
        return 0.0;
    }
}
