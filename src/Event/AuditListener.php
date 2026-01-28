<?php
/**
 * Audit Listener
 * 
 * Listener para auditoria de eventos de dominio.
 * Registra todas as operacoes importantes no banco de dados.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\Database;
use DotProject\Core\DomainEvents;
use DotProject\Core\EventDispatcher;
use DotProject\Core\Logger;

/**
 * Listener de auditoria
 * 
 * Registra operacoes em log de auditoria.
 */
class AuditListener
{
    private EventDispatcher $dispatcher;
    private Database $db;
    
    public function __construct(?EventDispatcher $dispatcher = null, ?Database $db = null)
    {
        $this->dispatcher = $dispatcher ?? EventDispatcher::getInstance();
        $this->db = $db ?? Database::getInstance();
        $this->register();
    }
    
    /**
     * Registra listeners
     */
    private function register(): void
    {
        // Auditoria de projetos
        $this->dispatcher->on(DomainEvents::PROJECT_CREATED, [$this, 'auditProjectCreated']);
        $this->dispatcher->on(DomainEvents::PROJECT_UPDATED, [$this, 'auditProjectUpdated']);
        $this->dispatcher->on(DomainEvents::PROJECT_DELETED, [$this, 'auditProjectDeleted']);
        
        // Auditoria de tarefas
        $this->dispatcher->on(DomainEvents::TASK_CREATED, [$this, 'auditTaskCreated']);
        $this->dispatcher->on(DomainEvents::TASK_UPDATED, [$this, 'auditTaskUpdated']);
        $this->dispatcher->on(DomainEvents::TASK_DELETED, [$this, 'auditTaskDeleted']);
        
        // Auditoria de usuarios
        $this->dispatcher->on(DomainEvents::USER_CREATED, [$this, 'auditUserCreated']);
        $this->dispatcher->on(DomainEvents::USER_UPDATED, [$this, 'auditUserUpdated']);
        $this->dispatcher->on(DomainEvents::USER_DELETED, [$this, 'auditUserDeleted']);
        $this->dispatcher->on(DomainEvents::USER_LOGIN, [$this, 'auditUserLogin']);
    }
    
    /**
     * Registra auditoria
     */
    private function logAudit(
        string $action,
        string $entityType,
        int $entityId,
        ?int $userId,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $this->db->insert('audit_log', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId,
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to write audit log', [
                'error' => $e->getMessage(),
                'action' => $action,
                'entity' => $entityType,
            ]);
        }
    }
    
    /**
     * Projeto criado
     */
    public function auditProjectCreated(ProjectEvent $event): void
    {
        $project = $event->getProject();
        
        $this->logAudit(
            'CREATE',
            'project',
            $project->getId() ?? 0,
            $event->getUserId(),
            null,
            $project->toArray()
        );
    }
    
    /**
     * Projeto atualizado
     */
    public function auditProjectUpdated(ProjectEvent $event): void
    {
        $project = $event->getProject();
        $data = $event->getData();
        
        $this->logAudit(
            'UPDATE',
            'project',
            $project->getId() ?? 0,
            $event->getUserId(),
            $data['old_data'] ?? null,
            $project->toArray()
        );
    }
    
    /**
     * Projeto deletado
     */
    public function auditProjectDeleted(ProjectEvent $event): void
    {
        $data = $event->getData();
        
        $this->logAudit(
            'DELETE',
            'project',
            $data['project_id'] ?? 0,
            $event->getUserId(),
            $data['project_data'] ?? null,
            null
        );
    }
    
    /**
     * Tarefa criada
     */
    public function auditTaskCreated(TaskEvent $event): void
    {
        $task = $event->getTask();
        
        $this->logAudit(
            'CREATE',
            'task',
            $task->getId() ?? 0,
            $event->getUserId(),
            null,
            $task->toArray()
        );
    }
    
    /**
     * Tarefa atualizada
     */
    public function auditTaskUpdated(TaskEvent $event): void
    {
        $task = $event->getTask();
        $data = $event->getData();
        
        $this->logAudit(
            'UPDATE',
            'task',
            $task->getId() ?? 0,
            $event->getUserId(),
            $data['old_data'] ?? null,
            $task->toArray()
        );
    }
    
    /**
     * Tarefa deletada
     */
    public function auditTaskDeleted(TaskEvent $event): void
    {
        $data = $event->getData();
        
        $this->logAudit(
            'DELETE',
            'task',
            $data['task_id'] ?? 0,
            $event->getUserId(),
            $data['task_data'] ?? null,
            null
        );
    }
    
    /**
     * Usuario criado
     */
    public function auditUserCreated(UserEvent $event): void
    {
        $user = $event->getUser();
        
        if ($user) {
            $this->logAudit(
                'CREATE',
                'user',
                $user->getId() ?? 0,
                $event->getUserId(),
                null,
                ['username' => $user->getUsername(), 'email' => $user->getEmail()]
            );
        }
    }
    
    /**
     * Usuario atualizado
     */
    public function auditUserUpdated(UserEvent $event): void
    {
        $user = $event->getUser();
        
        if ($user) {
            $this->logAudit(
                'UPDATE',
                'user',
                $user->getId() ?? 0,
                $event->getUserId(),
                null,
                ['username' => $user->getUsername(), 'changed_fields' => $event->getChangedFields()]
            );
        }
    }
    
    /**
     * Usuario deletado
     */
    public function auditUserDeleted(UserEvent $event): void
    {
        $this->logAudit(
            'DELETE',
            'user',
            $event->getUserId() ?? 0,
            $event->getUserId(),
            ['username' => $event->getUsername()],
            null
        );
    }
    
    /**
     * Login de usuario
     */
    public function auditUserLogin(UserEvent $event): void
    {
        $this->logAudit(
            'LOGIN',
            'user',
            $event->getUserId() ?? 0,
            $event->getUserId(),
            null,
            ['username' => $event->getUsername(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }
}
