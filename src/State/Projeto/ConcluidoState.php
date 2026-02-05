<?php
/**
 * Estado: Concluído
 * Projeto 100% entregue
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class ConcluidoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Concluido';
    }
    
    public function getLabel(): string
    {
        return 'Concluído';
    }
    
    public function getColor(): string
    {
        return '#22c55e'; // Verde
    }
    
    public function getEtapaNumero(): int
    {
        return 5;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            // Estado final - não permite transições normais
            // Apenas administrativo: 'Arquivado'
        ];
    }
    
    public function calcularPercentualExecucao(\DotProject\Entity\ProjetoEntity $projeto): float
    {
        return 100.0;
    }
    
    public function podeAvancarEtapa(\DotProject\Entity\ProjetoEntity $projeto): bool
    {
        return false; // Já está no final
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\ProjetoEntity) {
            // Registra data de conclusão
            $context->setDataConclusao(new \DateTime());
            
            $this->logger->info(
                "Projeto {$context->getId()} - '{$context->getNome()}' concluído com sucesso"
            );
        }
    }
}
