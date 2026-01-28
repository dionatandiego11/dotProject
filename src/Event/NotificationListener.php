<?php
/**
 * Notification Listener
 * 
 * Listener para envio de notificacoes em eventos de dominio.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\DomainEvents;
use DotProject\Core\EventDispatcher;
use DotProject\Core\Logger;
use DotProject\Entity\Notification;
use DotProject\Service\NotificationService;

/**
 * Listener de notificacoes
 * 
 * Registra-se nos eventos relevantes e envia notificacoes usando NotificationService.
 */
class NotificationListener
{
    private EventDispatcher $dispatcher;
    private NotificationService $notificationService;
    
    public function __construct(
        ?EventDispatcher $dispatcher = null,
        ?NotificationService $notificationService = null
    ) {
        $this->dispatcher = $dispatcher ?? EventDispatcher::getInstance();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->register();
    }
    
    /**
     * Registra listeners nos eventos
     */
    private function register(): void
    {
        // Notificacoes de projeto
        $this->dispatcher->on(DomainEvents::PROJECT_CREATED, [$this, 'onProjectCreated']);
        $this->dispatcher->on(DomainEvents::PROJECT_STATUS_CHANGED, [$this, 'onProjectStatusChanged']);
        
        // Notificacoes de tarefa
        $this->dispatcher->on(DomainEvents::TASK_ASSIGNED, [$this, 'onTaskAssigned']);
        $this->dispatcher->on(DomainEvents::TASK_COMPLETED, [$this, 'onTaskCompleted']);
        $this->dispatcher->on(DomainEvents::TASK_OVERDUE, [$this, 'onTaskOverdue']);
        
        // Notificacoes de usuario
        $this->dispatcher->on(DomainEvents::USER_PASSWORD_CHANGED, [$this, 'onPasswordChanged']);
    }
    
    /**
     * Projeto criado
     */
    public function onProjectCreated(ProjectEvent $event): void
    {
        $project = $event->getProject();
        $userId = $event->getUserId();
        
        Logger::info('Notification: Project created', [
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'created_by' => $userId,
        ]);
        
        // Notifica criador
        $this->notificationService->create(
            $userId ?? 0,
            Notification::TYPE_SYSTEM,
            'Projeto criado',
            "O projeto \"{$project->getName()}\" foi criado com sucesso.",
            'project',
            $project->getId()
        );
    }
    
    /**
     * Status do projeto alterado
     */
    public function onProjectStatusChanged(ProjectEvent $event): void
    {
        if (!$event->wasChanged('project_status')) {
            return;
        }
        
        $project = $event->getProject();
        $userId = $event->getUserId();
        
        Logger::info('Notification: Project status changed', [
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'new_status' => $project->getStatus(),
        ]);
        
        // Notifica alteracao
        $this->notificationService->createFromTemplate(
            $userId ?? 0,
            Notification::TYPE_PROJECT_STATUS,
            [
                'project_name' => $project->getName(),
                'new_status' => $this->getStatusLabel($project->getStatus()),
            ],
            'project',
            $project->getId()
        );
    }
    
    /**
     * Tarefa atribuida
     */
    public function onTaskAssigned(TaskAssignmentEvent $event): void
    {
        $task = $event->getTask();
        $assignedTo = $event->getAssignedTo();
        $assignedBy = $event->getAssignedBy();
        
        Logger::info('Notification: Task assigned', [
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
        ]);
        
        // Notifica quem foi atribuido
        $this->notificationService->createFromTemplate(
            $assignedTo,
            Notification::TYPE_TASK_ASSIGNED,
            [
                'task_name' => $task->getName(),
                'project_name' => "Project #{$task->getProjectId()}",
                'task_id' => $task->getId(),
            ],
            'task',
            $task->getId()
        );
    }
    
    /**
     * Tarefa completada
     */
    public function onTaskCompleted(TaskEvent $event): void
    {
        $task = $event->getTask();
        $userId = $event->getUserId();
        
        Logger::info('Notification: Task completed', [
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'completed_by' => $userId,
        ]);
        
        // Notifica o criador da tarefa (se diferente de quem completou)
        if ($task->getOwnerId() && $task->getOwnerId() !== $userId) {
            $this->notificationService->createFromTemplate(
                $task->getOwnerId(),
                Notification::TYPE_TASK_COMPLETED,
                [
                    'task_name' => $task->getName(),
                    'completed_by' => "User #{$userId}",
                ],
                'task',
                $task->getId()
            );
        }
    }
    
    /**
     * Tarefa atrasada
     */
    public function onTaskOverdue(TaskEvent $event): void
    {
        $task = $event->getTask();
        
        Logger::warning('Notification: Task overdue', [
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'end_date' => $task->getEndDate()?->format('Y-m-d'),
        ]);
        
        // Notifica assignee
        if ($task->getAssignedTo()) {
            $this->notificationService->createFromTemplate(
                $task->getAssignedTo(),
                Notification::TYPE_TASK_OVERDUE,
                [
                    'task_name' => $task->getName(),
                    'due_date' => $task->getEndDate()?->format('d/m/Y'),
                ],
                'task',
                $task->getId()
            );
        }
        
        // Notifica owner
        if ($task->getOwnerId() && $task->getOwnerId() !== $task->getAssignedTo()) {
            $this->notificationService->createFromTemplate(
                $task->getOwnerId(),
                Notification::TYPE_TASK_OVERDUE,
                [
                    'task_name' => $task->getName(),
                    'due_date' => $task->getEndDate()?->format('d/m/Y'),
                ],
                'task',
                $task->getId()
            );
        }
    }
    
    /**
     * Senha alterada
     */
    public function onPasswordChanged(UserEvent $event): void
    {
        $userId = $event->getUserId();
        $username = $event->getUsername();
        
        Logger::info('Notification: Password changed', [
            'user_id' => $userId,
            'username' => $username,
        ]);
        
        // Notifica usuario
        $this->notificationService->create(
            $userId ?? 0,
            Notification::TYPE_SYSTEM,
            'Senha alterada',
            'Sua senha foi alterada com sucesso. Se nao foi voce, entre em contato com o suporte.',
            'user',
            $userId
        );
    }
    
    /**
     * Helper: Retorna label do status
     */
    private function getStatusLabel(int $status): string
    {
        return match ($status) {
            0 => 'Nao definido',
            1 => 'Proposto',
            2 => 'Em planejamento',
            3 => 'Em andamento',
            4 => 'Suspenso',
            5 => 'Concluido',
            6 => 'Arquivado',
            default => 'Desconhecido',
        };
    }
}
