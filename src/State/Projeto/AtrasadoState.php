<?php
/**
 * Estado: Atrasado
 * Indica que o projeto está em atraso
 * 
 * @package DotProject\State\Projeto
 */

declare(strict_types=1);

namespace DotProject\State\Projeto;

use DotProject\Service\NotificationService;

class AtrasadoState extends AbstractProjetoState
{
    private NotificationService $notificador;
    
    public function __construct()
    {
        parent::__construct();
        $this->notificador = new NotificationService();
    }
    
    public function getName(): string
    {
        return 'Atrasado';
    }
    
    public function getLabel(): string
    {
        return 'Atrasado';
    }
    
    public function getColor(): string
    {
        return '#ef4444'; // Vermelho
    }
    
    public function getEtapaNumero(): int
    {
        // Retorna a etapa atual do projeto (não muda)
        return 0; // Será obtido do contexto
    }
    
    public function getAllowedTransitions(): array
    {
        return [
            'Recuperacao',     // Plano de ação criado
            'Planejamento',    // Se estava na etapa 1
            'Licitacao',       // Se estava na etapa 2
            'Execucao',        // Se estava na etapa 3
            'Medicao',         // Se estava na etapa 4
            'Pagamento',       // Se estava na etapa 5
            'Cancelado',
        ];
    }
    
    public function onEnter(?object $context = null): void
    {
        parent::onEnter($context);
        
        if ($context instanceof \DotProject\Entity\ProjetoEntity) {
            $diasAtraso = $context->getDiasAtraso();
            
            // Notifica gestores
            $this->notificador->notificarAtrasoProjeto($context, $diasAtraso);
            
            $this->logger->warning(
                "Projeto {$context->getId()} em estado de ATRASO - {$diasAtraso} dias"
            );
        }
    }
}
