<?php
/**
 * Estado: Execução (Etapa 3)
 * Etapa principal onde tarefas são executadas
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

class ExecucaoState extends AbstractProjetoState
{
    public function getName(): string
    {
        return 'Execucao';
    }
    
    public function getLabel(): string
    {
        return 'Em Execução';
    }
    
    public function getEtapaNumero(): int
    {
        return 3;
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Medicao',        // Avança para próxima etapa
            'Atrasado',       // Obra/executação parada
            'Impedido',       // Paralisação
            'Recuperacao',
        ];
    }
    
    public function onExit(?object $context = null): void
    {
        parent::onExit($context);
        
        // Ao sair da execução, verifica se há tarefas pendentes
        if ($context instanceof \DotProject\Entity\ProjetoEntity) {
            $tarefasPendentes = $context->countTarefasPendentas();
            if ($tarefasPendentes > 0) {
                $this->logger->warning(
                    "Projeto {$context->getId()} avançou de Execucao com {$tarefasPendentes} tarefas pendentes"
                );
            }
        }
    }
}
